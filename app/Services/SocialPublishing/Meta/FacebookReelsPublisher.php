<?php

namespace App\Services\SocialPublishing\Meta;

use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\CaptionBuilder;
use App\Services\SocialPublishing\HostedVideoSource;
use App\Services\SocialPublishing\NativePublisher;
use App\Services\SocialPublishing\PublishException;
use Illuminate\Support\Facades\Cache;

/**
 * Reels na Página do Facebook (Video API, /{page-id}/video_reels).
 *
 * start -> envio do arquivo ao rupload -> finish com video_state=PUBLISHED. Se o finish falhar
 * depois do envio, a próxima tentativa só refaz o finish com o mesmo vídeo.
 * Requisitos da Meta: 9:16, 3 a 90 s, mínimo 540x960; limite de 30 Reels por Página em 24 h.
 */
class FacebookReelsPublisher implements NativePublisher
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
        [$video, $connection, $size] = $this->source->prepare($publication, 'facebook');
        $this->assertReelSpecs($video);

        $pageId = $connection->external_account_id;
        $cacheKey = "facebook-reel:{$publication->id}";
        $videoId = Cache::get($cacheKey);

        if (! is_string($videoId)) {
            $session = $this->graph->post($connection, "{$pageId}/video_reels", ['upload_phase' => 'start']);
            $videoId = $this->id($session['video_id'] ?? null, 'upload_init_failed');
            $uploadUrl = is_string($session['upload_url'] ?? null)
                ? $session['upload_url']
                : MetaGraphPublisher::RUPLOAD_HOST.'video-upload/'.config('social.meta.graph_version', 'v26.0').'/'.$videoId;

            $this->graph->upload($connection, $uploadUrl, $this->source->stream($video), $size);

            Cache::put($cacheKey, $videoId, now()->addDay());
        }

        $finished = $this->graph->post($connection, "{$pageId}/video_reels", [
            'upload_phase' => 'finish',
            'video_id' => $videoId,
            'video_state' => 'PUBLISHED',
            'description' => $this->captions->build($publication, $video, self::CAPTION_LIMIT, self::HASHTAG_LIMIT),
        ]);

        if (($finished['success'] ?? false) !== true) {
            throw new PublishException('publish_response_invalid', retryable: true);
        }

        Cache::forget($cacheKey);

        return ['id' => $videoId, 'url' => 'https://www.facebook.com/reel/'.$videoId];
    }

    /**
     * Recusa antes de enviar quando já sabemos que o vídeo não serve como Reel.
     */
    private function assertReelSpecs(Video $video): void
    {
        $width = (int) $video->source_width;
        $height = (int) $video->source_height;

        if ($width > 0 && $height > 0) {
            $vertical = abs(($width / $height) - (9 / 16)) <= 0.02;
            if (! $vertical || $width < 540 || $height < 960) {
                throw new PublishException('reel_format_invalid');
            }
        }

        $duration = (int) $video->duration_seconds;
        if ($duration > 0 && ($duration < 3 || $duration > 90)) {
            throw new PublishException('reel_duration_invalid');
        }
    }

    private function id(mixed $value, string $reason): string
    {
        if ((is_string($value) || is_int($value)) && preg_match('/^\d{1,30}$/', (string) $value)) {
            return (string) $value;
        }

        throw new PublishException($reason);
    }
}
