<?php

namespace App\Services\SocialPublishing\Connectors;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\LinkedInOAuthClient;
use App\Services\SocialPublishing\OAuth\OAuthException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;

/**
 * Conecta o perfil pessoal do LinkedIn do cliente (w_member_social). Páginas de empresa ficam
 * para quando o app tiver a Community Management API.
 */
class LinkedInConnector implements SocialConnector
{
    use ManagesOAuthState;

    private const SESSION_KEY = 'social_oauth.linkedin';

    public function __construct(private readonly LinkedInOAuthClient $linkedin) {}

    public function provider(): string
    {
        return 'linkedin';
    }

    public function isConfigured(): bool
    {
        return $this->linkedin->isConfigured();
    }

    public function start(Cliente $cliente, Session $session): string
    {
        return $this->linkedin->authorizationUrl($this->putState($session, self::SESSION_KEY, $cliente));
    }

    public function finish(Cliente $cliente, Session $session, ?string $state, ?string $code, ?string $error): ConnectResult
    {
        $this->pullState($session, self::SESSION_KEY, $cliente, $state);

        if ($error !== null) {
            throw new OAuthException(in_array($error, ['user_cancelled_login', 'user_cancelled_authorize', 'access_denied'], true) ? 'access_denied' : 'authorization_failed');
        }

        if (! is_string($code) || $code === '') {
            throw new OAuthException('missing_code');
        }

        $tokens = $this->linkedin->exchangeCode($code);

        if (! in_array('w_member_social', $tokens['scopes'], true)) {
            throw new OAuthException('scope_not_granted');
        }

        $profile = $this->linkedin->userinfo($tokens['access_token']);

        $connection = SocialConnection::forTenant($cliente->tenant_id)->firstOrNew([
            'cliente_id' => $cliente->id,
            'platform' => 'linkedin',
            'external_account_id' => $profile['id'],
        ]);

        $connection->fill([
            'tenant_id' => $cliente->tenant_id,
            'provider' => 'linkedin',
            // O LinkedIn não tem @; o nome do perfil identifica a conta na tela.
            'handle' => null,
            'display_name' => $profile['name'] ? mb_substr($profile['name'], 0, 100) : null,
            // A foto do LinkedIn vem em URL assinada que expira: não vale guardar.
            'avatar_url' => null,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => now()->addSeconds($tokens['expires_in']),
            'scopes' => $tokens['scopes'],
            'status' => SocialConnection::STATUS_ACTIVE,
            'last_refreshed_at' => now(),
            'last_error' => null,
        ])->save();

        Log::info('social.linkedin.connected', [
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'social_connection_id' => $connection->id,
        ]);

        return ConnectResult::connected([$connection]);
    }

    /**
     * O LinkedIn não documenta revogação de token para este produto: apagamos os tokens aqui e a
     * pessoa pode remover o app em Configurações > Privacidade de dados > Serviços permitidos.
     */
    public function disconnect(SocialConnection $connection): void
    {
        $connection->cancelPendingPublications();

        Log::info('social.linkedin.disconnected', [
            'tenant_id' => $connection->tenant_id,
            'cliente_id' => $connection->cliente_id,
            'social_connection_id' => $connection->id,
        ]);

        $connection->delete();
    }

    public function successMessage(array $connections): string
    {
        return __('LinkedIn de :perfil conectado com sucesso!', ['perfil' => $connections[0]?->display_name ?? '']);
    }

    public function failureMessage(string $reason): ?string
    {
        return match ($reason) {
            'access_denied' => __('Você cancelou a autorização no LinkedIn. Nada foi conectado.'),
            'scope_not_granted' => __('Para publicar, autorize o compartilhamento de posts na tela do LinkedIn.'),
            'invalid_state' => null,
            default => __('Não foi possível conectar o LinkedIn agora. Tente novamente em alguns minutos.'),
        };
    }
}
