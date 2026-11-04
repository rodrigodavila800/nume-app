<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ClosesWhatsAppSale;
use App\Jobs\SendQuoteProposalDocuments;
use App\Models\Cliente;
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
 * passa pela QuoteCalculatorService e o fechamento é do cliente, na página pública /orcamento/{token}:
 * conta no portal, faturação, forma de pagamento e confirmação só pelo gateway (PackPaymentConfirmer),
 * fora do alcance da Sofi. Não cobra nem marca nada como pago, por isso não pede aprovação humana.
 *
 * Exige os dados de fechamento (responsável, empresa e e-mail; nicho quando houver), que vão para a
 * proposta e para a ficha do CRM. Plano de assinatura vira proposta do primeiro período (os seguintes
 * são faturados pela equipe; sem cobrança recorrente automática). Pacote piloto fica com a equipe.
 * Limite diário por telefone e reaproveitamento da proposta igual ainda válida evitam spam e duplicata.
 */
class CreateQuoteFromPackTool implements Tool
{
    use ClosesWhatsAppSale;

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
        return 'Cria a proposta oficial de um pacote do catálogo e devolve o link seguro de aprovação e pagamento para enviar ao cliente. Use quando o cliente aceitar o pacote. Exige nome do responsável, nome da empresa e e-mail (e o nicho, se souber). Não informe preço: ele vem da proposta.';
    }

    public function handle(Request $request): Stringable|string
    {
        ['cliente' => $cliente, 'email' => $email, 'responsible' => $responsible, 'company' => $company, 'niche' => $niche, 'missing' => $missing] = $this->closingData($request);

        if ($missing !== []) {
            return 'Faltam dados para a proposta formal: '.implode(', ', $missing).'. Peça ao cliente numa mensagem só e chame esta ferramenta de novo.';
        }

        $pack = Pack::forTenant($this->tenantId)
            ->where('status', 'active')
            ->with(['addons' => fn ($query) => $query->forTenant($this->tenantId)])
            ->find((int) ($request['pack_id'] ?? 0));

        if (! $pack) {
            return 'Pacote não encontrado ou inativo. Consulte ListTenantPacksTool e use um id retornado por ela.';
        }

        if ($pack->billing_mode === 'pilot_entry') {
            return 'Este pacote piloto tem condição de entrada própria e não vira proposta automática. Chame EscalateToTeamTool e diga ao cliente que a equipe retorna para fechar.';
        }

        $isSubscription = $pack->billing_type === 'subscription';
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
        // Assinatura: a aprovação cobra o primeiro período inteiro.
        $calc['sinal'] = ! $isSubscription && $pack->billing_mode === 'split_50_50'
            ? round($calc['total_com_iva'] * max(10, min(90, (int) $pack->deposit_percentage)) / 100, 2)
            : $calc['total_com_iva'];
        $calc['na_entrega'] = round($calc['total_com_iva'] - $calc['sinal'], 2);

        $plan = $isSubscription ? [
            'interval' => $pack->billing_interval === 'quarter' ? 'quarter' : 'month',
            'commitment_months' => max(1, (int) ($pack->commitment_months ?: 3)),
        ] : null;

        $closing = ['responsible' => $responsible, 'company' => $company, 'niche' => $niche];

        $quote = $this->reusableQuote($email, $pack, $calc['total_com_iva']);
        $isNew = $quote === null;
        $quote ??= $this->createQuote($email, $cliente, $pack, $calc, $defaults, $closing, $plan);

        // O link com token vai só no e-mail (botão de aprovação); no WhatsApp, só os endereços oficiais do portal.
        app(SofiQuoteNotificationService::class)->ensureApprovalToken($quote);

        // PDF formal por e-mail e por este WhatsApp: proposta nova, ou reaproveitada que ainda não teve o PDF enviado.
        $sendDocuments = $isNew || empty($quote->request_details['documents_sent_at']);
        if ($sendDocuments) {
            SendQuoteProposalDocuments::dispatch($quote->id, $this->tenantId, $this->phone);
        }
        $money = fn (float $value): string => number_format($value, 2, ',', '.').' €';

        $payment = match (true) {
            $plan !== null => 'Plano de assinatura: '.$money($quote->subtotal).' + IVA por '.($plan['interval'] === 'quarter' ? 'trimestre' : 'mês')
                .", contrato de {$plan['commitment_months']} meses. A aprovação cobra o primeiro período (".$money($quote->total_amount).' com IVA); os seguintes são faturados pela equipe. ',
            $quote->balance_amount > 0 => 'Total '.$money($quote->total_amount).' com IVA ('.$money($quote->subtotal).' + IVA). Sinal de '.$money($quote->deposit_amount).' e o restante na entrega. ',
            default => 'Total '.$money($quote->total_amount).' com IVA ('.$money($quote->subtotal).' + IVA). Pagamento integral na aprovação. ',
        };

        return "Proposta {$quote->number} pronta para {$company} ({$responsible}): {$pack->translated_name}, {$calc['inputs_sanitized']['videos']} vídeos. "
            .$payment
            .'Válida até '.$quote->valid_until->format('d/m/Y').'. '
            .($sendDocuments ? 'O PDF formal da proposta chega em seguida por e-mail e por este WhatsApp. ' : 'O PDF desta proposta já foi enviado antes por e-mail e por este WhatsApp; se o cliente pedir de novo, use ResendProposalPdfTool. ')
            .$this->accountAndPaymentText($quote, $email, $pack);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pack_id' => $schema->integer()->description('Id do pacote devolvido por ListTenantPacksTool.')->required(),
            'client_email' => $schema->string()->description('E-mail do cliente, para a proposta, a conta no portal e o pagamento.'),
            'responsible_name' => $schema->string()->description('Nome da pessoa responsável pela contratação.'),
            'company_name' => $schema->string()->description('Nome da empresa ou marca do cliente.'),
            'niche' => $schema->string()->description('Nicho ou segmento de atuação da empresa (ex.: odontologia, restaurante, moda).'),
            'addon_names' => $schema->array()->items($schema->string())->description('Nomes exatos dos adicionais do pacote que o cliente quer, se houver.'),
        ];
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
     * @param  array{responsible: string, company: string, niche: string}  $closing
     * @param  array{interval: string, commitment_months: int}|null  $plan
     */
    private function createQuote(string $email, ?Cliente $cliente, Pack $pack, array $calc, array $defaults, array $closing, ?array $plan): Quote
    {
        $lead = $this->syncLead($email, $closing);

        $title = (string) $pack->translated_name;
        if ($plan !== null) {
            $title .= $plan['interval'] === 'quarter'
                ? " (trimestral, contrato de {$plan['commitment_months']} meses)"
                : " (mensal, contrato de {$plan['commitment_months']} meses)";
        }

        $quote = Quote::create([
            'tenant_id' => $this->tenantId,
            'cliente_id' => $cliente?->id,
            'client_name' => $closing['company'],
            'client_contact' => $closing['responsible'],
            'client_email' => $email,
            'project_title' => $title,
            'language' => $this->isSpanish ? 'es' : 'pt_BR',
            'request_text' => 'Proposta gerada pela Sofi no WhatsApp.',
            'request_details' => array_filter([
                'source' => 'whatsapp',
                'pack_id' => $pack->id,
                'phone' => $this->phone,
                'niche' => $closing['niche'] ?: null,
                'subscription' => $plan,
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

        if ($lead) {
            app(LeadService::class)->log(
                $lead,
                'whatsapp',
                "Proposta {$quote->number} ({$title}) enviada pela Sofi no WhatsApp."
                ."\n• Responsável: {$closing['responsible']}\n• Empresa: {$closing['company']}"
                .($closing['niche'] !== '' ? "\n• Nicho: {$closing['niche']}" : ''),
                null,
                ['quote_id' => $quote->id, 'channel' => 'whatsapp', 'niche' => $closing['niche']],
            );
        }

        return $quote;
    }
}
