<?php

namespace App\Services\SocialPublishing\X;

use App\Models\SocialConnection;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\CaptionBuilder;
use App\Services\SocialPublishing\HostedVideoSource;
use App\Services\SocialPublishing\NativePublisher;
use App\Services\SocialPublishing\OAuth\XOAuthClient;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\SocialTokenManager;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Vídeo no X (API v2): initialize -> partes de até 4 MB -> finalize -> espera do processamento ->
 * post. Cada post é cobrado dos créditos da conta de desenvolvedor da NUME (pay-per-use); sem
 * crédito o X responde 402 e a falha é definitiva. A legenda vai sem link (post com URL custa mais).
 */
class XVideoPublisher implements NativePublisher
{
    private const SEGMENT_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly SocialTokenManager $tokens,
        private readonly HostedVideoSource $source,
        private readonly CaptionBuilder $captions,
    ) {}

    public function publish(VideoPublication $publication): array
    {
        [$video, $connection, $size, $mime] = $this->source->prepare($publication, 'x');
        $cacheKey = "x-media:{$publication->id}";

        $mediaId = Cache::get($cacheKey);

        if (! is_string($mediaId)) {
            $mediaId = $this->upload($connection, $video, $size, $mime);
            Cache::put($cacheKey, $mediaId, now()->addHours(20));
        }

        $this->waitUntilReady($connection, $mediaId, $cacheKey);

        $text = preg_replace('#https?://\S+#', '', $this->captions->build($publication, $video, (int) config('social.x.text_limit', 280), 3));

        $post = $this->send($connection, fn (PendingRequest $request) => $request->asJson()->post(XOAuthClient::API.'/tweets', [
            'text' => trim((string) $text),
            'media' => ['media_ids' => [$mediaId]],
        ]))->json('data');

        Cache::forget($cacheKey);

        $postId = $post['id'] ?? null;
        if (! is_string($postId) || ! preg_match('/^\d{1,30}$/', $postId)) {
            throw new PublishException('publish_response_invalid');
        }

        $username = ltrim((string) $connection->handle, '@');

        return ['id' => $postId, 'url' => 'https://x.com/'.($username !== '' ? $username : 'i/web').'/status/'.$postId];
    }

    private function upload(SocialConnection $connection, Video $video, int $size, string $mime): string
    {
        $mediaId = $this->send($connection, fn (PendingRequest $request) => $request->asJson()->post(XOAuthClient::API.'/media/upload/initialize', [
            'media_type' => in_array($mime, ['video/mp4', 'video/quicktime'], true) ? $mime : 'video/mp4',
            'total_bytes' => $size,
            'media_category' => 'tweet_video',
        ]))->json('data.id');

        if (! is_string($mediaId) || ! preg_match('/^\d{1,30}$/', $mediaId)) {
            throw new PublishException('upload_init_failed', retryable: true);
        }

        $resource = $this->source->stream($video);

        try {
            for ($index = 0, $offset = 0; $offset < $size; $index++, $offset += self::SEGMENT_BYTES) {
                $length = min(self::SEGMENT_BYTES, $size - $offset);
                $segment = new LimitStream(Utils::streamFor($resource), $length, $offset);

                $this->send($connection, fn (PendingRequest $request) => $request->asMultipart()
                    ->attach('media', $segment, 'segment-'.$index)
                    ->post(XOAuthClient::API.'/media/upload/'.$mediaId.'/append', ['segment_index' => (string) $index]), uploading: true);
            }
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        $this->send($connection, fn (PendingRequest $request) => $request->post(XOAuthClient::API.'/media/upload/'.$mediaId.'/finalize'));

        return $mediaId;
    }

    private function waitUntilReady(SocialConnection $connection, string $mediaId, string $cacheKey): void
    {
        $attempts = max(1, (int) config('social.meta.poll_attempts', 20));

        for ($i = 0; $i < $attempts; $i++) {
            $info = $this->send($connection, fn (PendingRequest $request) => $request->get(XOAuthClient::API.'/media/upload', [
                'command' => 'STATUS',
                'media_id' => $mediaId,
            ]))->json('data.processing_info');

            // Sem processing_info a mídia já está pronta.
            $state = is_array($info) ? ($info['state'] ?? null) : 'succeeded';

            if ($state === 'succeeded') {
                return;
            }

            if ($state === 'failed') {
                Cache::forget($cacheKey);
                throw new PublishException('media_processing_failed');
            }

            Sleep::for(max(1, min(60, (int) ($info['check_after_secs'] ?? config('social.meta.poll_seconds', 15)))))->seconds();
        }

        throw new PublishException('processing', retryable: true);
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(SocialConnection $connection, callable $call, bool $uploading = false): Response
    {
        try {
            $response = $call(Http::withToken($this->tokens->accessToken($connection))->acceptJson()->timeout($uploading ? 600 : 30));
        } catch (ConnectionException) {
            throw new PublishException($uploading ? 'upload_interrupted' : 'network', retryable: true);
        }

        if ($response->successful()) {
            return $response;
        }

        return match (true) {
            $response->status() === 401 => throw $this->reconnect($connection, $response),
            // Pay-per-use: sem créditos na conta de desenvolvedor da NUME.
            $response->status() === 402 => throw new PublishException('x_credits_exhausted'),
            $response->status() === 429 => throw new PublishException('rate_limited', retryable: true),
            default => throw new PublishException(
                XOAuthClient::errorReason($response, 'x_http_'.$response->status()),
                retryable: $response->serverError(),
            ),
        };
    }

    private function reconnect(SocialConnection $connection, Response $response): PublishException
    {
        $connection->markFailed(XOAuthClient::errorReason($response, 'unauthorized'), SocialConnection::STATUS_EXPIRED);

        return new PublishException('reconnect_required');
    }
}
