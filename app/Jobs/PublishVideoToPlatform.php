<?php

namespace App\Jobs;

use App\Models\VideoPublication;
use App\Services\SocialPublishing\NativePublisherRegistry;
use App\Services\SocialPublishing\PublishException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publica um vídeo hospedado em UMA rede pelo app próprio da NUME (uma VideoPublication por rede).
 *
 * Roda sem usuário autenticado: o tenant vem no payload e toda consulta usa forTenant().
 * Idempotente: publicação já concluída, falha definitiva ou já com external_id não é reenviada.
 */
class PublishVideoToPlatform implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public bool $failOnTimeout = true;

    /** Definido no construtor: tempo do upload + folga (o worker usa este valor). */
    public int $timeout;

    public function __construct(public readonly int $publicationId, public readonly int $tenantId)
    {
        // Conexão própria (config/queue.php): retry_after maior que o envio, para não duplicar vídeo.
        $this->onConnection('social-uploads')->onQueue('social-uploads');
        $this->timeout = (int) config('social.youtube.upload_timeout', 3600) + 120;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(NativePublisherRegistry $publishers): void
    {
        $publication = VideoPublication::forTenant($this->tenantId)
            ->with(['video.production', 'socialConnection'])
            ->find($this->publicationId);

        $publisher = $publication ? $publishers->for((string) $publication->driver) : null;

        if (! $publication || ! $publisher || ! in_array($publication->status, ['pendente', 'processando'], true)) {
            return;
        }

        if ($publication->external_id) {
            $publication->update(['status' => 'publicado', 'publicado_em' => $publication->publicado_em ?? now()]);

            return;
        }

        $publication->forceFill(['status' => 'processando', 'attempts' => $publication->attempts + 1])->save();

        try {
            $result = $publisher->publish($publication);
        } catch (PublishException $e) {
            $this->log($publication, $e->reason, $e->retryable);

            if ($e->retryable && $this->attempts() < $this->tries) {
                $publication->update(['last_error' => $e->reason]);
                $this->release($this->backoff()[$this->attempts() - 1] ?? 900);

                return;
            }

            $this->markFailed($publication, $e->reason);

            return;
        }

        $publication->update([
            'status' => 'publicado',
            'external_id' => $result['id'],
            'publicado_em' => now(),
            'last_error' => null,
            'resultado' => array_merge($publication->resultado ?? [], [
                'driver' => $publication->driver.'_native',
                'video_id' => $result['id'],
                'url' => $result['url'],
            ]),
        ]);

        if ($publication->video && $publication->video->status === 'ready') {
            $publication->video->update(['status' => 'published', 'published_at' => now(), 'platform' => $publication->driver]);
        }

        Log::info('social.publish.succeeded', [
            'tenant_id' => $this->tenantId,
            'video_publication_id' => $publication->id,
            'platform' => $publication->driver,
        ]);
    }

    /**
     * Esgotou tentativas, estourou o tempo ou quebrou de forma inesperada.
     */
    public function failed(?Throwable $exception): void
    {
        $publication = VideoPublication::forTenant($this->tenantId)->find($this->publicationId);

        if ($publication && $publication->status !== 'publicado') {
            $reason = $exception instanceof PublishException ? $exception->reason : 'unexpected_error';
            $this->markFailed($publication, $reason);
            $this->log($publication, $reason, false);
        }
    }

    private function markFailed(VideoPublication $publication, string $reason): void
    {
        $publication->update([
            'status' => 'falhou',
            'last_error' => mb_substr($reason, 0, 100),
            'resultado' => array_merge($publication->resultado ?? [], ['driver' => $publication->driver.'_native', 'error' => $reason]),
        ]);
    }

    private function log(VideoPublication $publication, string $reason, bool $retryable): void
    {
        Log::warning('social.publish.failed', [
            'tenant_id' => $this->tenantId,
            'video_publication_id' => $publication->id,
            'platform' => $publication->platform,
            'reason' => $reason,
            'retryable' => $retryable,
            'attempt' => $this->attempts(),
        ]);
    }
}
