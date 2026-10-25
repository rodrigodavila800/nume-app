<?php

namespace App\Services\SocialPublishing\Pinterest;

use App\Models\SocialConnection;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\HostedVideoSource;
use App\Services\SocialPublishing\NativePublisher;
use App\Services\SocialPublishing\PublishException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Pin de vídeo no Pinterest, na pasta escolhida pelo cliente na tela "Publicar no Pinterest"
 * (resultado['pinterest']).
 *
 * Registro da mídia -> envio ao S3 com os campos assinados (o arquivo por último, sem token) ->
 * espera "succeeded" -> criação do pin com capa tirada de um quadro do vídeo. Depois do envio,
 * a mídia fica guardada e uma nova tentativa só volta a esperar e criar o pin.
 */
class PinterestVideoPublisher implements NativePublisher
{
    public function __construct(
        private readonly PinterestApi $api,
        private readonly HostedVideoSource $source,
    ) {}

    public function publish(VideoPublication $publication): array
    {
        [$video, $connection, $size, $mime] = $this->source->prepare($publication, 'pinterest');

        $options = $publication->resultado['pinterest'] ?? null;
        if (! is_array($options) || ! is_string($options['board_id'] ?? null) || ! preg_match('/^\d{1,30}$/', $options['board_id'])) {
            throw new PublishException('pinterest_options_missing');
        }

        $cacheKey = "pinterest-media:{$publication->id}";
        $mediaId = Cache::get($cacheKey);

        if (! is_string($mediaId)) {
            $mediaId = $this->upload($connection, $video, $mime);
            Cache::put($cacheKey, $mediaId, now()->addDay());
        }

        $this->waitUntilReady($connection, $mediaId, $cacheKey);

        $pin = $this->api->request($connection, 'post', 'pins', array_filter([
            'board_id' => $options['board_id'],
            'title' => mb_substr((string) ($options['title'] ?? ''), 0, 100) ?: null,
            'description' => mb_substr((string) ($options['description'] ?? ''), 0, 800) ?: null,
            'link' => is_string($options['link'] ?? null) && str_starts_with($options['link'], 'https://') ? mb_substr($options['link'], 0, 2048) : null,
            'media_source' => [
                'source_type' => 'video_id',
                'media_id' => $mediaId,
                // Capa: quadro em 1 s do próprio vídeo (o Pinterest usa o último se o vídeo for menor).
                'cover_image_key_frame_time' => 1,
            ],
        ], fn ($value) => $value !== null));

        Cache::forget($cacheKey);

        $pinId = $pin['id'] ?? null;
        if (! is_string($pinId) || ! preg_match('/^\d{1,30}$/', $pinId)) {
            throw new PublishException('publish_response_invalid');
        }

        return ['id' => $pinId, 'url' => 'https://www.pinterest.com/pin/'.$pinId.'/'];
    }

    private function upload(SocialConnection $connection, $video, string $mime): string
    {
        $register = $this->api->request($connection, 'post', 'media', ['media_type' => 'video']);

        $mediaId = $register['media_id'] ?? null;
        $uploadUrl = $register['upload_url'] ?? null;
        $parameters = $register['upload_parameters'] ?? null;

        if (! is_string($mediaId) || ! preg_match('/^\d{1,30}$/', $mediaId) || ! is_string($uploadUrl) || ! self::isUploadHost($uploadUrl) || ! is_array($parameters)) {
            throw new PublishException('upload_init_failed', retryable: true);
        }

        $fields = array_filter($parameters, fn ($value, $key) => is_string($key) && is_scalar($value), ARRAY_FILTER_USE_BOTH);
        $resource = $this->source->stream($video);

        try {
            // Sem token do Pinterest: o destino é o S3, autorizado pelos campos assinados.
            $response = Http::asMultipart()
                ->attach('file', $resource, basename((string) $video->source_path), ['Content-Type' => $mime])
                ->timeout((int) config('social.youtube.upload_timeout', 3600))
                ->post($uploadUrl, array_map('strval', $fields));
        } catch (ConnectionException) {
            throw new PublishException('upload_interrupted', retryable: true);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        if (! $response->successful()) {
            throw new PublishException('upload_rejected', retryable: $response->serverError() || $response->status() === 403);
        }

        return $mediaId;
    }

    private function waitUntilReady(SocialConnection $connection, string $mediaId, string $cacheKey): void
    {
        $attempts = max(1, (int) config('social.meta.poll_attempts', 20));

        for ($i = 0; $i < $attempts; $i++) {
            $status = $this->api->request($connection, 'get', 'media/'.$mediaId)['status'] ?? null;

            if ($status === 'succeeded') {
                return;
            }

            if ($status === 'failed') {
                Cache::forget($cacheKey);
                throw new PublishException('media_processing_failed');
            }

            Sleep::for((int) config('social.meta.poll_seconds', 15))->seconds();
        }

        throw new PublishException('processing', retryable: true);
    }

    public static function isUploadHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return str_starts_with($url, 'https://') && is_string($host) && str_ends_with($host, '.amazonaws.com');
    }
}
