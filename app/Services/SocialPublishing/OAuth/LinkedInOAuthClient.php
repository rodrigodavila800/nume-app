<?php

namespace App\Services\SocialPublishing\OAuth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OAuth 2.0 do LinkedIn (3-legged) com Sign In with LinkedIn (OpenID Connect).
 *
 * O access token vale 60 dias. Refresh token só existe para parceiros aprovados; sem ele, o
 * cliente reconecta (se ainda estiver logado no LinkedIn, a tela de consentimento é pulada).
 * Segredo só no corpo form-urlencoded, nunca na URL.
 */
class LinkedInOAuthClient
{
    public const AUTHORIZE_URL = 'https://www.linkedin.com/oauth/v2/authorization';

    public const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

    public const USERINFO_URL = 'https://api.linkedin.com/v2/userinfo';

    public function isConfigured(): bool
    {
        return filled(config('services.linkedin.client_id')) && filled(config('services.linkedin.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.linkedin.redirect') ?: route('portal.social.oauth.callback', 'linkedin');
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.linkedin.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'scope' => implode(' ', config('social.linkedin.scopes')),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scopes: list<string>}
     */
    public function exchangeCode(string $code): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);
    }

    /**
     * Só para apps com refresh programático liberado pelo LinkedIn.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scopes: list<string>}
     */
    public function refresh(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * @return array{id: string, name: ?string}
     */
    public function userinfo(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(15)->get(self::USERINFO_URL);
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        if (! $response->successful() || ! is_string($response->json('sub')) || $response->json('sub') === '') {
            throw new OAuthException(self::errorReason($response, 'profile_lookup_failed'), $response->status());
        }

        return ['id' => $response->json('sub'), 'name' => $response->json('name')];
    }

    private function tokenRequest(array $params): array
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(15)->post(self::TOKEN_URL, array_merge([
                'client_id' => config('services.linkedin.client_id'),
                'client_secret' => config('services.linkedin.client_secret'),
            ], $params));
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw new OAuthException(self::errorReason($response, 'token_request_failed'), $response->status());
        }

        return [
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'expires_in' => (int) ($response->json('expires_in') ?? 5184000),
            'scopes' => array_values(array_filter(preg_split('/[\s,]+/', (string) $response->json('scope')))),
        ];
    }

    /**
     * Código curto do erro do LinkedIn ("error" no OAuth, "code" nas APIs REST).
     */
    public static function errorReason(Response $response, string $fallback): string
    {
        $error = $response->json('error') ?? $response->json('code') ?? $response->json('serviceErrorCode');

        return (is_string($error) || is_int($error)) && preg_match('/^[A-Za-z0-9_.-]{1,60}$/', (string) $error) ? (string) $error : $fallback;
    }
}
