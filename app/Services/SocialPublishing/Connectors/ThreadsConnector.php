<?php

namespace App\Services\SocialPublishing\Connectors;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\OAuthException;
use App\Services\SocialPublishing\OAuth\ThreadsOAuthClient;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;

/**
 * Conecta o perfil do Threads do cliente (token longo de 60 dias, renovado pelo app).
 */
class ThreadsConnector implements SocialConnector
{
    use ManagesOAuthState;

    private const SESSION_KEY = 'social_oauth.threads';

    public function __construct(private readonly ThreadsOAuthClient $threads) {}

    public function provider(): string
    {
        return 'threads';
    }

    public function isConfigured(): bool
    {
        return $this->threads->isConfigured();
    }

    public function start(Cliente $cliente, Session $session): string
    {
        return $this->threads->authorizationUrl($this->putState($session, self::SESSION_KEY, $cliente));
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

        $tokens = $this->threads->exchangeCode($code);
        $profile = $this->threads->profile($tokens['access_token']);

        $connection = SocialConnection::forTenant($cliente->tenant_id)->firstOrNew([
            'cliente_id' => $cliente->id,
            'platform' => 'threads',
            'external_account_id' => $profile['id'],
        ]);

        $connection->fill([
            'tenant_id' => $cliente->tenant_id,
            'provider' => 'threads',
            'handle' => $profile['username'] ? '@'.ltrim($profile['username'], '@') : null,
            'display_name' => $profile['username'],
            'avatar_url' => is_string($profile['picture']) && str_starts_with($profile['picture'], 'https://') && mb_strlen($profile['picture']) <= 500 ? $profile['picture'] : null,
            'access_token' => $tokens['access_token'],
            'refresh_token' => null,
            'expires_at' => now()->addSeconds($tokens['expires_in']),
            'scopes' => config('social.threads.scopes'),
            'status' => SocialConnection::STATUS_ACTIVE,
            'last_refreshed_at' => now(),
            'last_error' => null,
        ])->save();

        Log::info('social.threads.connected', [
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'social_connection_id' => $connection->id,
        ]);

        return ConnectResult::connected([$connection]);
    }

    /**
     * A API do Threads não documenta revogação: apagamos os tokens aqui e a pessoa pode remover o
     * app nas configurações do Threads (explicado em /exclusao-de-dados).
     */
    public function disconnect(SocialConnection $connection): void
    {
        $connection->cancelPendingPublications();

        Log::info('social.threads.disconnected', [
            'tenant_id' => $connection->tenant_id,
            'cliente_id' => $connection->cliente_id,
            'social_connection_id' => $connection->id,
        ]);

        $connection->delete();
    }

    public function successMessage(array $connections): string
    {
        return __('Threads :perfil conectado com sucesso!', ['perfil' => $connections[0]?->handle ?? '']);
    }

    public function failureMessage(string $reason): ?string
    {
        return match ($reason) {
            'access_denied' => __('Você cancelou a autorização no Threads. Nada foi conectado.'),
            'invalid_state' => null,
            default => __('Não foi possível conectar o Threads agora. Tente novamente em alguns minutos.'),
        };
    }
}
