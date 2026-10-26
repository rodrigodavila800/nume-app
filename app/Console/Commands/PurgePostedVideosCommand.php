<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\Video\VideoUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Decisão de negócio (26/10/2026): o arquivo final não fica na hospedagem depois de postado.
 *
 * Só apaga quando: o vídeo tem arquivo próprio, há link de entrega (a cópia do cliente fica no
 * Drive), pelo menos uma rede publicou, nenhuma publicação está pendente/agendada/em andamento e a
 * última atividade passou de social.purge_after_hours. Sem usuário logado: tudo por tenant explícito.
 */
class PurgePostedVideosCommand extends Command
{
    protected $signature = 'videos:purge-posted {--dry-run : Só lista o que seria apagado}';

    protected $description = 'Apaga da hospedagem o arquivo dos vídeos já postados (com link de entrega)';

    public function handle(): int
    {
        $hours = max(1, (int) config('social.purge_after_hours', 24));
        $cutoff = now()->subHours($hours);
        $purged = 0;
        $waitingDelivery = 0;

        Video::withoutGlobalScopes()
            ->whereNotNull('source_path')
            ->whereNotNull('source_disk')
            ->whereNull('source_purged_at')
            ->whereHas('publications', fn ($q) => $q->withoutGlobalScopes()->whereColumn('video_publications.tenant_id', 'videos.tenant_id')->where('status', 'publicado'))
            ->orderBy('id')
            ->chunkById(100, function ($videos) use ($cutoff, &$purged, &$waitingDelivery) {
                foreach ($videos as $video) {
                    $publications = VideoPublication::forTenant($video->tenant_id)->where('video_id', $video->id);

                    if ((clone $publications)->whereIn('status', ['pendente', 'agendado', 'processando'])->exists()) {
                        continue;
                    }

                    if ((clone $publications)->where('updated_at', '>', $cutoff)->exists()) {
                        continue;
                    }

                    if (blank($video->delivery_url)) {
                        $waitingDelivery++;

                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("video #{$video->id} (tenant {$video->tenant_id})");
                        $purged++;

                        continue;
                    }

                    $video->deleteSourceFile();
                    $video->forceFill([...VideoUploadService::clearedAttributes(), 'source_purged_at' => now()])->save();
                    $purged++;

                    Log::info('videos.source_purged', ['tenant_id' => $video->tenant_id, 'video_id' => $video->id]);
                }
            });

        $this->info(($this->option('dry-run') ? 'Seriam apagados: ' : 'Arquivos apagados: ').$purged);
        if ($waitingDelivery > 0) {
            $this->warn("Postados sem link de entrega (mantidos): {$waitingDelivery}");
        }

        return self::SUCCESS;
    }
}
