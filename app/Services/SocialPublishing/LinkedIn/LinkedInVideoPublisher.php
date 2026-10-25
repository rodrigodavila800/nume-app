<?php

namespace App\Services\SocialPublishing\LinkedIn;

use App\Models\SocialConnection;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\CaptionBuilder;
use App\Services\SocialPublishing\HostedVideoSource;
use App\Services\SocialPublishing\NativePublisher;
use App\Services\SocialPublishing\OAuth\LinkedInOAuthClient;
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
 * Vídeo no perfil do LinkedIn (Videos API + Posts API, versionadas).
 *
 * initializeUpload -> envio das partes (ETag de cada uma) -> finalizeUpload -> espera AVAILABLE ->
 * post no feed. Depois de finalizado, o vídeo fica guardado e uma nova tentativa só volta a esperar
 * e a publicar, sem reenviar. Limites do LinkedIn: 75 KB a 500 MB, 3 s a 30 min, MP4.
 */
class LinkedInVideoPublisher implements NativePublisher
{
    public const API = 'https://api.linkedin.com/rest';

    private const MIN_BYTES = 75 * 1024;

    private const MAX_BYTES = 500 * 1024 * 1024;

    private const COMMENTARY_LIMIT = 3000;

    public function __construct(
        private readonly SocialTokenManager $tokens,
        private readonly HostedVideoSource $source,
        private readonly CaptionBuilder $captions,
    ) {}

