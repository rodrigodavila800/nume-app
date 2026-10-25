<?php

namespace App\Services\SocialPublishing\YouTube;

use App\Models\SocialConnection;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\NativePublisher;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\SocialTokenManager;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Envio do arquivo hospedado na NUME para o canal conectado (YouTube Data API v3, upload resumable).
 *
 * Só sai o arquivo próprio (nunca uma URL digitada). A URL da sessão de upload dá acesso de escrita
 * ao canal por dias: fica criptografada no cache, nunca em `resultado` (que o portal devolve ao cliente)
 * nem em log. Se a tentativa cair no meio, a próxima retoma de onde parou.
 */
class YouTubeUploader implements NativePublisher
{
    public const UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos';

    private const SESSION_TTL_DAYS = 6;

    public function __construct(private readonly SocialTokenManager $tokens) {}

    public function publish(VideoPublication $publication): array
    {
        return $this->upload($publication);
    }

    /**
     * @return array{id: string, url: string}
     *
     * @throws PublishException
     */
    public function upload(VideoPublication $publication): array
    {
        $video = $publication->video;
        $connection = $publication->socialConnection;

        if (! $connection || $connection->platform !== 'youtube') {
            throw new PublishException('not_connected');
        }

        if (! $video?->sourceFileExists()) {
            throw new PublishException('source_missing');
        }

        $disk = Storage::disk($video->source_disk);
        $size = (int) $disk->size($video->source_path);
        $mime = $video->source_mime ?: 'video/mp4';

        $session = $this->cachedSession($publication);
        $offset = 0;

        if ($session !== null) {
            $probe = $this->probe($connection, $session, $size);
            if (isset($probe['done'])) {
                return $this->finish($publication, $probe['done']);
            }
            if ($probe['offset'] === null) {
                $session = null;
            } else {
                $offset = $probe['offset'];
            }
        }

        if ($session === null) {
            $session = $this->startSession($publication, $connection, $video, $size, $mime);
        }

        $stream = Utils::streamFor($disk->readStream($video->source_path));
        if ($offset > 0) {
            $stream = new LimitStream($stream, -1, $offset);
        }

        try {
            $response = Http::withToken($this->tokens->accessToken($connection))
                ->withHeaders(['Content-Range' => 'bytes '.$offset.'-'.($size - 1).'/'.$size])
                ->withBody($stream, $mime)
                ->timeout((int) config('social.youtube.upload_timeout', 3600))
                ->put($session);
        } catch (ConnectionException) {
            throw new PublishException('upload_interrupted', retryable: true);
        } finally {
            $stream->close();
        }

        if ($response->successful()) {
            return $this->finish($publication, $response);
        }

        if ($response->status() === 308 || $response->serverError() || $response->status() === 429) {
            throw new PublishException('upload_interrupted', retryable: true);
        }

        $this->forgetSession($publication);

        throw new PublishException($this->apiReason($response, 'upload_rejected'), retryable: in_array($response->status(), [404, 410], true));
    }

    /**
     * Metadados do vídeo no YouTube a partir do SEO da Sofi (limites oficiais: título 100, descrição 5000 bytes, tags 500).
     */
    public function metadata(VideoPublication $publication, Video $video): array
    {
        $clean = fn (?string $text) => trim(str_replace(['<', '>'], '', (string) $text));

        $title = Str::limit($clean($publication->titulo) ?: $clean($video->sofi_seo_title) ?: $clean($video->title), 100, '');

        $description = $clean($video->sofi_seo_description) ?: $clean($video->production?->title);
        $hashtags = collect($video->sofi_hashtags ?? [])
            ->filter(fn ($tag) => is_string($tag) && $tag !== '')
            ->map(fn (string $tag) => '#'.ltrim($clean($tag), '#'))
            ->take(15)
            ->implode(' ');
        $description = mb_strcut(trim($description."\n\n".$hashtags), 0, 4900, 'UTF-8');

        $tags = [];
        $length = 0;
        foreach (explode(',', (string) $video->sofi_seo_keywords) as $tag) {
            $tag = Str::limit($clean($tag), 60, '');
            if ($tag === '' || $length + mb_strlen($tag) + 1 > 450) {
                continue;
            }
            $tags[] = $tag;
            $length += mb_strlen($tag) + 1;
        }

        return [
            'snippet' => array_filter([
                'title' => $title,
                'description' => $description,
                'tags' => $tags ?: null,
                'categoryId' => (string) config('social.youtube.category_id', '22'),
            ], fn ($value) => $value !== null),
            'status' => [
                'privacyStatus' => in_array(config('social.youtube.privacy'), ['private', 'unlisted', 'public'], true)
                    ? config('social.youtube.privacy')
                    : 'private',
                'selfDeclaredMadeForKids' => false,
            ],
        ];
    }

