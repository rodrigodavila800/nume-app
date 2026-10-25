<?php

namespace App\Services\SocialPublishing\Connectors;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\OAuthException;
use App\Services\SocialPublishing\OAuth\XOAuthClient;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Conecta a conta do X do cliente (OAuth 2.0 + PKCE). Cada post é cobrado da conta de
 * desenvolvedor da NUME (API paga por uso).
 */
class XConnector implements SocialConnector
{
    use ManagesOAuthState;

    private const SESSION_KEY = 'social_oauth.x';

    private const REQUIRED_SCOPES = ['tweet.write', 'media.write', 'offline.access'];

    public function __construct(private readonly XOAuthClient $x) {}

    public function provider(): string
    {
        return 'x';
    }

    public function isConfigured(): bool
    {
        return $this->x->isConfigured();
    }

    public function start(Cliente $cliente, Session $session): string
    {
        $verifier = Str::random(96);
        $state = $this->putState($session, self::SESSION_KEY, $cliente, ['verifier' => $verifier]);

        return $this->x->authorizationUrl($state, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='));
    }

    public function finish(Cliente $cliente, Session $session, ?string $state, ?string $code, ?string $error): ConnectResult
    {
        $pending = $this->pullState($session, self::SESSION_KEY, $cliente, $state);

        if ($error !== null) {
            throw new OAuthException($error === 'access_denied' ? 'access_denied' : 'authorization_failed');
        }

        if (! is_string($code) || $code === '') {
            throw new OAuthException('missing_code');
        }

        $tokens = $this->x->exchangeCode($code, $pending['verifier']);

        if (array_diff(self::REQUIRED_SCOPES, $tokens['scopes']) !== [] || ! $tokens['refresh_token']) {
            $this->x->revoke($tokens['access_token'], 'access_token');
            throw new OAuthException('scope_not_granted');
        }

        $me = $this->x->me($tokens['access_token']);

        $connection = SocialConnection::forTenant($cliente->tenant_id)->firstOrNew([
            'cliente_id' => $cliente->id,
            'platform' => 'x',
            'external_account_id' => $me['id'],
        ]);

        $connection->fill([
            'tenant_id' => $cliente->tenant_id,
            'provider' => 'x',
            'handle' => $me['username'] ? '@'.ltrim($me['username'], '@') : null,
            'display_name' => $me['name'] ? mb_substr($me['name'], 0, 100) : null,
            'avatar_url' => null,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => now()->addSeconds($tokens['expires_in']),
            'scopes' => $tokens['scopes'],
            'status' => SocialConnection::STATUS_ACTIVE,
            'last_refreshed_at' => now(),
            'last_error' => null,
        ])->save();

        Log::info('social.x.connected', [
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'social_connection_id' => $connection->id,
        ]);

        return ConnectResult::connected([$connection]);
    }

    public function disconnect(SocialConnection $connection): void
    {
        $revoked = $connection->refresh_token ? $this->x->revoke($connection->refresh_token) : true;

        $connection->cancelPendingPublications();

        Log::info('social.x.disconnected', [
            'tenant_id' => $connection->tenant_id,
            'cliente_id' => $connection->cliente_id,
            'social_connection_id' => $connection->id,
            'revoked' => $revoked,
        ]);

        $connection->delete();
    }

    public function successMessage(array $connections): string
    {
        return __('X :perfil conectado com sucesso!', ['perfil' => $connections[0]?->handle ?? '']);
    }

    public function failureMessage(string $reason): ?string
    {
        return match ($reason) {
            'access_denied' => __('Você cancelou a autorização no X. Nada foi conectado.'),
            'scope_not_granted' => __('Para publicar, autorize a criação de posts e o envio de mídia na tela do X.'),
            'invalid_state' => null,
            default => __('Não foi possível conectar o X agora. Tente novamente em alguns minutos.'),
        };
    }
}
