<?php

namespace App\Services\SocialPublishing\OAuth;

use RuntimeException;

/**
 * Falha de OAuth com a rede. A mensagem é só um código curto (ex.: invalid_grant, network):
 * nunca inclui token, código de autorização ou corpo da resposta, para poder ir para log e banco.
 */
class OAuthException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?int $status = null)
    {
        parent::__construct($reason);
    }

    /**
     * O token de atualização não vale mais (revogado pelo usuário, senha trocada, app em teste há 7 dias).
     */
    public function requiresReconnect(): bool
    {
        return in_array($this->reason, ['invalid_grant', 'unauthorized_client', 'missing_refresh_token'], true);
    }
}
