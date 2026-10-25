<?php

namespace App\Services\SocialPublishing\Connectors;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\OAuthException;
use App\Services\SocialPublishing\OAuth\PinterestOAuthClient;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;

/**
 * Conecta a conta do Pinterest do cliente (pins em pastas públicas).
 */
class PinterestConnector implements SocialConnector
{
    use ManagesOAuthState;

    private const SESSION_KEY = 'social_oauth.pinterest';

    public function __construct(private readonly PinterestOAuthClient $pinterest) {}

    public function provider(): string
    {
        return 'pinterest';
    }

    public function isConfigured(): bool
    {
        return $this->pinterest->isConfigured();
    }

    public function start(Cliente $cliente, Session $session): string
    {
        return $this->pinterest->authorizationUrl($this->putState($session, self::SESSION_KEY, $cliente));
    }

    public function finish(Cliente $cliente, Session $session, ?string $state, ?string $code, ?string $error): ConnectResult
    {
        $this->pullState($session, self::SESSION_KEY, $cliente, $state);

        if ($error !== null) {
            throw new OAuthException($error === 'access_denied' ? 'access_denied' : 'authorization_failed');
        }

        if (! is_string($code) || $code === '') {
            throw new OAuthException('missing_code');
        }

        $tokens = $this->pinterest->exchangeCode($code);

        if (array_diff(['pins:write', 'boards:read'], $tokens['scopes']) !== [] || ! $tokens['refresh_token']) {
            throw new OAuthException('scope_not_granted');
        }

        $account = $this->pinterest->account($tokens['access_token']);

        $connection = SocialConnection::forTenant($cliente->tenant_id)->firstOrNew([
            'cliente_id' => $cliente->id,
            'platform' => 'pinterest',
            'external_account_id' => $account['id'],
        ]);

        $connection->fill([
            'tenant_id' => $cliente->tenant_id,
            'provider' => 'pinterest',
            'handle' => $account['username'] ? '@'.ltrim($account['username'], '@') : null,
            'display_name' => $account['username'],
            'avatar_url' => null,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => now()->addSeconds($tokens['expires_in']),
            'scopes' => $tokens['scopes'],
            'status' => SocialConnection::STATUS_ACTIVE,
            'last_refreshed_at' => now(),
            'last_error' => null,
        ])->save();

        Log::info('social.pinterest.connected', [
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'social_connection_id' => $connection->id,
        ]);

        return ConnectResult::connected([$connection]);
    }

    /**
     * A API v5 não documenta revogação de token pelo app: apagamos os tokens aqui e a pessoa pode
     * remover o app nas configurações de segurança do Pinterest (explicado em /exclusao-de-dados).
     */
    public function disconnect(SocialConnection $connection): void
    {
        $connection->cancelPendingPublications();

        Log::info('social.pinterest.disconnected', [
            'tenant_id' => $connection->tenant_id,
            'cliente_id' => $connection->cliente_id,
            'social_connection_id' => $connection->id,
        ]);

        $connection->delete();
    }

    public function successMessage(array $connections): string
    {
        return __('Pinterest :perfil conectado com sucesso!', ['perfil' => $connections[0]?->handle ?? '']);
    }

    public function failureMessage(string $reason): ?string
    {
        return match ($reason) {
            'access_denied' => __('Você cancelou a autorização no Pinterest. Nada foi conectado.'),
            'scope_not_granted' => __('Para publicar, autorize a leitura das pastas e a criação de pins na tela do Pinterest.'),
            'invalid_state' => null,
            default => __('Não foi possível conectar o Pinterest agora. Tente novamente em alguns minutos.'),
        };
    }
}
