<?php

namespace App\Console\Commands;

use App\Models\Roteiro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPendingApprovalsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'approvals:check-pending {--hours=48 : Horas mínimas pendente para disparar lembrete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica roteiros pendentes de aprovação pelo cliente há mais de X horas e dispara follow-up automático.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $threshold = now()->subHours($hours);

        $this->info("Verificando roteiros submetidos antes de {$threshold->toDateTimeString()} ({$hours}h atrasados)...");

        // Busca roteiros submitted com mais de X horas e sem lembrete nas últimas 24 horas
        $pendingRoteiros = Roteiro::with(['production.cliente', 'production.tenant'])
            ->where('status', 'submitted')
            ->where('submitted_at', '<=', $threshold)
            ->where(function ($q) {
                $q->whereNull('last_reminded_at')
                  ->orWhere('last_reminded_at', '<=', now()->subHours(24));
            })
            ->get();

        if ($pendingRoteiros->isEmpty()) {
            $this->comment('Nenhum roteiro com aprovação pendente em atraso.');
            return Command::SUCCESS;
        }

        $notifiedCount = 0;

        foreach ($pendingRoteiros as $roteiro) {
            $cliente = $roteiro->production?->cliente;
            $production = $roteiro->production;
            $magicUrl = $roteiro->getMagicApprovalUrl(7);

            // Registra notificação e atualiza metadados
            $roteiro->update([
                'last_reminded_at' => now(),
                'reminder_count' => $roteiro->reminder_count + 1,
            ]);

            Log::channel('daily')->info("CheckPendingApprovals: Lembrete automático enviado para Roteiro #{$roteiro->id} do cliente " . ($cliente?->name ?? 'N/A'), [
                'roteiro_id' => $roteiro->id,
                'production_title' => $production?->title,
                'cliente_id' => $cliente?->id,
                'reminder_count' => $roteiro->reminder_count,
                'magic_url' => $magicUrl,
            ]);

            $notifiedCount++;
            $this->line("• [Roteiro #{$roteiro->id}] {$production?->title} - Cliente: {$cliente?->name} (Lembrete #{$roteiro->reminder_count})");
        }

        $this->info("Processamento concluído: {$notifiedCount} follow-ups de aprovação registrados com sucesso.");

        return Command::SUCCESS;
    }
}
