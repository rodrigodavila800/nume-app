<?php

namespace App\Services\SocialPublishing;

use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\GoogleOAuthClient;
use App\Services\SocialPublishing\OAuth\OAuthException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Entrega um access token válido da conta conectada, renovando quando está para vencer.
 * A renovação roda sob lock por conta, para dois jobs não gastarem o mesmo refresh token.
 */
class SocialTokenManager
{
    public function __construct(private readonly GoogleOAuthClient $google) {}

    /**
     * @throws PublishException
     */
    public function accessToken(SocialConnection $connection, bool $forceRefresh = false): string
    {
        if (! $connection->isActive()) {
            throw new PublishException('reconnect_required');
        }

        if (! $forceRefresh && ! $connection->expiresWithin(5)) {
            return $connection->access_token;
        }

        return Cache::lock("social-token-refresh:{$connection->id}", 30)->block(20, function () use ($connection, $forceRefresh) {
            $connection->refresh();

            // Outro processo pode ter renovado enquanto esperávamos o lock.
            if (! $forceRefresh && $connection->isActive() && ! $connection->expiresWithin(5)) {
                return $connection->access_token;
            }

            return $this->refresh($connection);
        });
    }

    /**
     * @throws PublishException
     */
    public function refresh(SocialConnection $connection): string
    {
        if ($connection->provider !== 'google' || ! $connection->refresh_token) {
            $connection->markFailed('missing_refresh_token', SocialConnection::STATUS_EXPIRED);
            throw new PublishException('reconnect_required');
        }

        try {
            $tokens = $this->google->refresh($connection->refresh_token);
        } catch (OAuthException $e) {
            Log::warning('social.token.refresh_failed', [
                'tenant_id' => $connection->tenant_id,
                'social_connection_id' => $connection->id,
                'reason' => $e->reason,
            ]);

            if ($e->requiresReconnect()) {
                $connection->markFailed($e->reason, SocialConnection::STATUS_EXPIRED);
                throw new PublishException('reconnect_required');
            }

            throw new PublishException('token_refresh_failed', retryable: true);
        }

        $connection->forceFill([
            'access_token' => $tokens['access_token'],
            // O Google às vezes devolve um refresh token novo; senão o atual continua valendo.
            'refresh_token' => $tokens['refresh_token'] ?: $connection->refresh_token,
            'expires_at' => now()->addSeconds($tokens['expires_in']),
            'scopes' => $tokens['scopes'] ?: $connection->scopes,
            'status' => SocialConnection::STATUS_ACTIVE,
            'last_refreshed_at' => now(),
            'last_error' => null,
        ])->save();

        return $connection->access_token;
    }
}
