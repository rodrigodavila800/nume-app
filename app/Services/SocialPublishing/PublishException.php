<?php

namespace App\Services\SocialPublishing;

use RuntimeException;

/**
 * Falha de publicação nativa. A mensagem é só um código curto (vai para last_error e log),
 * nunca token, URL de sessão de upload ou corpo de resposta.
 *
 * retryable = vale tentar de novo mais tarde (rede, 5xx, 429); senão a falha é definitiva
 * (conta desconectada, cota do dia, metadados recusados, arquivo ausente).
 */
class PublishException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly bool $retryable = false)
    {
        parent::__construct($reason);
    }
}
