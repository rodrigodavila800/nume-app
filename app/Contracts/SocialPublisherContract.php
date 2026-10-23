<?php

namespace App\Contracts;

use App\Models\Cliente;
use App\Models\SocialProfile;
use App\Models\Video;
use App\Models\VideoPublication;

use App\Models\User;

/**
 * Contrato agnóstico para provedores de distribuição social.
 *
 * Permite alternar entre o provedor Upload-Post e os drivers nativos
 * da NUME (Meta Graph API, YouTube Data API, TikTok Open API) com zero impacto
 * no código de controllers, views ou regras de negócio.
 */
interface SocialPublisherContract
{
    /**
     * Garante a criação do perfil do cliente no provedor.
     */
    public function ensureProfile(Cliente $cliente): SocialProfile;

    /**
     * Gera a URL segura para conexão das contas sociais do cliente.
     */
    public function generateConnectUrl(Cliente $cliente, ?string $redirectUrl = null): string;

    /**
     * Sincroniza o status das plataformas conectadas server-to-server.
     */
    public function syncConnectionStatus(Cliente $cliente): SocialProfile;

    /**
     * Dispara a publicação do vídeo nas redes sociais especificadas.
     *
     * @param  string[]  $platforms
     */
    public function publish(
        Cliente $cliente,
        string $videoUrl,
        string $title,
        array $platforms,
        ?Video $video = null,
        ?User $user = null,
        ?\DateTimeInterface $scheduledAt = null
    ): VideoPublication;

    /**
     * Consulta e atualiza o status de uma publicação assíncrona.
     */
    public function refreshStatus(VideoPublication $publication): VideoPublication;

    /**
     * Processa e dispara publicações agendadas cujo horário já foi atingido.
     *
     * @return int Quantidade de publicações disparadas
     */
    public function processScheduled(): int;
}
