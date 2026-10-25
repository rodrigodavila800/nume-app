<?php

namespace App\Services\SocialPublishing;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Services\SocialPublishing\Connectors\ConnectResult;
use App\Services\SocialPublishing\Connectors\ManagesOAuthState;
use App\Services\SocialPublishing\Connectors\SocialConnector;
use App\Services\SocialPublishing\OAuth\GoogleOAuthClient;
use App\Services\SocialPublishing\OAuth\OAuthException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Conecta o canal do YouTube do cliente pelo app próprio da NUME.
 *
 * O state (anti-CSRF) e o code_verifier (PKCE) ficam só na sessão, presos ao cliente logado,
 * e valem uma única vez. O tenant vem sempre do cliente, nunca da requisição.
 */
class YouTubeConnector implements SocialConnector
{
    use ManagesOAuthState;

    private const SESSION_KEY = 'social_oauth.youtube';

    public function __construct(private readonly GoogleOAuthClient $google) {}

    public function provider(): string
    {
        return 'google';
    }

    public function isConfigured(): bool
    {
        return $this->google->isConfigured();
    }

    public function start(Cliente $cliente, Session $session): string
    {
        $verifier = Str::random(96);
        $state = $this->putState($session, self::SESSION_KEY, $cliente, ['verifier' => $verifier]);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return $this->google->authorizationUrl($state, $challenge);
    }

    /**
     * Conclui o retorno do Google. Erros viram OAuthException com código curto.
     */
    public function finish(Cliente $cliente, Session $session, ?string $state, ?string $code, ?string $error): ConnectResult
    {
        $pending = $this->pullState($session, self::SESSION_KEY, $cliente, $state);

        if ($error !== null) {
            throw new OAuthException($error === 'access_denied' ? 'access_denied' : 'authorization_failed');
        }

        if (! is_string($code) || $code === '') {
            throw new OAuthException('missing_code');
        }

        $tokens = $this->google->exchangeCode($code, $pending['verifier']);

        if (! in_array('https://www.googleapis.com/auth/youtube.upload', $tokens['scopes'], true)) {
            $this->google->revoke($tokens['access_token']);
            throw new OAuthException('scope_not_granted');
        }

        $channel = $this->google->fetchOwnChannel($tokens['access_token']);
        if ($channel === null) {
            $this->google->revoke($tokens['access_token']);
            throw new OAuthException('no_channel');
        }

        $connection = SocialConnection::forTenant($cliente->tenant_id)->firstOrNew([
            'cliente_id' => $cliente->id,
            'platform' => 'youtube',
            'external_account_id' => $channel['id'],
        ]);

        // O Google só reenvia o refresh_token com prompt=consent; se faltar, mantém o que já existe.
        $refreshToken = $tokens['refresh_token'] ?: $connection->refresh_token;
        if (! $refreshToken) {
            $this->google->revoke($tokens['access_token']);
            throw new OAuthException('missing_refresh_token');
        }

        $connection->fill([
            'tenant_id' => $cliente->tenant_id,
            'provider' => 'google',
            'handle' => $channel['handle'],
            'display_name' => $channel['title'] ? Str::limit($channel['title'], 97) : null,
            'avatar_url' => $channel['avatar'] && mb_strlen($channel['avatar']) <= 500 ? $channel['avatar'] : null,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $refreshToken,
            'expires_at' => now()->addSeconds($tokens['expires_in']),
            'scopes' => $tokens['scopes'],
            'status' => SocialConnection::STATUS_ACTIVE,
            'last_refreshed_at' => now(),
            'last_error' => null,
        ])->save();

        Log::info('social.youtube.connected', [
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'social_connection_id' => $connection->id,
        ]);

        return ConnectResult::connected([$connection]);
    }

    /**
     * Desconecta: revoga no Google (o refresh token derruba também os access tokens) e apaga os
     * tokens daqui mesmo se a revogação falhar. Envios nativos ainda não feitos são cancelados.
     */
    public function disconnect(SocialConnection $connection): void
    {
        $token = $connection->refresh_token ?: $connection->access_token;
        $revoked = $token ? $this->google->revoke($token) : true;

        $connection->cancelPendingPublications();

        Log::info('social.youtube.disconnected', [
            'tenant_id' => $connection->tenant_id,
            'cliente_id' => $connection->cliente_id,
            'social_connection_id' => $connection->id,
            'revoked' => $revoked,
        ]);

        $connection->delete();
    }

    public function successMessage(array $connections): string
    {
        $connection = $connections[0] ?? null;

        return __('Canal do YouTube :canal conectado com sucesso!', ['canal' => $connection?->handle ?? $connection?->display_name ?? '']);
    }

    public function failureMessage(string $reason): ?string
    {
        return match ($reason) {
            'access_denied' => __('Você cancelou a autorização no Google. Nada foi conectado.'),
            'scope_not_granted' => __('Para publicar, autorize o envio de vídeos ao seu canal na tela do Google.'),
            'no_channel' => __('Esta conta Google não tem um canal do YouTube. Crie o canal e tente de novo.'),
            'invalid_state' => null,
            default => __('Não foi possível conectar o YouTube agora. Tente novamente em alguns minutos.'),
        };
    }
}
