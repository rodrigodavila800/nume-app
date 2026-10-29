<?php

namespace App\Services\Crm;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\Ai\SofiMemoryDistillationService;
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
        $rawPhone = $attributes['phone'] ?? $cliente?->phone;
        $cleanPhone = $rawPhone ? preg_replace('/\D/', '', (string) $rawPhone) : null;
        $phoneSuffix = ($cleanPhone && strlen($cleanPhone) >= 8) ? substr($cleanPhone, -8) : null;
        $hyphenPattern = $phoneSuffix ? substr($phoneSuffix, 0, 4).'-'.substr($phoneSuffix, 4) : null;

        $lead = Lead::forTenant($tenantId)
            ->whereNull('anonymized_at')
            ->where(function ($q) use ($email, $cliente, $cleanPhone, $phoneSuffix, $hyphenPattern) {
                $q->when($cliente, fn ($q) => $q->orWhere('cliente_id', $cliente->id))
                    ->when($email, fn ($q) => $q->orWhere('email', $email))
                    ->when($cleanPhone, fn ($q) => $q->orWhere('phone', 'like', "%{$cleanPhone}%"))
                    ->when($phoneSuffix, fn ($q) => $q->orWhere('phone', 'like', "%{$phoneSuffix}%"))
                    ->when($hyphenPattern, fn ($q) => $q->orWhere('phone', 'like', "%{$hyphenPattern}%"));
            })
            ->when(! $cliente && ! $email && ! $cleanPhone, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByRaw("stage in ('ganho','perdido')")
            ->latest('id')
            ->first();

        // Se não localizou por SQL direto mas temos telefone, faz busca resiliente a formatação (ex: (91) 8258-6886 vs 559182586886)
        if (! $lead && $phoneSuffix) {
            $candidates = Lead::forTenant($tenantId)
                ->whereNull('anonymized_at')
                ->whereNotNull('phone')
                ->whereIn('stage', Lead::OPEN_STAGES)
                ->latest('id')
                ->get();

            foreach ($candidates as $candidate) {
                $candidateClean = preg_replace('/\D/', '', (string) $candidate->phone);
                if (
                    $candidateClean === $cleanPhone ||
                    ($candidateClean && str_ends_with($candidateClean, $phoneSuffix)) ||
                    ($cleanPhone && str_ends_with($cleanPhone, $candidateClean))
                ) {
                    $lead = $candidate;
                    break;
                }
            }
        }

        if ($lead) {
            $updates = [];
            if ($cliente && ! $lead->cliente_id) {
                $updates['cliente_id'] = $cliente->id;
            }
            if ($cliente && ($lead->name === 'Sem nome' || str_starts_with($lead->name, 'Contato WhatsApp'))) {
                $updates['name'] = $cliente->name;
            } elseif (! empty($attributes['name']) && (str_starts_with($lead->name, 'Contato WhatsApp') || $lead->name === 'Sem nome') && ! str_starts_with($attributes['name'], 'Contato WhatsApp')) {
                $updates['name'] = $attributes['name'];
            }
            if ($cliente?->company_name && empty($lead->company)) {
                $updates['company'] = $cliente->company_name;
            }
            if ($cliente?->email && empty($lead->email)) {
                $updates['email'] = $cliente->email;
            }
            if (! empty($updates)) {
                $lead->forceFill($updates)->save();
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

        if ($stage === 'perdido' && ($reason || $lead->lost_reason)) {
            try {
                app(SofiMemoryDistillationService::class)->distillFromLeadLost($lead, $reason ?: $lead->lost_reason);
            } catch (\Throwable) {
                // Fallback silencioso para não impactar o fluxo de CRM
            }
        }

        $labels = Lead::stageLabels();
        $this->log($lead, 'stage_change', __(':from → :to', ['from' => $labels[$from] ?? $from, 'to' => $labels[$stage] ?? $stage])
            .($reason ? ' · '.$reason : ''), $user, ['from' => $from, 'to' => $stage, 'automatic' => $automatic]);
    }

    /**
     * Dados pessoais do lead para entregar ao titular (direito de acesso/portabilidade, RGPD).
     */
    public function export(Lead $lead): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'lead' => $lead->only(['id', 'name', 'company', 'email', 'phone', 'source', 'stage', 'estimated_value', 'notes', 'legal_basis', 'created_at']) + [
                'consent_at' => $lead->consent_at?->toIso8601String(),
            ],
            'history' => $lead->activities()->get(['type', 'body', 'created_at'])
                ->map(fn ($a) => ['type' => $a->type, 'body' => $a->body, 'at' => $a->created_at?->toIso8601String()])
                ->all(),
        ];
    }

    /**
     * Apaga os dados pessoais e mantém só o que serve à estatística do funil (etapa, origem, valor).
     */
    public function anonymize(Lead $lead, ?User $user = null): void
    {
        if ($lead->anonymized_at !== null) {
            return;
        }

        $lead->forceFill([
            'name' => __('Anonimizado'),
            'company' => null,
            'email' => null,
            'phone' => null,
            'notes' => null,
            'lost_reason' => null,
            'consent_at' => null,
            'cliente_id' => null,
            'anonymized_at' => now(),
        ])->save();

        LeadActivity::forTenant($lead->tenant_id)->where('lead_id', $lead->id)->update(['body' => '[anonimizado]', 'meta' => null]);

        $this->log($lead, 'system', __('Dados pessoais anonimizados (RGPD).'), $user);
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
