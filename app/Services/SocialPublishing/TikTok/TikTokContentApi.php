<?php

namespace App\Services\SocialPublishing\TikTok;

use App\Services\SocialPublishing\OAuth\TikTokOAuthClient;
use App\Services\SocialPublishing\PublishException;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Content Posting API do TikTok (Direct Post).
 *
 * Atenção: vários erros chegam com HTTP 200 e error.code diferente de "ok"; por isso toda
 * resposta é conferida pelo código, não só pelo status HTTP.
 */
class TikTokContentApi
{
    /**
     * Dados do criador, buscados de novo a cada tela de publicação e antes de cada envio.
     *
     * @return array{username: ?string, nickname: ?string, avatar: ?string, privacy_level_options: list<string>, comment_disabled: bool, duet_disabled: bool, stitch_disabled: bool, max_video_post_duration_sec: ?int}
     *
     * @throws PublishException
     */
    public function creatorInfo(string $token): array
    {
        $data = $this->post($token, '/v2/post/publish/creator_info/query/', []);

        return [
            'username' => $data['creator_username'] ?? null,
            'nickname' => $data['creator_nickname'] ?? null,
            'avatar' => $data['creator_avatar_url'] ?? null,
            'privacy_level_options' => array_values(array_filter($data['privacy_level_options'] ?? [], 'is_string')),
            'comment_disabled' => (bool) ($data['comment_disabled'] ?? false),
            'duet_disabled' => (bool) ($data['duet_disabled'] ?? false),
            'stitch_disabled' => (bool) ($data['stitch_disabled'] ?? false),
            'max_video_post_duration_sec' => isset($data['max_video_post_duration_sec']) ? (int) $data['max_video_post_duration_sec'] : null,
        ];
    }

    /**
     * @return array{publish_id: string, upload_url: string}
     *
     * @throws PublishException
     */
    public function initFileUpload(string $token, array $postInfo, int $size, int $chunkSize, int $chunkCount): array
    {
        $data = $this->post($token, '/v2/post/publish/video/init/', [
            'post_info' => $postInfo,
            'source_info' => [
                'source' => 'FILE_UPLOAD',
                'video_size' => $size,
                'chunk_size' => $chunkSize,
                'total_chunk_count' => $chunkCount,
            ],
        ]);

        $publishId = $data['publish_id'] ?? null;
        $uploadUrl = $data['upload_url'] ?? null;

        if (! is_string($publishId) || $publishId === '' || ! is_string($uploadUrl) || ! self::isTikTokUploadUrl($uploadUrl)) {
            throw new PublishException('upload_init_failed', retryable: true);
        }

        return ['publish_id' => $publishId, 'upload_url' => $uploadUrl];
    }

    /**
     * Envia uma parte do arquivo (bytes $start..$end) e devolve o status HTTP (206 = falta parte, 201 = completo).
     *
     * @param  resource  $resource
     *
     * @throws PublishException
     */
    public function uploadChunk(string $uploadUrl, $resource, string $mime, int $start, int $end, int $size): int
    {
        $stream = new LimitStream(Utils::streamFor($resource), $end - $start + 1, $start);

        try {
            $response = Http::withHeaders(['Content-Range' => "bytes {$start}-{$end}/{$size}"])
                ->withBody($stream, in_array($mime, ['video/mp4', 'video/quicktime', 'video/webm'], true) ? $mime : 'video/mp4')
                ->timeout((int) config('social.youtube.upload_timeout', 3600))
                ->put($uploadUrl);
        } catch (ConnectionException) {
            throw new PublishException('upload_interrupted', retryable: true);
        }

        if (in_array($response->status(), [201, 206], true)) {
            return $response->status();
        }

        // 403 = URL de envio expirada (vale 1 h); 5xx = tentar de novo; o resto é definitivo.
        throw new PublishException(
            $response->status() === 403 ? 'upload_url_expired' : 'upload_rejected',
            retryable: $response->status() === 403 || $response->serverError(),
        );
    }

    /**
     * @throws PublishException
     */
    public function status(string $token, string $publishId): array
    {
        return $this->post($token, '/v2/post/publish/status/fetch/', ['publish_id' => $publishId]);
    }

    public static function isTikTokUploadUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return str_starts_with($url, 'https://') && is_string($host) && str_ends_with($host, '.tiktokapis.com');
    }

    /**
     * @throws PublishException
     */
    private function post(string $token, string $path, array $body): array
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
                ->timeout(30)
                ->withBody(json_encode($body === [] ? new \stdClass : $body), 'application/json; charset=UTF-8')
                ->post(TikTokOAuthClient::API_HOST.$path);
        } catch (ConnectionException) {
            throw new PublishException('network', retryable: true);
        }

        $code = $response->json('error.code');

        if ($response->successful() && ($code === null || $code === 'ok')) {
            return (array) $response->json('data', []);
        }

        throw $this->classify($response);
    }

    private function classify(Response $response): PublishException
    {
        $reason = TikTokOAuthClient::errorReason($response, 'tiktok_http_'.$response->status());

        return match (true) {
            $reason === 'access_token_invalid' => new PublishException('token_invalid'),
            $reason === 'scope_not_authorized' => new PublishException('reconnect_required'),
            $reason === 'rate_limit_exceeded', $response->status() === 429 => new PublishException('rate_limited', retryable: true),
            $reason === 'spam_risk_too_many_posts' => new PublishException('publish_limit_reached'),
            $response->serverError() => new PublishException($reason, retryable: true),
            default => new PublishException($reason),
        };
    }
}
