<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\Crm\LeadService;
use Illuminate\Console\Command;

/**
 * RGPD (limitação do prazo de conservação): lead perdido há mais de crm.anonymize_lost_after_months
 * meses perde os dados pessoais. Roda sem usuário logado, então ignora o scope de tenant.
 */
class AnonymizeLostLeadsCommand extends Command
{
    protected $signature = 'crm:anonymize-lost';

    protected $description = 'Anonimiza leads perdidos além do prazo de conservação (RGPD)';

    public function handle(LeadService $leads): int
    {
        $cutoff = now()->subMonths(max(1, (int) config('crm.anonymize_lost_after_months', 24)));
        $count = 0;

        Lead::withoutGlobalScope('tenant')
            ->where('stage', 'perdido')
            ->whereNull('anonymized_at')
            ->where(fn ($q) => $q->where('stage_changed_at', '<', $cutoff)->orWhere(fn ($q) => $q->whereNull('stage_changed_at')->where('updated_at', '<', $cutoff)))
            ->chunkById(100, function ($batch) use ($leads, &$count) {
                foreach ($batch as $lead) {
                    $leads->anonymize($lead);
                    $count++;
                }
            });

        $this->info("Leads anonimizados: {$count}");

        return self::SUCCESS;
    }
}
