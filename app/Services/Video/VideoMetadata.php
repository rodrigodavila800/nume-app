<?php

namespace App\Services\Video;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Lê largura, altura e duração de um arquivo de vídeo com o ffprobe.
 *
 * Opcional: sem o ffprobe instalado (ou com arquivo ilegível) devolve tudo nulo e o upload
 * segue normalmente. Nunca lança exceção.
 */
class VideoMetadata
{
    /**
     * @return array{width: ?int, height: ?int, duration: ?int}
     */
    public function read(string $absolutePath): array
    {
        $empty = ['width' => null, 'height' => null, 'duration' => null];

        try {
            $process = new Process([
                (string) config('social.video.ffprobe_binary', 'ffprobe'),
                '-v', 'error',
                '-select_streams', 'v:0',
                '-show_entries', 'stream=width,height:format=duration',
                '-of', 'json',
                $absolutePath,
            ]);
            $process->setTimeout(20);
            $process->run();

            if (! $process->isSuccessful()) {
                return $empty;
            }

            $data = json_decode($process->getOutput(), true);
            $stream = $data['streams'][0] ?? [];
            $duration = $data['format']['duration'] ?? null;

            return [
                'width' => isset($stream['width']) ? (int) $stream['width'] : null,
                'height' => isset($stream['height']) ? (int) $stream['height'] : null,
                'duration' => is_numeric($duration) ? max(1, (int) round((float) $duration)) : null,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }
}
