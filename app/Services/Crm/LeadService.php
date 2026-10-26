<?php

namespace App\Services\Crm;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Regras do CRM: criar sem duplicar, mudar etapa com histórico e registrar contatos.
 * Tenant sempre explícito: também roda em fluxos públicos (cadastro, vitrine, link de orçamento).
 */
class LeadService
{
    /**
     * Lead aberto do mesmo e-mail (ou do cliente) no tenant; se não houver, cria.
     */
    public function findOrCreate(int $tenantId, array $attributes, string $source, ?Cliente $cliente = null): Lead
    {
        $email = filled($attributes['email'] ?? null) ? Str::lower(trim($attributes['email'])) : null;

        $lead = Lead::forTenant($tenantId)
            ->whereNull('anonymized_at')
            ->where(function ($q) use ($email, $cliente) {
                $q->when($cliente, fn ($q) => $q->orWhere('cliente_id', $cliente->id))
                    ->when($email, fn ($q) => $q->orWhere('email', $email));
            })
            ->when(! $cliente && ! $email, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByRaw("stage in ('ganho','perdido')")
            ->latest('id')
            ->first();

        if ($lead) {
            if ($cliente && ! $lead->cliente_id) {
                $lead->forceFill(['cliente_id' => $cliente->id])->save();
            }

            return $lead;
        }

        $lead = new Lead([
            'tenant_id' => $tenantId,
            'cliente_id' => $cliente?->id,
            'name' => $attributes['name'] ?? ($cliente?->name ?? __('Sem nome')),
            'company' => $attributes['company'] ?? $cliente?->company_name,
            'email' => $email ?? $cliente?->email,
            'phone' => $attributes['phone'] ?? $cliente?->phone,
            'source' => in_array($source, Lead::SOURCES, true) ? $source : 'manual',
            'legal_basis' => $attributes['legal_basis'] ?? 'legitimate_interest',
            'consent_at' => $attributes['consent_at'] ?? null,
        ]);
        $lead->forceFill(['tenant_id' => $tenantId, 'stage_changed_at' => now()])->save();

        $this->log($lead, 'system', __('Lead criado (origem: :source).', ['source' => Lead::sourceLabels()[$lead->source] ?? $lead->source]));

        return $lead;
    }

    /**
     * Muda a etapa e registra no histórico. Ganho/perdido são finais, salvo mudança manual.
     */
    public function moveTo(Lead $lead, string $stage, ?User $user = null, ?string $reason = null, bool $automatic = false): void
    {
        if (! in_array($stage, Lead::STAGES, true) || $lead->stage === $stage) {
            return;
        }

        // Regra automática nunca reabre nem volta etapa de lead já decidido.
        if ($automatic && ! $lead->isOpen()) {
            return;
        }

        $from = $lead->stage;
        $lead->forceFill([
            'stage' => $stage,
            'stage_changed_at' => now(),
            'lost_reason' => $stage === 'perdido' ? ($reason ? mb_substr($reason, 0, 255) : $lead->lost_reason) : null,
        ])->save();

        $labels = Lead::stageLabels();
        $this->log($lead, 'stage_change', __(':from → :to', ['from' => $labels[$from] ?? $from, 'to' => $labels[$stage] ?? $stage])
            .($reason ? ' · '.$reason : ''), $user, ['from' => $from, 'to' => $stage, 'automatic' => $automatic]);
    }

    public function log(Lead $lead, string $type, string $body, ?User $user = null, array $meta = []): LeadActivity
    {
        $activity = new LeadActivity([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'user_id' => $user?->id,
            'type' => in_array($type, LeadActivity::TYPES, true) ? $type : 'note',
            'body' => mb_substr($body, 0, 5000),
            'meta' => $meta ?: null,
        ]);
        $activity->forceFill(['tenant_id' => $lead->tenant_id])->save();

        if (in_array($activity->type, LeadActivity::CONTACT_TYPES, true)) {
            $lead->forceFill(['last_contact_at' => now()])->save();
        }

        return $activity;
    }
}
