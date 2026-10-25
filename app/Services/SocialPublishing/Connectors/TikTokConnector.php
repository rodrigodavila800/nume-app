<?php

namespace App\Services\SocialPublishing\Connectors;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\OAuthException;
use App\Services\SocialPublishing\OAuth\TikTokOAuthClient;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\TikTok\TikTokContentApi;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;

/**
 * Conecta a conta do TikTok do cliente (Login Kit + permissão video.publish).
 */
class TikTokConnector implements SocialConnector
{
    use ManagesOAuthState;

    private const SESSION_KEY = 'social_oauth.tiktok';

    public function __construct(
        private readonly TikTokOAuthClient $tiktok,
        private readonly TikTokContentApi $content,
    ) {}

    public function provider(): string
    {
        return 'tiktok';
    }

    public function isConfigured(): bool
    {
        return $this->tiktok->isConfigured();
    }

    public function start(Cliente $cliente, Session $session): string
    {
        return $this->tiktok->authorizationUrl($this->putState($session, self::SESSION_KEY, $cliente));
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

        $tokens = $this->tiktok->exchangeCode($code);

        if (! in_array('video.publish', $tokens['scopes'], true) || ! $tokens['refresh_token'] || ! $tokens['open_id']) {
            $this->tiktok->revoke($tokens['access_token']);
            throw new OAuthException('scope_not_granted');
        }

        try {
            $creator = $this->content->creatorInfo($tokens['access_token']);
        } catch (PublishException $e) {
            throw new OAuthException($e->reason);
        }

        $connection = SocialConnection::forTenant($cliente->tenant_id)->firstOrNew([
            'cliente_id' => $cliente->id,
            'platform' => 'tiktok',
            'external_account_id' => (string) $tokens['open_id'],
        ]);

        $connection->fill([
            'tenant_id' => $cliente->tenant_id,
            'provider' => 'tiktok',
            'handle' => $creator['username'] ? '@'.ltrim($creator['username'], '@') : null,
            'display_name' => $creator['nickname'] ? mb_substr($creator['nickname'], 0, 100) : null,
            // A URL do avatar do TikTok expira em ~2 h: não vale guardar.
            'avatar_url' => null,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => now()->addSeconds($tokens['expires_in']),
            'scopes' => $tokens['scopes'],
            'status' => SocialConnection::STATUS_ACTIVE,
            'last_refreshed_at' => now(),
            'last_error' => null,
        ])->save();

        Log::info('social.tiktok.connected', [
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'social_connection_id' => $connection->id,
        ]);

        return ConnectResult::connected([$connection]);
    }

    public function disconnect(SocialConnection $connection): void
    {
        $revoked = $connection->access_token ? $this->tiktok->revoke($connection->access_token) : true;

        $connection->cancelPendingPublications();

        Log::info('social.tiktok.disconnected', [
            'tenant_id' => $connection->tenant_id,
            'cliente_id' => $connection->cliente_id,
            'social_connection_id' => $connection->id,
            'revoked' => $revoked,
        ]);

        $connection->delete();
    }

    public function successMessage(array $connections): string
    {
        return __('TikTok :perfil conectado com sucesso!', ['perfil' => $connections[0]?->handle ?? '']);
    }

    public function failureMessage(string $reason): ?string
    {
        return match ($reason) {
            'access_denied' => __('Você cancelou a autorização no TikTok. Nada foi conectado.'),
            'scope_not_granted' => __('Para publicar, autorize a publicação de vídeos na tela do TikTok.'),
            'reached_active_user_cap' => __('O TikTok limitou novas contas no app hoje. Tente amanhã.'),
            'invalid_state' => null,
            default => __('Não foi possível conectar o TikTok agora. Tente novamente em alguns minutos.'),
        };
    }
}
