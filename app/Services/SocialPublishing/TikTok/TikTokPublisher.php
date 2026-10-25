<?php

namespace App\Services\SocialPublishing\TikTok;

use App\Models\SocialConnection;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\HostedVideoSource;
use App\Services\SocialPublishing\NativePublisher;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\SocialTokenManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

/**
 * Direct Post no TikTok com as escolhas feitas pelo cliente na tela "Publicar no TikTok"
 * (gravadas em resultado['tiktok']). Nunca publica sem essas escolhas.
 *
 * Antes de enviar: creator info de novo (pode ter mudado), privacidade dentro das opções,
 * duração dentro do limite do criador e conteúdo de marca nunca privado. Depois: init FILE_UPLOAD,
 * envio em partes e acompanhamento do status. Concluído o envio, o publish_id fica guardado e uma
 * nova tentativa só volta a acompanhar, sem reenviar.
 */
class TikTokPublisher implements NativePublisher
{
    public const PRIVACY_LEVELS = ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'];

    private const SINGLE_CHUNK_LIMIT = 64 * 1024 * 1024;

    public function __construct(
        private readonly TikTokContentApi $content,
        private readonly SocialTokenManager $tokens,
        private readonly HostedVideoSource $source,
    ) {}

    public function publish(VideoPublication $publication): array
    {
        [$video, $connection, $size, $mime] = $this->source->prepare($publication, 'tiktok');

        $options = $publication->resultado['tiktok'] ?? null;
        if (! is_array($options) || ! in_array($options['privacy_level'] ?? null, self::PRIVACY_LEVELS, true)) {
            throw new PublishException('tiktok_options_missing');
        }

        $cacheKey = "tiktok-publish:{$publication->id}";
        $publishId = Cache::get($cacheKey);

        if (! is_string($publishId)) {
            $creator = $this->authorized($connection, fn (string $token) => $this->content->creatorInfo($token));
            $this->assertAllowed($options, $creator, (int) $video->duration_seconds);

            [$chunkSize, $chunkCount] = $this->chunks($size);

            $init = $this->authorized($connection, fn (string $token) => $this->content->initFileUpload($token, [
                'title' => mb_substr((string) ($options['title'] ?? ''), 0, 2200),
                'privacy_level' => $options['privacy_level'],
                // O que o criador desativou no app fica desativado, mesmo que a escolha diga outra coisa.
                'disable_comment' => $creator['comment_disabled'] || ! ($options['allow_comment'] ?? false),
                'disable_duet' => $creator['duet_disabled'] || ! ($options['allow_duet'] ?? false),
                'disable_stitch' => $creator['stitch_disabled'] || ! ($options['allow_stitch'] ?? false),
                'brand_content_toggle' => (bool) ($options['brand_content'] ?? false),
                'brand_organic_toggle' => (bool) ($options['brand_organic'] ?? false),
            ], $size, $chunkSize, $chunkCount));

            $this->upload($video, $init['upload_url'], $mime, $size, $chunkSize, $chunkCount);

            $publishId = $init['publish_id'];
            Cache::put($cacheKey, $publishId, now()->addDay());
        }

        $status = $this->waitForPublish($connection, $publishId, $cacheKey);
        Cache::forget($cacheKey);

        $postId = $status['publicaly_available_post_id'][0] ?? null;
        $username = ltrim((string) $connection->handle, '@');

        return [
            'id' => $postId !== null ? (string) $postId : $publishId,
            'url' => $postId !== null && $username !== ''
                ? "https://www.tiktok.com/@{$username}/video/{$postId}"
                : 'https://www.tiktok.com/'.($username !== '' ? '@'.$username : ''),
        ];
    }

    /**
     * Regras do TikTok que valem no envio, não só na tela.
     */
    public function assertAllowed(array $options, array $creator, int $durationSeconds): void
    {
        if (! in_array($options['privacy_level'], $creator['privacy_level_options'], true)) {
            throw new PublishException('privacy_level_option_mismatch');
        }

        if (($options['brand_content'] ?? false) && $options['privacy_level'] === 'SELF_ONLY') {
            throw new PublishException('branded_content_cannot_be_private');
        }

        $max = $creator['max_video_post_duration_sec'];
        if ($max !== null && $durationSeconds > 0 && $durationSeconds > $max) {
            throw new PublishException('tiktok_duration_invalid');
        }
    }

    /**
     * Até 64 MB vai numa parte só; acima disso, partes de social.tiktok.chunk_mb (a última leva o resto).
     *
     * @return array{0: int, 1: int}
     */
    public function chunks(int $size): array
    {
        if ($size <= self::SINGLE_CHUNK_LIMIT) {
            return [$size, 1];
        }

        $chunk = max(5, min(64, (int) config('social.tiktok.chunk_mb', 10))) * 1024 * 1024;

        return [$chunk, intdiv($size, $chunk)];
    }

    private function upload($video, string $uploadUrl, string $mime, int $size, int $chunkSize, int $chunkCount): void
    {
        $resource = $this->source->stream($video);

        try {
            for ($i = 0; $i < $chunkCount; $i++) {
                $start = $i * $chunkSize;
                $end = $i === $chunkCount - 1 ? $size - 1 : $start + $chunkSize - 1;

                $status = $this->content->uploadChunk($uploadUrl, $resource, $mime, $start, $end, $size);

                if ($i === $chunkCount - 1 && $status !== 201) {
                    throw new PublishException('upload_incomplete', retryable: true);
                }
            }
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    private function waitForPublish(SocialConnection $connection, string $publishId, string $cacheKey): array
    {
        $attempts = max(1, (int) config('social.meta.poll_attempts', 20));

        for ($i = 0; $i < $attempts; $i++) {
            $status = $this->authorized($connection, fn (string $token) => $this->content->status($token, $publishId));

            if (($status['status'] ?? null) === 'PUBLISH_COMPLETE') {
                return $status;
            }

            if (($status['status'] ?? null) === 'FAILED') {
                Cache::forget($cacheKey);
                $reason = is_string($status['fail_reason'] ?? null) && preg_match('/^[A-Za-z0-9_.-]{1,60}$/', $status['fail_reason'])
                    ? $status['fail_reason']
                    : 'tiktok_publish_failed';

                throw new PublishException($reason);
            }

            Sleep::for((int) config('social.meta.poll_seconds', 15))->seconds();
        }

        throw new PublishException('processing', retryable: true);
    }

    /**
     * Chama a API com o token da conta; token recusado é renovado uma vez antes de pedir reconexão.
     *
     * @template T
     *
     * @param  callable(string): T  $call
     * @return T
     */
    private function authorized(SocialConnection $connection, callable $call): mixed
    {
        try {
            return $call($this->tokens->accessToken($connection));
        } catch (PublishException $e) {
            if ($e->reason === 'reconnect_required') {
                $connection->markFailed('scope_not_authorized', SocialConnection::STATUS_EXPIRED);
                throw $e;
            }

            if ($e->reason !== 'token_invalid') {
                throw $e;
            }
        }

        try {
            return $call($this->tokens->accessToken($connection, forceRefresh: true));
        } catch (PublishException $e) {
            if (in_array($e->reason, ['token_invalid', 'reconnect_required'], true)) {
                $connection->markFailed('access_token_invalid', SocialConnection::STATUS_EXPIRED);
                throw new PublishException('reconnect_required');
            }

            throw $e;
        }
    }
}
