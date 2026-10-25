<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Video;
use App\Services\SocialPublishing\SocialPublisherRouter;

/**
 * Regras de acesso das telas de publicação próprias (TikTok, Pinterest), iguais às do envio pelo
 * portal: vídeo do cliente, arquivo hospedado, saldo quitado, cota disponível e conta conectada direto.
 */
trait GuardsComposerPublishing
{
    protected function guardComposer(Cliente $cliente, Video $video, string $platform, SocialPublisherRouter $router): SocialConnection
    {
        abort_unless($video->production?->cliente_id === $cliente->id, 403);
        abort_unless($video->hasSourceFile(), 404);
        abort_if($video->isAssetLocked(), 403, __('Envio bloqueado: o saldo do pacote ainda não foi quitado.'));
        abort_unless($cliente->canDistributeSocial(), 403, __('Você atingiu o limite de publicações do seu plano.'));
        abort_unless($router->routeFor($platform) === SocialPublisherRouter::NATIVE, 404);

        $connection = $cliente->activeSocialConnection($platform);
        abort_if($connection === null, 404);

        return $connection;
    }
}
