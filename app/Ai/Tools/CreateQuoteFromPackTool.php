<?php

namespace App\Ai\Tools;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Pack;
use App\Models\Quote;
use App\Services\Crm\LeadService;
use App\Services\Notification\SofiQuoteNotificationService;
use App\Services\Quotes\QuoteCalculatorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Cria e "envia" a proposta de um pacote do catálogo para quem conversa no WhatsApp.
 *
 * Valores só do banco: o modelo escolhe o id do pacote e adicionais pelo nome, nunca o preço. A conta
 * passa pela QuoteCalculatorService (IVA e sinal de 50% como nos demais orçamentos) e o fechamento é do
 * cliente, na página pública /orcamento/{token}: faturação, forma de pagamento e confirmação só pelo
 * gateway (PackPaymentConfirmer), fora do alcance da Sofi. Não cobra nem marca nada como pago, por isso
 * não pede aprovação humana. Limite diário por telefone e reaproveitamento da proposta igual ainda válida
 * evitam spam e duplicata.
 */
class CreateQuoteFromPackTool implements Tool
{
    public const DAILY_LIMIT = 3;

    public function __construct(
        protected int $tenantId,
        protected ?int $clienteId = null,
        protected ?string $phone = null,
        protected ?int $leadId = null,
        protected bool $isSpanish = false,
    ) {}

    public function description(): Stringable|string
    {
        return 'Cria a proposta oficial de um pacote do catálogo e devolve o link seguro de aprovação e pagamento para enviar ao cliente. Use quando o cliente aceitar o pacote ou pedir a proposta. Exige o e-mail dele. Não informe preço: ele vem da proposta.';
    }

    public function handle(Request $request): Stringable|string
    {
        $cliente = $this->clienteId ? Cliente::forTenant($this->tenantId)->find($this->clienteId) : null;
        $email = mb_strtolower(trim((string) ($request['client_email'] ?? $cliente?->email ?? '')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Falta um e-mail válido. Peça o e-mail ao cliente e chame esta ferramenta de novo.';
        }

        $pack = Pack::forTenant($this->tenantId)
            ->where('status', 'active')
            ->with(['addons' => fn ($query) => $query->forTenant($this->tenantId)])
            ->find((int) ($request['pack_id'] ?? 0));

        if (! $pack) {
            return 'Pacote não encontrado ou inativo. Consulte ListTenantPacksTool e use um id retornado por ela.';
        }

        if ($pack->billing_type !== 'one_time' || $pack->billing_mode === 'pilot_entry') {
            return 'Este pacote tem condição de cobrança própria (assinatura ou piloto) e não vira proposta automática. Diga ao cliente que a equipe retorna para fechar.';
        }

        $extras = [['nome' => (string) $pack->translated_name, 'valor' => (float) $pack->price]];
        $unknown = [];
        $available = collect($pack->effective_addons);
        foreach ((array) ($request['addon_names'] ?? []) as $wanted) {
            $addon = $available->first(fn (array $a) => mb_strtolower((string) ($a['name'] ?? '')) === mb_strtolower(trim((string) $wanted)));

            if ($addon) {
                $extras[] = ['nome' => (string) $addon['name'], 'valor' => (float) ($addon['price'] ?? 0)];
            } else {
                $unknown[] = (string) $wanted;
            }
        }

        if ($unknown !== []) {
            return 'Adicional não existe neste pacote: '.implode(', ', $unknown).'. Adicionais válidos: '.($available->pluck('name')->implode(', ') ?: 'nenhum').'.';
        }

        if ($this->recentRequests() >= self::DAILY_LIMIT) {
            return 'Limite de propostas por dia atingido para este contato. Diga ao cliente que a equipe retorna com a proposta.';
        }

        $calculator = app(QuoteCalculatorService::class);
        $defaults = $calculator->getDefaults();
        $calc = $calculator->calculate([
            'videos' => max(1, (int) $pack->video_quantity),
            'filmmakers' => 1,
            'iva' => (float) $pack->vat_rate,
            'cambios' => $defaults['cambios'],
            'prazo' => $defaults['prazo'],
            'extras' => $extras,
            'redes' => $pack->social_networks_limit,
            'envios_mes' => $pack->monthly_post_limit,
        ]);

        // Pagamento como o pacote define: integral, ou sinal na porcentagem dele (a calculadora fixa 50%).
        $calc['sinal'] = $pack->billing_mode === 'split_50_50'
            ? round($calc['total_com_iva'] * max(10, min(90, (int) $pack->deposit_percentage)) / 100, 2)
            : $calc['total_com_iva'];
        $calc['na_entrega'] = round($calc['total_com_iva'] - $calc['sinal'], 2);

        $quote = $this->reusableQuote($email, $pack, $calc['total_com_iva']) ?? $this->createQuote($email, $cliente, $pack, $calc, $defaults);

        $url = app(SofiQuoteNotificationService::class)->getPublicApprovalUrl($quote);
        $money = fn (float $value): string => number_format($value, 2, ',', '.').' €';

        return "Proposta {$quote->number} pronta: {$pack->translated_name}, {$calc['inputs_sanitized']['videos']} vídeos. "
            .'Total '.$money($quote->total_amount).' com IVA ('.$money($quote->subtotal).' + IVA). '
            .($quote->balance_amount > 0
                ? 'Sinal de '.$money($quote->deposit_amount).' e o restante na entrega. '
                : 'Pagamento integral na aprovação. ')
            .'Válida até '.$quote->valid_until->format('d/m/Y').". Link de aprovação e pagamento: {$url} "
            .'Envie ao cliente este link exatamente como está e o total acima, sem alterar valores. Explique que, ao abrir, ele informa os dados de faturação e escolhe a forma de pagamento.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pack_id' => $schema->integer()->description('Id do pacote devolvido por ListTenantPacksTool.')->required(),
            'client_email' => $schema->string()->description('E-mail do cliente, para a proposta e o pagamento. Obrigatório, exceto para cliente já cadastrado.'),
            'addon_names' => $schema->array()->items($schema->string())->description('Nomes exatos dos adicionais do pacote que o cliente quer, se houver.'),
        ];
    }

