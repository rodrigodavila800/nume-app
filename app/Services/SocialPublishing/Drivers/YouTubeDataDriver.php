<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\Cliente;

/**
 * YouTube pelo app próprio da NUME.
 *
 * A conexão é feita em YouTubeConnector (OAuth + PKCE, por cliente) e o envio do arquivo
 * pelo job de publicação nativa, nunca por URL digitada nem por token global. Por isso esta
 * interface só reflete o estado real das contas conectadas.
 */
class YouTubeDataDriver extends PendingNativeDriver
{
    public function getName(): string
    {
        return 'youtube_data';
    }

    public function supportedPlatforms(): array
    {
        return ['youtube'];
    }

    public function generateConnectUrl(Cliente $cliente, ?string $redirectUrl = null): string
    {
        return route('portal.social.oauth.redirect', 'youtube');
    }

    protected function profilePrefix(): string
    {
        return 'yt';
    }
}
