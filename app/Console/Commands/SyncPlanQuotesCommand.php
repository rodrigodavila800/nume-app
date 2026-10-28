<?php

namespace App\Console\Commands;

use App\Models\MarketingPlan;
use App\Models\Quote;
use App\Services\Quotes\QuoteRequestService;
use Illuminate\Console\Command;

class SyncPlanQuotesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nume:sync-plan-quotes {--tenant= : ID do tenant específico (opcional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera a solicitação de orçamento (status requested) para planos de marketing aprovados que ainda não possuem orçamento ou pacote associado.';

    /**
     * Execute the console command.
     */
    public function handle(QuoteRequestService $quoteRequests): int
    {
        $this->info('Iniciando sincronização de orçamentos para planos de marketing aprovados...');

        $query = MarketingPlan::withoutGlobalScope('tenant')
            ->where('status', MarketingPlan::STATUS_APPROVED)
            ->with(['cliente' => fn ($q) => $q->withoutGlobalScope('tenant')]);

        if ($tenantId = $this->option('tenant')) {
            $query->where('tenant_id', $tenantId);
        }

        $plans = $query->get();
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($plans as $plan) {
            $cliente = $plan->cliente;
            if (! $cliente) {
                $this->warn("Plano #{$plan->id} sem cliente vinculado. Pulando.");
                $skippedCount++;

                continue;
            }

            // Verifica se já tem orçamento vinculado a este plano
            $existingQuote = Quote::withoutGlobalScope('tenant')
                ->where('tenant_id', $plan->tenant_id)
                ->where('marketing_plan_id', $plan->id)
                ->first();

            if ($existingQuote) {
                $this->line("Plano #{$plan->id} ({$cliente->name}) já possui orçamento #{$existingQuote->number} (Status: {$existingQuote->status}).");
                $skippedCount++;

                continue;
            }

            // Verifica se o cliente já possui pacote pago ativo
            $hasActivePack = $cliente->packPurchases()
                ->withoutGlobalScope('tenant')
                ->whereIn('status', ['paid', 'active'])
                ->whereColumn('videos_used', '<', 'video_quantity')
                ->exists();

            if ($hasActivePack) {
                $this->line("Plano #{$plan->id} ({$cliente->name}) já está coberto por pacote de vídeos ativo.");
                $skippedCount++;

                continue;
            }

            // Gera o orçamento com status requested
            $quote = $quoteRequests->ensureRequestForPlan($cliente, $plan);

            if ($quote) {
                $createdCount++;
                $this->info("✓ Orçamento #{$quote->number} gerado em 'requested' para o Plano #{$plan->id} ({$cliente->name}) - Subtotal: € ".number_format((float) $quote->subtotal, 2, ',', '.'));
            }
        }

        $this->newLine();
        $this->info("Sincronização concluída! {$createdCount} orçamento(s) gerado(s), {$skippedCount} plano(s) mantido(s).");

        return self::SUCCESS;
    }
}
