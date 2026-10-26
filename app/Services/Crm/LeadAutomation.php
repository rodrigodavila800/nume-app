<?php

namespace App\Services\Crm;

use App\Models\Cliente;
use App\Models\Quote;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Regras automáticas do funil. Nunca derrubam o fluxo que as chamou (cadastro, orçamento):
 * qualquer falha só é registrada.
 *
 * - cadastro de cliente (portal, Google, vitrine, equipe) → lead "novo" com a origem;
 * - pedido de orçamento no portal → "qualificado";
 * - proposta enviada → "proposta"; aceita → "ganho"; recusada/expirada → "perdido".
 */
class LeadAutomation
{
    private const QUOTE_STAGE = [
        Quote::STATUS_REQUESTED => 'qualificado',
        'sent' => 'proposta',
        'accepted' => 'ganho',
        'rejected' => 'perdido',
        'expired' => 'perdido',
    ];

    public function __construct(private readonly LeadService $leads) {}

    public function clienteRegistered(Cliente $cliente, string $source): void
    {
        $this->safely(function () use ($cliente, $source) {
            $this->leads->findOrCreate($cliente->tenant_id, [
                'name' => $cliente->name,
                'company' => $cliente->company_name,
                'email' => $cliente->email,
                'phone' => $cliente->phone,
                // Cadastro feito pela própria pessoa para contratar: medidas pré-contratuais.
                'legal_basis' => $source === 'manual' ? 'legitimate_interest' : 'contract',
            ], $source, $cliente);
        });
    }

    public function quoteChanged(Quote $quote): void
    {
        $stage = self::QUOTE_STAGE[$quote->status] ?? null;
        if ($stage === null) {
            return;
        }

        $this->safely(function () use ($quote, $stage) {
            $cliente = $quote->cliente_id
                ? Cliente::forTenant($quote->tenant_id)->find($quote->cliente_id)
                : null;

            if (! $cliente && blank($quote->client_email)) {
                return;
            }

            $lead = $this->leads->findOrCreate($quote->tenant_id, [
                'name' => $cliente?->name ?? $quote->client_contact ?? $quote->client_name,
                'company' => $cliente?->company_name ?? $quote->client_name,
                'email' => $cliente?->email ?? $quote->client_email,
                'legal_basis' => 'contract',
            ], $quote->isRequested() ? 'quote_request' : 'manual', $cliente);

            if ($quote->total_amount > 0 && ! $quote->isAds() && (! $lead->estimated_value || $quote->status === 'sent')) {
                $lead->forceFill(['estimated_value' => $quote->total_amount])->save();
            }

            $reason = in_array($quote->status, ['rejected', 'expired'], true)
                ? __('Orçamento :num :status', ['num' => $quote->number, 'status' => $quote->status === 'rejected' ? __('recusado') : __('expirado')])
                : null;

            $this->leads->moveTo($lead, $stage, null, $reason, automatic: true);
            $this->leads->log($lead, 'system', __('Orçamento :num: :status.', ['num' => $quote->number, 'status' => $quote->status]), null, ['quote_id' => $quote->id]);
        });
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning('crm.automation_failed', ['error' => get_class($e)]);
        }
    }
}
