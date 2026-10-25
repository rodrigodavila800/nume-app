<?php

namespace App\Services\SocialPublishing\Meta;

use App\Models\SocialConnection;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\CaptionBuilder;
use App\Services\SocialPublishing\HostedVideoSource;
use App\Services\SocialPublishing\NativePublisher;
use App\Services\SocialPublishing\OAuth\ThreadsOAuthClient;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\SocialTokenManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Vídeo no Threads. A API só aceita buscar o vídeo por URL pública: mandamos a URL assinada e
 * temporária do arquivo hospedado (o servidor precisa estar acessível pela internet).
 * Contêiner VIDEO -> espera FINISHED -> threads_publish. Texto: até 500 caracteres e 1 tag.
 */
class ThreadsVideoPublisher implements NativePublisher
{
    private const TEXT_LIMIT = 500;

    private const HASHTAG_LIMIT = 1;

    public function __construct(
        private readonly ThreadsOAuthClient $threads,
        private readonly SocialTokenManager $tokens,
        private readonly MetaGraphPublisher $meta,
        private readonly HostedVideoSource $source,
        private readonly CaptionBuilder $captions,
    ) {}

    public function publish(VideoPublication $publication): array
    {
        [$video, $connection] = $this->source->prepare($publication, 'threads');
        $userId = $connection->external_account_id;
        $cacheKey = "threads-container:{$publication->id}";

        $containerId = Cache::get($cacheKey);

        if (! is_string($containerId)) {
            $container = $this->call($connection, 'post', "{$userId}/threads", [
                'media_type' => 'VIDEO',
                'video_url' => $video->publicFetchUrl(),
                'text' => $this->captions->build($publication, $video, self::TEXT_LIMIT, self::HASHTAG_LIMIT),
            ]);
            $containerId = $this->id($container['id'] ?? null, 'upload_init_failed');
            Cache::put($cacheKey, $containerId, now()->addHours(23));
        }

        $this->waitUntilFinished($connection, $containerId, $cacheKey);

        $published = $this->call($connection, 'post', "{$userId}/threads_publish", ['creation_id' => $containerId]);
        Cache::forget($cacheKey);
        $postId = $this->id($published['id'] ?? null, 'publish_response_invalid');

        try {
            $permalink = $this->call($connection, 'get', $postId, ['fields' => 'permalink'])['permalink'] ?? null;
        } catch (PublishException) {
            $permalink = null;
        }

        return [
            'id' => $postId,
            'url' => is_string($permalink) && str_starts_with($permalink, 'https://www.threads.') ? $permalink : 'https://www.threads.com/',
        ];
    }

    private function waitUntilFinished(SocialConnection $connection, string $containerId, string $cacheKey): void
    {
        $attempts = max(1, (int) config('social.meta.poll_attempts', 20));

        for ($i = 0; $i < $attempts; $i++) {
            $status = $this->call($connection, 'get', $containerId, ['fields' => 'status,error_message'])['status'] ?? null;

            if ($status === 'FINISHED' || $status === 'PUBLISHED') {
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

            Sleep::for((int) config('social.meta.poll_seconds', 15))->seconds();
        }

        throw new PublishException('processing', retryable: true);
    }

    private function call(SocialConnection $connection, string $method, string $path, array $data = []): array
    {
        try {
            $request = Http::withToken($this->tokens->accessToken($connection))->acceptJson()->timeout(30);
            $response = $method === 'get'
                ? $request->get($this->threads->apiUrl($path), $data)
                : $request->asForm()->post($this->threads->apiUrl($path), $data);
        } catch (ConnectionException) {
            throw new PublishException('network', retryable: true);
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw $this->meta->classify($response, $connection);
    }

    private function id(mixed $value, string $reason): string
    {
        if ((is_string($value) || is_int($value)) && preg_match('/^\d{1,30}$/', (string) $value)) {
            return (string) $value;
        }

        throw new PublishException($reason);
    }
}
