<?php

namespace App\Services\SocialPublishing\OAuth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OAuth do Pinterest (API v5).
 *
 * Access token de 30 dias e refresh token contínuo de 60 dias, renovável indefinidamente.
 * A troca usa HTTP Basic (client_id:client_secret); nada de segredo na URL.
 */
class PinterestOAuthClient
{
    public const AUTHORIZE_URL = 'https://www.pinterest.com/oauth/';

    public function isConfigured(): bool
    {
        return filled(config('services.pinterest.client_id')) && filled(config('services.pinterest.client_secret'));
    }

    public function apiUrl(string $path): string
    {
        return rtrim((string) config('social.pinterest.api_base', 'https://api.pinterest.com/v5'), '/').'/'.ltrim($path, '/');
    }

    public function redirectUri(): string
    {
        return config('services.pinterest.redirect') ?: route('portal.social.oauth.callback', 'pinterest');
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.pinterest.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(',', config('social.pinterest.scopes')),
            'state' => $state,
        ]);
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
            // Apps anteriores a 25/09/2025 precisam pedir o refresh contínuo; os novos ignoram.
            'continuous_refresh' => 'true',
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

    /**
     * @return array{id: string, username: ?string}
     */
    public function account(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(15)->get($this->apiUrl('user_account'));
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        $id = $response->json('id') ?? $response->json('username');

        if (! $response->successful() || ! is_string($id) || $id === '') {
            throw new OAuthException(self::errorReason($response, 'profile_lookup_failed'), $response->status());
        }

        return ['id' => $id, 'username' => $response->json('username')];
    }

    private function tokenRequest(array $params): array
    {
        try {
            $response = Http::withBasicAuth((string) config('services.pinterest.client_id'), (string) config('services.pinterest.client_secret'))
                ->asForm()->acceptJson()->timeout(15)
                ->post($this->apiUrl('oauth/token'), $params);
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            $reason = self::errorReason($response, 'token_request_failed');

            // Refresh vencido ou revogado: só reconectando.
            throw new OAuthException($response->status() === 400 || $response->status() === 401 ? 'invalid_grant' : $reason, $response->status());
        }

        return [
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'expires_in' => (int) ($response->json('expires_in') ?? 2592000),
            'scopes' => array_values(array_filter(preg_split('/[\s,]+/', (string) $response->json('scope')))),
        ];
    }

    /**
     * Código do erro do Pinterest ({"code": 2, "message": "..."}), seguro para log e banco.
     */
    public static function errorReason(Response $response, string $fallback): string
    {
        $code = $response->json('code');

        return is_int($code) || (is_string($code) && ctype_digit($code)) ? 'pinterest_'.$code : $fallback;
    }
}
