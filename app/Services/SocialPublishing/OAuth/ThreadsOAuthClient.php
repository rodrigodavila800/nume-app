<?php

namespace App\Services\SocialPublishing\OAuth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OAuth do Threads (threads.com / graph.threads.com).
 *
 * Código -> token curto (1 h) -> token longo (60 dias), renovável quando tem mais de 24 h e ainda
 * não venceu. A troca e a renovação usam GET com o token na query porque é o único formato
 * documentado pela Meta: nunca usamos ->throw() nem logamos a URL dessas chamadas.
 */
class ThreadsOAuthClient
{
    public const AUTHORIZE_URL = 'https://threads.com/oauth/authorize';

    public const GRAPH_HOST = 'https://graph.threads.com';

    public function isConfigured(): bool
    {
        return filled(config('services.threads.client_id')) && filled(config('services.threads.client_secret'));
    }

    public function apiUrl(string $path): string
    {
        return self::GRAPH_HOST.'/'.config('social.threads.api_version', 'v1.0').'/'.ltrim($path, '/');
    }

    public function redirectUri(): string
    {
        return config('services.threads.redirect') ?: route('portal.social.oauth.callback', 'threads');
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.threads.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(',', config('social.threads.scopes')),
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    /**
     * @return array{access_token: string, expires_in: int, user_id: ?string}
     */
    public function exchangeCode(string $code): array
    {
        $short = $this->call(fn () => Http::asForm()->acceptJson()->timeout(15)->post(self::GRAPH_HOST.'/oauth/access_token', [
            'client_id' => config('services.threads.client_id'),
            'client_secret' => config('services.threads.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]));

        $long = $this->call(fn () => Http::acceptJson()->timeout(15)->get(self::GRAPH_HOST.'/access_token', [
            'grant_type' => 'th_exchange_token',
            'client_secret' => config('services.threads.client_secret'),
            'access_token' => $short['access_token'],
        ]));

        return [
            'access_token' => $long['access_token'],
            'expires_in' => (int) ($long['expires_in'] ?? 5184000),
            'user_id' => isset($short['user_id']) ? (string) $short['user_id'] : null,
        ];
    }

    /**
     * @return array{access_token: string, expires_in: int}
     */
    public function refresh(string $accessToken): array
    {
        $refreshed = $this->call(fn () => Http::acceptJson()->timeout(15)->get(self::GRAPH_HOST.'/refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $accessToken,
        ]));

        return [
            'access_token' => $refreshed['access_token'],
            'expires_in' => (int) ($refreshed['expires_in'] ?? 5184000),
        ];
    }

    /**
     * @return array{id: string, username: ?string, picture: ?string}
     */
    public function profile(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(15)
                ->get($this->apiUrl('me'), ['fields' => 'id,username,threads_profile_picture_url']);
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        if (! $response->successful() || empty($response->json('id'))) {
            throw new OAuthException(MetaOAuthClient::errorReason($response, 'profile_lookup_failed'), $response->status());
        }

        return [
            'id' => (string) $response->json('id'),
            'username' => $response->json('username'),
            'picture' => $response->json('threads_profile_picture_url'),
        ];
    }

    /**
     * @param  callable(): Response  $request
     */
    private function call(callable $request): array
    {
        try {
            $response = $request();
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            $reason = MetaOAuthClient::errorReason($response, 'token_request_failed');

            // Token vencido ou revogado: só reconectando.
            throw new OAuthException($reason === 'meta_190' || str_starts_with($reason, 'meta_190_') ? 'invalid_grant' : $reason, $response->status());
        }

        return (array) $response->json();
    }
}
