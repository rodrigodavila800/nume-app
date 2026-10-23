<?php

namespace App\Console\Commands;

use App\Models\PackPurchase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpirePendingBankTransfersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packs:expire-bank-transfers {--tenant= : ID do tenant específico (opcional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expira solicitações de pagamento via transferência bancária pendentes após o prazo limite.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando verificação de transferências bancárias expiradas...');

        // Convenção 1 do AGENTS.md: comandos de scheduler/console rodam sem usuário logado,
        // portanto consultas devem passar tenant_id explicitamente ou processar por tenant.
        $query = PackPurchase::withoutGlobalScope('tenant')
            ->where('status', 'pending')
            ->where('payment_gateway', 'bank_transfer')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        if ($tenantId = $this->option('tenant')) {
            $query->where('tenant_id', $tenantId);
        }

        $purchases = $query->get();
        $expiredCount = 0;

        foreach ($purchases as $purchase) {
            DB::transaction(function () use ($purchase, &$expiredCount) {
                // Passa o tenant_id explicitamente no lockForUpdate
                $locked = PackPurchase::withoutGlobalScope('tenant')
                    ->where('tenant_id', $purchase->tenant_id)
                    ->lockForUpdate()
                    ->find($purchase->id);

                if ($locked && $locked->status === 'pending' && $locked->expires_at && $locked->expires_at->isPast()) {
                    $locked->update(['status' => 'expired']);
                    $expiredCount++;

                    Log::info("ExpirePendingBankTransfers: Compra #{$locked->id} do tenant #{$locked->tenant_id} expirada por decurso de prazo.");
                }
            });
        }

        $this->info("Verificação concluída. Total de compras expiradas: {$expiredCount}.");

        return self::SUCCESS;
    }
}
