<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Contracts\SocialPublisherContract;

/**
 * Contrato específico para drivers de publicação social da NUME.
 * Estende o contrato agnóstico SocialPublisherContract e expõe o identificador do driver.
 */
interface SocialDriverInterface extends SocialPublisherContract
{
    /**
     * Retorna o identificador do driver (ex: 'upload_post', 'meta', 'youtube', 'tiktok').
     */
    public function getName(): string;

    /**
     * Lista as plataformas que este driver é capaz de atender diretamente.
     *
     * @return string[]
     */
    public function supportedPlatforms(): array;
}
