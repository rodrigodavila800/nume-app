<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega do arquivo de vídeo hospedado na NUME.
 *
 * - A rota é assinada (URL temporária gerada só para quem pode ver o vídeo).
 * - Reprodução ("inline") fica liberada com a assinatura, inclusive com o ativo em custódia
 *   (o cliente precisa assistir o corte para aprová-lo).
 * - Download exige login do dono e saldo quitado (custódia 50/50); a equipe da produtora sempre pode baixar.
 */
class VideoMediaController extends Controller
{
    public function show(Request $request, int $video): Response
    {
        // A assinatura da URL é o controle de acesso: o scope de tenant não deve interferir
        // (ex.: Meta/hub buscando o arquivo sem sessão).
        $video = Video::withoutGlobalScope('tenant')->findOrFail($video);

        abort_unless($video->sourceFileExists(), 404);

        $download = $request->boolean('download');

        if ($download) {
            $this->authorizeDownload($video);
        }

        $disk = Storage::disk($video->source_disk);
        $headers = [
            'Content-Type' => $video->source_mime ?: 'video/mp4',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];

        if (config("filesystems.disks.{$video->source_disk}.driver") !== 'local') {
            return $disk->response($video->source_path, $download ? $this->downloadName($video) : null, $headers, $download ? 'attachment' : 'inline');
        }

        // BinaryFileResponse atende "Range" (avançar/voltar no player e downloads retomáveis).
        return $download
            ? $this->downloadResponse($disk->path($video->source_path), $video, $headers)
            : response()->file($disk->path($video->source_path), $headers + ['Content-Disposition' => 'inline']);
    }

    private function downloadResponse(string $path, Video $video, array $headers): BinaryFileResponse
    {
        return response()->download($path, $this->downloadName($video), $headers);
    }

    private function authorizeDownload(Video $video): void
    {
        $staff = auth('web')->user();
        if ($staff && $staff->tenant_id === $video->tenant_id) {
            return;
        }

        $cliente = auth('cliente')->user();
        $ownsVideo = $cliente
            && $cliente->tenant_id === $video->tenant_id
            && $video->production?->cliente_id === $cliente->id;

        abort_unless($ownsVideo, 403, __('Acesso não autorizado a este vídeo.'));
        abort_if($video->isAssetLocked(), 403, __('Download bloqueado: o saldo do pacote ainda não foi quitado.'));
    }

    private function downloadName(Video $video): string
    {
        $extension = pathinfo((string) $video->source_path, PATHINFO_EXTENSION) ?: 'mp4';

        return str($video->title ?: 'video')->slug()->limit(80, '')->toString().'.'.$extension;
    }
}
