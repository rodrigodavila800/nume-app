<?php

namespace App\Services\Quotes;

use App\Models\Addon;
use App\Models\Cliente;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\VideoType;
use App\Services\Notification\SofiQuoteNotificationService;

/**
 * "Monte seu pacote": o cliente escolhe quantos vídeos de cada tipo e quais extras, e o total sobe.
 *
 * Preço só do banco: tipo de vídeo com builder_price (sem preço o tipo não aparece) e adicional ativo
 * marcado in_builder, sempre do tenant da produtora. O navegador só mostra a soma; quem vale é este
 * cálculo, refeito no servidor a cada envio. Planos de assinatura ficam fora (são fixos).
 */
class PackBuilderService
{
    /** Teto por tipo, para um envio adulterado não gerar proposta absurda. */
    public const MAX_PER_TYPE = 50;

    public function __construct(protected QuoteCalculatorService $calculator) {}

    /**
     * @return array{video_types: list<array{id: int, name: string, description: ?string, duration_seconds: int, price: float}>, addons: list<array{id: int, name: string, description: ?string, price: float}>, vat_rate: float}
     */
    public function catalog(Tenant $tenant): array
    {
        $types = VideoType::forTenant($tenant->id)
            ->active()
            ->where('builder_price', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (VideoType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'duration_seconds' => (int) $type->duration_seconds,
                'price' => round((float) $type->builder_price, 2),
            ])
            ->values()
            ->all();

        $addons = Addon::forTenant($tenant->id)
            ->where('is_active', true)
            ->where('in_builder', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Addon $addon): array => [
                'id' => $addon->id,
                'name' => $addon->name,
                'description' => $addon->description,
                'price' => round((float) $addon->default_price, 2),
            ])
            ->values()
            ->all();

        return [
            'video_types' => $types,
            'addons' => $addons,
            'vat_rate' => (float) $this->calculator->getDefaults($tenant)['iva'],
        ];
    }

    /**
     * Preço da montagem. Id desconhecido, de outra produtora ou fora do montador é ignorado;
     * quantidade fica entre 0 e MAX_PER_TYPE. Sem nenhum vídeo, não há pacote (null).
     *
     * @param  array{items?: array<int|string, mixed>, addons?: array<int, mixed>}  $selection  items: id do tipo => quantidade
     * @return array{lines: list<array{kind: string, id: int, name: string, quantity: int, unit_price: float, total: float}>, videos: int, subtotal: float, tax_rate: float, tax_amount: float, total: float, deposit: float, balance: float, signature: string}|null
     */
    public function price(Tenant $tenant, array $selection): ?array
    {
        $catalog = $this->catalog($tenant);
        $types = collect($catalog['video_types'])->keyBy('id');
        $addons = collect($catalog['addons'])->keyBy('id');

        $lines = [];
        foreach ((array) ($selection['items'] ?? []) as $typeId => $quantity) {
            $type = $types->get((int) $typeId);
            $quantity = max(0, min(self::MAX_PER_TYPE, (int) $quantity));

            if ($type && $quantity > 0) {
                $lines[] = ['kind' => 'video', 'id' => $type['id'], 'name' => $type['name'], 'quantity' => $quantity, 'unit_price' => $type['price'], 'total' => round($type['price'] * $quantity, 2)];
            }
        }

        $videos = array_sum(array_column($lines, 'quantity'));
        if ($videos === 0) {
            return null;
        }

        foreach (array_unique(array_map('intval', (array) ($selection['addons'] ?? []))) as $addonId) {
            if ($addon = $addons->get($addonId)) {
                $lines[] = ['kind' => 'addon', 'id' => $addon['id'], 'name' => $addon['name'], 'quantity' => 1, 'unit_price' => $addon['price'], 'total' => $addon['price']];
            }
        }

        $subtotal = round(array_sum(array_column($lines, 'total')), 2);
        $taxAmount = round($subtotal * $catalog['vat_rate'] / 100, 2);
        $total = round($subtotal + $taxAmount, 2);
        // Regra padrão das propostas de produção: sinal de 50% e saldo na entrega.
        $deposit = round($total * 0.5, 2);

        return [
            'lines' => $lines,
            'videos' => $videos,
            'subtotal' => $subtotal,
            'tax_rate' => $catalog['vat_rate'],
            'tax_amount' => $taxAmount,
            'total' => $total,
            'deposit' => $deposit,
            'balance' => round($total - $deposit, 2),
            'signature' => md5(json_encode(array_map(fn (array $l) => [$l['kind'], $l['id'], $l['quantity'], $l['unit_price']], $lines))),
        ];
    }

    /**
     * Proposta "enviada" da montagem (fluxo de sempre: aprovação com conta e pagamento confirmado só
     * pelo gateway). A mesma montagem ainda válida para o mesmo e-mail e canal reaproveita a proposta.
     *
     * @param  array{lines: list<array{kind: string, id: int, name: string, quantity: int, unit_price: float, total: float}>, videos: int, tax_rate: float, signature: string}  $priced  Saída de price()
     * @param  array{responsible: string, company: string, email: string}  $contact
     */
    public function createQuote(Tenant $tenant, array $priced, array $contact, string $source, ?Cliente $cliente = null, ?string $phone = null, bool $isSpanish = false): Quote
    {
        $email = mb_strtolower(trim($contact['email']));

        $existing = Quote::forTenant($tenant->id)
            ->where('client_email', $email)
            ->where('status', 'sent')
            ->where('request_details->source', $source)
            ->where('request_details->builder_signature', $priced['signature'])
            ->whereDate('valid_until', '>=', now()->toDateString())
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $defaults = $this->calculator->getDefaults($tenant);
        $calc = $this->calculator->calculate([
            'videos' => $priced['videos'],
            'filmmakers' => 1,
            'iva' => $priced['tax_rate'],
            'cambios' => $defaults['cambios'],
            'prazo' => $defaults['prazo'],
            'extras' => array_map(fn (array $line): array => [
                'nome' => $line['kind'] === 'video' ? "{$line['quantity']}× {$line['name']}" : $line['name'],
                'valor' => $line['total'],
            ], $priced['lines']),
        ]);

        $quote = Quote::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente?->id,
            'client_name' => $contact['company'],
            'client_contact' => $contact['responsible'],
            'client_email' => $email,
            'project_title' => ($isSpanish ? 'Paquete personalizado' : 'Pacote personalizado')." ({$priced['videos']} vídeos)",
            'language' => $isSpanish ? 'es' : 'pt_BR',
            'request_text' => 'Pacote montado pelo cliente ('.$source.').',
            'request_details' => array_filter([
                'source' => $source,
                'builder' => $priced['lines'],
                'builder_signature' => $priced['signature'],
                'phone' => $phone,
            ], fn ($value) => $value !== null),
            'inputs' => $calc['inputs_sanitized'],
            'calculation' => $calc,
            'subtotal' => $calc['preco'],
            'margin_amount' => $calc['margem'],
            'adjustment_amount' => $calc['adicional'],
            'tax_rate' => $calc['inputs_sanitized']['iva'],
            'tax_amount' => $calc['iva'],
            'total_amount' => $calc['total_com_iva'],
            'deposit_amount' => $calc['sinal'],
            'balance_amount' => $calc['na_entrega'],
            'valid_until' => now()->addDays(max(1, (int) $defaults['validade_dias'])),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        app(SofiQuoteNotificationService::class)->ensureApprovalToken($quote);

        return $quote;
    }
}
