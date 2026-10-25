<?php

namespace App\Services\SocialPublishing\OAuth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OAuth v2 do TikTok (Login Kit para web).
 *
 * Access token vale 24 h e o refresh token 365 dias (pode vir um novo a cada renovação).
 * Segredo e tokens só no corpo form-urlencoded, nunca na URL. Erros saem como OAuthException.
 */
class TikTokOAuthClient
{
    public const AUTHORIZE_URL = 'https://www.tiktok.com/v2/auth/authorize/';

    public const API_HOST = 'https://open.tiktokapis.com';

    public function isConfigured(): bool
    {
        return filled(config('services.tiktok.client_key')) && filled(config('services.tiktok.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.tiktok.redirect') ?: route('portal.social.oauth.callback', 'tiktok');
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_key' => config('services.tiktok.client_key'),
            'scope' => implode(',', config('social.tiktok.scopes')),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, open_id: ?string, scopes: list<string>}
     */
    public function exchangeCode(string $code): array
    {
        return $this->tokenRequest([
            'code' => urldecode($code),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, open_id: ?string, scopes: list<string>}
     */
    public function refresh(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function revoke(string $accessToken): bool
    {
        try {
            $response = Http::asForm()->timeout(15)->post(self::API_HOST.'/v2/oauth/revoke/', [
                'client_key' => config('services.tiktok.client_key'),
                'client_secret' => config('services.tiktok.client_secret'),
                'token' => $accessToken,
            ]);
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful();
    }

    private function tokenRequest(array $params): array
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(15)->post(self::API_HOST.'/v2/oauth/token/', array_merge([
                'client_key' => config('services.tiktok.client_key'),
                'client_secret' => config('services.tiktok.client_secret'),
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
            'expires_in' => (int) ($response->json('expires_in') ?? 86400),
            'open_id' => $response->json('open_id'),
            'scopes' => array_values(array_filter(array_map('trim', explode(',', (string) $response->json('scope'))))),
        ];
    }

    /**
     * Código de erro do TikTok (no OAuth vem em "error"; na Content API em "error.code").
     */
    public static function errorReason(Response $response, string $fallback): string
    {
        $error = $response->json('error');
        if (is_array($error)) {
            $error = $error['code'] ?? null;
        }

        return is_string($error) && $error !== 'ok' && preg_match('/^[A-Za-z0-9_.-]{1,60}$/', $error) ? $error : $fallback;
    }
}
