<?php

namespace App\Services\SocialPublishing;

use DomainException;

/**
 * Envio ou conexão recusados pelos limites do plano (redes liberadas ou envios do mês).
 * A mensagem já é traduzida e pode ir para a tela.
 */
class PublishingLimitException extends DomainException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