    private function recentRequests(): int
    {
        if (blank($this->phone)) {
            return 0;
        }

        return Quote::forTenant($this->tenantId)
            ->where('request_details->source', 'whatsapp')
            ->where('request_details->phone', $this->phone)
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    /**
     * Mesma proposta (pacote, e-mail e total) ainda válida: devolve o mesmo link em vez de duplicar.
     */
    private function reusableQuote(string $email, Pack $pack, float $total): ?Quote
    {
        return Quote::forTenant($this->tenantId)
            ->where('client_email', $email)
            ->where('status', 'sent')
            ->where('request_details->source', 'whatsapp')
            ->where('request_details->pack_id', $pack->id)
            ->where('total_amount', $total)
            ->whereDate('valid_until', '>=', now()->toDateString())
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $calc
     * @param  array<string, mixed>  $defaults
     */
    private function createQuote(string $email, ?Cliente $cliente, Pack $pack, array $calc, array $defaults): Quote
    {
        // O lead do WhatsApp recebe o e-mail antes: o CRM (LeadAutomation) acha o lead por ele
        // e move a mesma ficha para "proposta", em vez de abrir uma segunda.
        $lead = $this->leadId ? Lead::forTenant($this->tenantId)->whereNull('anonymized_at')->find($this->leadId) : null;
        if ($lead && blank($lead->email)) {
            $lead->forceFill(['email' => $email])->save();
        }

        $quote = Quote::create([
            'tenant_id' => $this->tenantId,
            'cliente_id' => $cliente?->id,
            'client_name' => $cliente?->company_name ?: ($cliente?->name ?? $lead?->name ?? 'Cliente'),
            'client_contact' => $cliente?->name ?? $lead?->name,
            'client_email' => $email,
            'project_title' => (string) $pack->translated_name,
            'language' => $this->isSpanish ? 'es' : 'pt_BR',
            'request_text' => 'Proposta gerada pela Sofi no WhatsApp.',
            'request_details' => ['source' => 'whatsapp', 'pack_id' => $pack->id, 'phone' => $this->phone],
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

        if ($lead) {
            app(LeadService::class)->log(
                $lead,
                'whatsapp',
                "Proposta {$quote->number} ({$pack->translated_name}) enviada pela Sofi no WhatsApp.",
                null,
                ['quote_id' => $quote->id, 'channel' => 'whatsapp'],
            );
        }

        return $quote;
    }
}