    private function startSession(VideoPublication $publication, SocialConnection $connection, Video $video, int $size, string $mime): string
    {
        $response = $this->initRequest($this->tokens->accessToken($connection), $publication, $video, $size, $mime);

        // Token recusado mesmo dentro da validade (revogado ou trocado): renova uma vez e tenta de novo.
        if ($response->status() === 401) {
            $response = $this->initRequest($this->tokens->accessToken($connection, forceRefresh: true), $publication, $video, $size, $mime);

            if ($response->status() === 401) {
                $connection->markFailed('unauthorized', SocialConnection::STATUS_EXPIRED);
                throw new PublishException('reconnect_required');
            }
        }

        if (! $response->successful()) {
            $reason = $this->apiReason($response, 'upload_init_failed');
            $retryable = $response->serverError() || $response->status() === 429 || $reason === 'rateLimitExceeded';

            throw new PublishException($reason, $retryable);
        }

        $session = (string) $response->header('Location');
        if (! str_starts_with($session, self::UPLOAD_URL.'?')) {
            throw new PublishException('upload_init_failed', retryable: true);
        }

        Cache::put($this->sessionKey($publication), Crypt::encryptString($session), now()->addDays(self::SESSION_TTL_DAYS));

        return $session;
    }

    private function initRequest(string $token, VideoPublication $publication, Video $video, int $size, string $mime): Response
    {
        try {
            return Http::withToken($token)
                ->withHeaders(['X-Upload-Content-Type' => $mime, 'X-Upload-Content-Length' => (string) $size])
                ->acceptJson()
                ->timeout(30)
                ->post(self::UPLOAD_URL.'?uploadType=resumable&part=snippet,status', $this->metadata($publication, $video));
        } catch (ConnectionException) {
            throw new PublishException('network', retryable: true);
        }
    }

    /**
     * Pergunta ao Google quanto da sessão anterior já chegou.
     *
     * @return array{done: Response}|array{offset: ?int}
     */
    private function probe(SocialConnection $connection, string $session, int $size): array
    {
        try {
            $response = Http::withToken($this->tokens->accessToken($connection))
                ->withHeaders(['Content-Range' => 'bytes */'.$size])
                ->withBody('', 'application/octet-stream')
                ->timeout(30)
                ->put($session);
        } catch (ConnectionException) {
            throw new PublishException('network', retryable: true);
        }

        if ($response->successful()) {
            return ['done' => $response];
        }

        if ($response->status() === 308) {
            $range = (string) $response->header('Range');

            return ['offset' => preg_match('/bytes=0-(\d+)/', $range, $m) ? ((int) $m[1]) + 1 : 0];
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new PublishException('upload_interrupted', retryable: true);
        }

        // Sessão expirada ou desconhecida: recomeça do zero.
        return ['offset' => null];
    }

    /**
     * @return array{id: string, url: string}
     */
    private function finish(VideoPublication $publication, Response $response): array
    {
        $this->forgetSession($publication);

        $id = $response->json('id');
        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)) {
            throw new PublishException('upload_response_invalid');
        }

        return ['id' => $id, 'url' => 'https://youtu.be/'.$id];
    }

    private function cachedSession(VideoPublication $publication): ?string
    {
        $encrypted = Cache::get($this->sessionKey($publication));
        if (! is_string($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            $this->forgetSession($publication);

            return null;
        }
    }

    private function forgetSession(VideoPublication $publication): void
    {
        Cache::forget($this->sessionKey($publication));
    }

    private function sessionKey(VideoPublication $publication): string
    {
        return "youtube-upload-session:{$publication->id}";
    }

    private function apiReason(Response $response, string $fallback): string
    {
        $reason = $response->json('error.errors.0.reason') ?? $response->json('error.status');

        return is_string($reason) && preg_match('/^[A-Za-z0-9_.-]{1,60}$/', $reason) ? $reason : $fallback;
    }
}
