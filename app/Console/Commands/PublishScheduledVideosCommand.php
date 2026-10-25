<?php

namespace App\Console\Commands;

use App\Contracts\SocialPublisherContract;
use App\Services\SocialPublishing\SocialPublicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledVideosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'videos:publish-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispara publicações de vídeos sociais que foram agendadas previamente e atingiram o horário programado.';

    /**
     * Execute the console command.
     */
    public function handle(SocialPublisherContract $publisher, SocialPublicationService $native): int
    {
        $this->info('Iniciando verificação de publicações sociais agendadas...');

        try {
            $processed = $native->dispatchDueNative() + $publisher->processScheduled();

            if ($processed > 0) {
                $this->info("{$processed} publicações agendadas foram disparadas com sucesso.");
                Log::info("PublishScheduledVideosCommand: {$processed} publicações disparadas.");
            } else {
                $this->comment('Nenhuma publicação agendada pendente no momento.');
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro ao processar publicações agendadas: ' . $e->getMessage());
            Log::error('PublishScheduledVideosCommand Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
