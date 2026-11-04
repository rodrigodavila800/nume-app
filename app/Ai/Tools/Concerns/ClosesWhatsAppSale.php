<?php

namespace App\Ai\Tools\Concerns;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Pack;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\Payment\PaymentMethodAvailability;
use App\Services\Quotes\QuoteOwnership;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;

/**
 * Fechamento de venda pela Sofi no WhatsApp, comum às ferramentas de proposta (pacote pronto e pacote
 * montado): dados do cliente, limite diário por telefone, ficha do CRM e o texto de conta e pagamento.
 *
 * Quem usa precisa das propriedades $tenantId, $clienteId, $phone, $leadId e $isSpanish.
 */
trait ClosesWhatsAppSale
{
    /** Valores que a IA usa para "preencher" um dado que o cliente não deu (comparados sem acento). */
    public const PLACEHOLDERS = [
        'nao informada', 'nao informado', 'nao informou', 'nao sei', 'n a', 'na', 'nd', 'none', 'null', 'nenhum', 'nenhuma',
        'sem empresa', 'sem nome', 'desconhecido', 'desconhecida', 'a definir', 'indefinido', 'pendente', 'cliente', 'empresa',
        'responsavel', 'no informado', 'no informada', 'sin empresa', 'sin nombre', 'desconocido', 'desconocida', 'unknown',
    ];

    /**
     * Dados de fechamento. Cliente reconhecido pelo telefone preenche o que faltar; sem ele, uma conta
     * achada pelo e-mail passa a ser a dona da proposta.
     *
     * @return array{cliente: ?Cliente, email: string, responsible: string, company: string, niche: string, missing: list<string>}
     */
    protected function closingData(Request $request): array
    {
        $cliente = $this->clienteId ? Cliente::forTenant($this->tenantId)->find($this->clienteId) : null;
        $email = mb_strtolower(trim((string) ($request['client_email'] ?? $cliente?->email ?? '')));
        $responsible = $this->clean($request['responsible_name'] ?? $cliente?->name);
        $company = $this->clean($request['company_name'] ?? $cliente?->company_name);
        $niche = $this->clean($request['niche'] ?? $cliente?->brand_niche);
        $validEmail = (bool) filter_var($email, FILTER_VALIDATE_EMAIL);

        $cliente ??= $validEmail ? QuoteOwnership::findClienteByEmail($this->tenantId, $email) : null;

        return [
            'cliente' => $cliente,
            'email' => $email,
            'responsible' => $responsible,
            'company' => $company,
            'niche' => $niche,
            'missing' => array_keys(array_filter([
                'e-mail válido' => ! $validEmail,
                'nome do responsável' => $responsible === '',
                'nome da empresa' => $company === '',
            ])),
        ];
    }

    /**
     * Texto limpo do cliente. Valor de enchimento ("não informada", "n/a", "-") conta como vazio: em
     * produção a IA preencheu a empresa obrigatória com "Não informada" em vez de perguntar, e o PDF
     * saiu com um cliente que parecia ser outro.
     */
    protected function clean(mixed $value): string
    {
        $text = mb_substr(trim(strip_tags((string) $value)), 0, 255);
        $normalized = trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($text))));

        return mb_strlen($normalized) < 2 || in_array($normalized, self::PLACEHOLDERS, true) ? '' : $text;
    }

    /** Propostas criadas pela Sofi para este telefone nas últimas 24 h (limite contra spam). */
    protected function recentRequests(): int
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
     * O lead do WhatsApp recebe os dados de fechamento antes da proposta: o CRM (LeadAutomation) acha o
     * lead pelo e-mail e move a mesma ficha para "proposta", em vez de abrir uma segunda.
     *
     * @param  array{responsible: string, company: string}  $closing
     */
    protected function syncLead(string $email, array $closing): ?Lead
    {
        $lead = $this->leadId ? Lead::forTenant($this->tenantId)->whereNull('anonymized_at')->find($this->leadId) : null;

        if ($lead) {
            $lead->forceFill(array_filter([
                'email' => blank($lead->email) ? $email : null,
                'company' => blank($lead->company) ? $closing['company'] : null,
                'name' => blank($lead->name) || str_starts_with((string) $lead->name, 'Contato WhatsApp') ? $closing['responsible'] : null,
            ]))->save();
        }

        return $lead;
    }

    /** Endereço oficial do portal (cadastro ou login), formas de pagamento ativas e a instrução de repasse. */
    protected function accountAndPaymentText(Quote $quote, string $email, ?Pack $pack = null): string
    {
        $methods = PaymentMethodAvailability::forTenant(Tenant::findOrFail($this->tenantId))->activeMethodLabels($pack, $this->isSpanish);
        $account = $quote->cliente_id
            ? 'O cliente JÁ TEM CONTA: ele entra em '.route('portal.login')." com o e-mail {$email}; a proposta está em Orçamentos para aprovar e pagar. "
            : 'O cliente AINDA NÃO TEM CONTA: para seguir, ele faz o cadastro em '.route('portal.register')." com o mesmo e-mail ({$email}); a proposta aparece em Orçamentos para aprovar e pagar. ";

        return $account
            .'Formas de pagamento disponíveis: '.($methods === [] ? 'a equipe informa' : implode(', ', $methods)).'. '
            .'Repasse ao cliente os valores, o endereço do portal e as formas de pagamento exatamente como aqui, sem alterar nada e sem citar outros links ou formas de pagamento.';
    }
}
