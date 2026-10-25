<?php

namespace App\Services\SocialPublishing;

use App\Models\SocialConnection;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Support\Facades\Storage;

/**
 * Valida o que todo publicador nativo precisa antes de chamar a rede: conta da rede certa
 * e arquivo hospedado presente. Nunca usa URL digitada.
 */
class HostedVideoSource
{
    /**
     * @return array{0: Video, 1: SocialConnection, 2: int, 3: string} vídeo, conta, tamanho em bytes, mime
     *
     * @throws PublishException
     */
    public function prepare(VideoPublication $publication, string $platform): array
    {
        $connection = $publication->socialConnection;
        if (! $connection || $connection->platform !== $platform) {
            throw new PublishException('not_connected');
        }

        $video = $publication->video;
        if (! $video?->sourceFileExists()) {
            throw new PublishException('source_missing');
        }

        return [
            $video,
            $connection,
            (int) Storage::disk($video->source_disk)->size($video->source_path),
            $video->source_mime ?: 'video/mp4',
        ];
    }

    /**
     * @return resource
     */
    public function stream(Video $video)
    {
        $stream = Storage::disk($video->source_disk)->readStream($video->source_path);
        if (! is_resource($stream)) {
            throw new PublishException('source_missing');
        }

        return $stream;
    }
}
