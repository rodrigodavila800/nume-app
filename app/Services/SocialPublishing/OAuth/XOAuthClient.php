<?php

namespace App\Services\SocialPublishing\OAuth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OAuth 2.0 do X (Authorization Code + PKCE S256, cliente confidencial).
 *
 * Access token de 2 h; com offline.access vem um refresh token, que é trocado a cada renovação.
 * Credenciais do app no header Basic, nunca na URL.
 */
class XOAuthClient
{
    public const AUTHORIZE_URL = 'https://x.com/i/oauth2/authorize';

    public const API = 'https://api.x.com/2';

    public function isConfigured(): bool
    {
        return filled(config('services.x.client_id')) && filled(config('services.x.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.x.redirect') ?: route('portal.social.oauth.callback', 'x');
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.x.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', config('social.x.scopes')),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scopes: list<string>}
     */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $codeVerifier,
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scopes: list<string>}
     */
    public function refresh(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function revoke(string $token, string $hint = 'refresh_token'): bool
    {
        try {
            $response = $this->client()->asForm()->timeout(15)->post(self::API.'/oauth2/revoke', ['token' => $token, 'token_type_hint' => $hint]);
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful();
    }

    /**
     * @return array{id: string, username: ?string, name: ?string}
     */
    public function me(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(15)->get(self::API.'/users/me');
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        $id = $response->json('data.id');
        if (! $response->successful() || ! is_string($id) || $id === '') {
            throw new OAuthException(self::errorReason($response, 'profile_lookup_failed'), $response->status());
        }

        return ['id' => $id, 'username' => $response->json('data.username'), 'name' => $response->json('data.name')];
    }

    private function tokenRequest(array $params): array
    {
        try {
            $response = $this->client()->asForm()->acceptJson()->timeout(15)->post(self::API.'/oauth2/token', $params);
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            $reason = self::errorReason($response, 'token_request_failed');

            throw new OAuthException($reason === 'invalid_request' || $response->status() === 400 ? 'invalid_grant' : $reason, $response->status());
        }

        return [
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'expires_in' => (int) ($response->json('expires_in') ?? 7200),
            'scopes' => array_values(array_filter(preg_split('/\s+/', (string) $response->json('scope')))),
        ];
    }

    private function client()
    {
        return Http::withBasicAuth((string) config('services.x.client_id'), (string) config('services.x.client_secret'));
    }

    /**
     * Código curto do erro do X ("error" no OAuth; "type"/"title" nas APIs v2), seguro para log e banco.
     */
    public static function errorReason(Response $response, string $fallback): string
    {
        $error = $response->json('error') ?? $response->json('title');

        if (! is_string($error)) {
            return $fallback;
        }

        $error = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', trim($error)));

        return preg_match('/^[a-z0-9_]{1,60}$/', $error) ? $error : $fallback;
    }
}
