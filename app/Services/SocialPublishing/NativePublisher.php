<?php

namespace App\Services\SocialPublishing;

use App\Models\VideoPublication;

/**
 * Publicador de UMA rede pelo app próprio da NUME. O job PublishVideoToPlatform escolhe o
 * publicador pelo driver da publicação (config('social.native_publishers')), então fila,
 * tentativas, backoff e cofre de tokens são os mesmos para todas as redes.
 */
interface NativePublisher
{
    /**
     * Envia o vídeo hospedado da publicação para a rede.
     *
     * @return array{id: string, url: string} identificador e link do post na rede
     *
     * @throws PublishException
     */
    public function publish(VideoPublication $publication): array;
}
