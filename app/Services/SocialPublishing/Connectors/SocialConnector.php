<?php

namespace App\Services\SocialPublishing\Connectors;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\OAuthException;
use Illuminate\Contracts\Session\Session;

/**
 * Conexão de contas de uma rede (ou família de redes) pelo app próprio da NUME.
 * O controller do portal escolhe o conector pela rota (config('social.connectors')).
 */
interface SocialConnector
{
    /** Valor gravado em social_connections.provider pelas contas deste conector. */
    public function provider(): string;

    public function isConfigured(): bool;

    /** URL da tela de autorização da rede; guarda o state na sessão. */
    public function start(Cliente $cliente, Session $session): string;

    /**
     * Retorno da rede. Pode concluir a conexão ou pedir que o cliente escolha a conta.
     *
     * @throws OAuthException
     */
    public function finish(Cliente $cliente, Session $session, ?string $state, ?string $code, ?string $error): ConnectResult;

    public function disconnect(SocialConnection $connection): void;

    /** @param  list<SocialConnection>  $connections */
    public function successMessage(array $connections): string;

    /** Mensagem específica da rede para um código de falha, ou null para a genérica. */
    public function failureMessage(string $reason): ?string;
}
