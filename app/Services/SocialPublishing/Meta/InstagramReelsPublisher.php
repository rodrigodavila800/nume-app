<?php

namespace App\Services\SocialPublishing\Meta;

use App\Models\SocialConnection;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\CaptionBuilder;
use App\Services\SocialPublishing\HostedVideoSource;
use App\Services\SocialPublishing\NativePublisher;
use App\Services\SocialPublishing\PublishException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

/**
 * Reels no Instagram profissional (Instagram API com Facebook Login).
 *
 * Contêiner REELS com upload resumable -> envio do arquivo ao rupload -> espera o processamento
 * (status_code FINISHED) -> media_publish. Se o processamento passar do tempo, o contêiner fica
 * guardado e a próxima tentativa só volta a esperar, sem reenviar o arquivo.
 * Limite da Meta: 100 posts por conta em 24 h (erro 9 = publish_limit_reached).
 */
class InstagramReelsPublisher implements NativePublisher
{
    private const CAPTION_LIMIT = 2200;

    private const HASHTAG_LIMIT = 30;

    public function __construct(
        private readonly MetaGraphPublisher $graph,
        private readonly HostedVideoSource $source,
        private readonly CaptionBuilder $captions,
    ) {}

    public function publish(VideoPublication $publication): array
    {
        [$video, $connection, $size] = $this->source->prepare($publication, 'instagram');
        $igId = $connection->external_account_id;
        $cacheKey = "instagram-container:{$publication->id}";

        $containerId = Cache::get($cacheKey);

        if (! is_string($containerId)) {
            $container = $this->graph->post($connection, "{$igId}/media", [
                'media_type' => 'REELS',
                'upload_type' => 'resumable',
                'caption' => $this->captions->build($publication, $video, self::CAPTION_LIMIT, self::HASHTAG_LIMIT),
                'share_to_feed' => 'true',
            ]);

            $containerId = $this->id($container['id'] ?? null, 'upload_init_failed');
            $uploadUrl = is_string($container['uri'] ?? null)
                ? $container['uri']
                : MetaGraphPublisher::RUPLOAD_HOST.'ig-api-upload/'.config('social.meta.graph_version', 'v26.0').'/'.$containerId;

            $this->graph->upload($connection, $uploadUrl, $this->source->stream($video), $size);

            Cache::put($cacheKey, $containerId, now()->addDay());
        }

        $this->waitUntilFinished($connection, $containerId, $cacheKey);

        $published = $this->graph->post($connection, "{$igId}/media_publish", ['creation_id' => $containerId]);
        Cache::forget($cacheKey);

        $mediaId = $this->id($published['id'] ?? null, 'publish_response_invalid');

        return ['id' => $mediaId, 'url' => $this->permalink($connection, $mediaId)];
    }

    private function waitUntilFinished(SocialConnection $connection, string $containerId, string $cacheKey): void
    {
        $attempts = max(1, (int) config('social.meta.poll_attempts', 20));

        for ($i = 0; $i < $attempts; $i++) {
            $status = $this->graph->get($connection, $containerId, ['fields' => 'status_code'])['status_code'] ?? null;

            if ($status === 'FINISHED') {
                return;
            }

            if ($status === 'ERROR') {
                Cache::forget($cacheKey);
                throw new PublishException('media_processing_failed');
            }

            if ($status === 'EXPIRED') {
                Cache::forget($cacheKey);
                throw new PublishException('container_expired', retryable: true);
            }

            if ($status === 'PUBLISHED') {
                return;
            }

            Sleep::for((int) config('social.meta.poll_seconds', 15))->seconds();
        }

        // Ainda processando: a próxima tentativa do job retoma a espera com o mesmo contêiner.
        throw new PublishException('processing', retryable: true);
    }

    private function permalink(SocialConnection $connection, string $mediaId): string
    {
        try {
            $permalink = $this->graph->get($connection, $mediaId, ['fields' => 'permalink'])['permalink'] ?? null;
        } catch (PublishException) {
            $permalink = null;
        }

        return is_string($permalink) && str_starts_with($permalink, 'https://www.instagram.com/')
            ? $permalink
            : 'https://www.instagram.com/';
    }

    private function id(mixed $value, string $reason): string
    {
        if ((is_string($value) || is_int($value)) && preg_match('/^\d{1,30}$/', (string) $value)) {
            return (string) $value;
        }

        throw new PublishException($reason);
    }
}
