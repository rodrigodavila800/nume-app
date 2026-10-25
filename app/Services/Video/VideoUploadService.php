<?php

namespace App\Services\Video;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Grava o vídeo enviado no disco privado da NUME e devolve os atributos `source_*` do registro.
 *
 * O tipo é conferido pelo CONTEÚDO do arquivo (finfo), nunca pelo que o navegador declarou,
 * e o nome no disco é gerado aqui (uuid): o nome original nunca vira caminho.
 */
class VideoUploadService
{
    public function __construct(private readonly VideoMetadata $metadata) {}

    /**
     * @return array<string, mixed>
     */
    public function store(UploadedFile $file, int $tenantId): array
    {
        $mime = (string) $file->getMimeType();
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));

        if (! in_array($mime, config('social.video.allowed_mimes', []), true)
            || ! in_array($extension, config('social.video.allowed_extensions', []), true)) {
            throw new DomainException('video_type_not_allowed');
        }

        $size = (int) $file->getSize();
        $hash = (string) hash_file('sha256', $file->getRealPath());
        $diskName = (string) config('social.video.disk', 'videos');
        $disk = Storage::disk($diskName);

        $path = $disk->putFileAs((string) $tenantId, $file, Str::uuid()->toString().'.'.$extension);

        if ($path === false) {
            throw new RuntimeException('video_upload_failed');
        }

        // Metadados só quando o disco é local (o ffprobe precisa de um caminho no servidor).
        $meta = config("filesystems.disks.{$diskName}.driver") === 'local'
            ? $this->metadata->read($disk->path($path))
            : ['width' => null, 'height' => null, 'duration' => null];

        return array_filter([
            'source_disk' => $diskName,
            'source_path' => $path,
            'source_mime' => $mime,
            'source_size' => $size,
            'source_width' => $meta['width'],
            'source_height' => $meta['height'],
            'source_sha256' => $hash,
            'duration_seconds' => $meta['duration'],
        ], fn ($value) => $value !== null);
    }

    /**
     * Atributos que limpam o arquivo de um vídeo (usado ao remover ou substituir).
     *
     * @return array<string, null>
     */
    public static function clearedAttributes(): array
    {
        return [
            'source_disk' => null,
            'source_path' => null,
            'source_mime' => null,
            'source_size' => null,
            'source_width' => null,
            'source_height' => null,
            'source_sha256' => null,
        ];
    }
}