    public function publish(VideoPublication $publication): array
    {
        [$video, $connection, $size] = $this->source->prepare($publication, 'linkedin');
        $owner = 'urn:li:person:'.$connection->external_account_id;
        $cacheKey = "linkedin-video:{$publication->id}";

        $videoUrn = Cache::get($cacheKey);

        if (! is_string($videoUrn)) {
            $this->assertSpecs($video, $size);
            $videoUrn = $this->upload($connection, $video, $owner, $size);
            Cache::put($cacheKey, $videoUrn, now()->addDays(7));
        }

        $this->waitUntilAvailable($connection, $videoUrn, $cacheKey);

        $response = $this->send($connection, fn ($request) => $request->post(self::API.'/posts', [
            'author' => $owner,
            'commentary' => self::escapeLittleText($this->captions->build($publication, $video, self::COMMENTARY_LIMIT, 10)),
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'content' => [
                'media' => [
                    'title' => mb_substr(trim((string) ($publication->titulo ?: $video->title)), 0, 200),
                    'id' => $videoUrn,
                ],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ]));

        $postUrn = (string) $response->header('x-restli-id');
        if (! preg_match('/^urn:li:(share|ugcPost):\d{1,30}$/', $postUrn)) {
            throw new PublishException('publish_response_invalid');
        }

        Cache::forget($cacheKey);

        return ['id' => $postUrn, 'url' => 'https://www.linkedin.com/feed/update/'.$postUrn.'/'];
    }

    /**
     * Escapa os caracteres reservados do "little text format" do LinkedIn. O # fica livre para
     * as hashtags continuarem funcionando.
     */
    public static function escapeLittleText(string $text): string
    {
        return preg_replace('/([\\\\|{}@\[\]()<>*_~])/', '\\\\$1', $text);
    }

    private function assertSpecs(Video $video, int $size): void
    {
        if ($size < self::MIN_BYTES || $size > self::MAX_BYTES) {
            throw new PublishException('linkedin_file_size_invalid');
        }

        $duration = (int) $video->duration_seconds;
        if ($duration > 0 && ($duration < 3 || $duration > 1800)) {
            throw new PublishException('linkedin_duration_invalid');
        }
    }

    /**
     * @return string URN do vídeo finalizado
     */
    private function upload(SocialConnection $connection, Video $video, string $owner, int $size): string
    {
        $init = $this->send($connection, fn ($request) => $request->post(self::API.'/videos?action=initializeUpload', [
            'initializeUploadRequest' => [
                'owner' => $owner,
                'fileSizeBytes' => $size,
                'uploadCaptions' => false,
                'uploadThumbnail' => false,
            ],
        ]))->json('value');

        $videoUrn = $init['video'] ?? null;
        $instructions = $init['uploadInstructions'] ?? null;

        if (! is_string($videoUrn) || ! preg_match('/^urn:li:video:[A-Za-z0-9_-]{1,100}$/', $videoUrn) || ! is_array($instructions) || $instructions === []) {
            throw new PublishException('upload_init_failed', retryable: true);
        }

        $resource = $this->source->stream($video);
        $etags = [];

        try {
            foreach ($instructions as $part) {
                $url = $part['uploadUrl'] ?? null;
                $first = (int) ($part['firstByte'] ?? -1);
                $last = (int) ($part['lastByte'] ?? -1);

                if (! is_string($url) || ! self::isLinkedInUploadUrl($url) || $first < 0 || $last < $first || $last >= $size) {
                    throw new PublishException('upload_init_failed', retryable: true);
                }

                $etags[] = $this->uploadPart($url, $resource, $first, $last);
            }
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        $this->send($connection, fn ($request) => $request->post(self::API.'/videos?action=finalizeUpload', [
            'finalizeUploadRequest' => [
                'video' => $videoUrn,
                'uploadToken' => (string) ($init['uploadToken'] ?? ''),
                'uploadedPartIds' => $etags,
            ],
        ]));

        return $videoUrn;
    }

    /**
     * @param  resource  $resource
     */
    private function uploadPart(string $url, $resource, int $first, int $last): string
    {
        try {
            $response = Http::withBody(new LimitStream(Utils::streamFor($resource), $last - $first + 1, $first), 'application/octet-stream')
                ->timeout((int) config('social.youtube.upload_timeout', 3600))
                ->put($url);
        } catch (ConnectionException) {
            throw new PublishException('upload_interrupted', retryable: true);
        }

        $etag = $response->header('ETag');

        if ($response->successful() && $etag !== '') {
            return $etag;
        }

        throw new PublishException(
            $response->status() === 401 ? 'upload_url_expired' : 'upload_rejected',
            retryable: $response->status() === 401 || $response->serverError(),
        );
    }

    private function waitUntilAvailable(SocialConnection $connection, string $videoUrn, string $cacheKey): void
    {
        $attempts = max(1, (int) config('social.meta.poll_attempts', 20));

        for ($i = 0; $i < $attempts; $i++) {
            $video = $this->send($connection, fn ($request) => $request->get(self::API.'/videos/'.rawurlencode($videoUrn)))->json();
            $status = $video['status'] ?? null;

            if ($status === 'AVAILABLE') {
                return;
            }

            if ($status === 'PROCESSING_FAILED') {
                Cache::forget($cacheKey);
                $reason = $video['processingFailureReason'] ?? null;

                throw new PublishException(is_string($reason) && preg_match('/^[A-Za-z0-9_.-]{1,60}$/', $reason) ? $reason : 'media_processing_failed');
            }

            Sleep::for((int) config('social.meta.poll_seconds', 15))->seconds();
        }

        throw new PublishException('processing', retryable: true);
    }

    /**
     * Chamada à API REST versionada, com erros classificados.
     *
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(SocialConnection $connection, callable $call): Response
    {
        try {
            $response = $call(Http::withToken($this->tokens->accessToken($connection))
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'LinkedIn-Version' => (string) config('social.linkedin.api_version', '202610'),
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->timeout(30));
        } catch (ConnectionException) {
            throw new PublishException('network', retryable: true);
        }

        if ($response->successful()) {
            return $response;
        }

        $reason = LinkedInOAuthClient::errorReason($response, 'linkedin_http_'.$response->status());

        if (in_array($response->status(), [401, 403], true)) {
            $connection->markFailed($reason, SocialConnection::STATUS_EXPIRED);

            throw new PublishException('reconnect_required');
        }

        if ($response->status() === 429) {
            throw new PublishException('rate_limited', retryable: true);
        }

        throw new PublishException($reason, retryable: $response->serverError() || $response->status() === 409);
    }

    public static function isLinkedInUploadUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return str_starts_with($url, 'https://') && is_string($host) && ($host === 'www.linkedin.com' || str_ends_with($host, '.linkedin.com'));
    }
}
