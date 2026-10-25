<?php

namespace App\Services\SocialPublishing\OAuth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OAuth 2.0 do Google para o YouTube (Authorization Code + PKCE, acesso offline).
 *
 * Usa o mesmo cliente OAuth do login com Google (services.google), com uma URI de retorno própria
 * que precisa estar cadastrada no Google Cloud. Nada aqui grava em log: quem chama decide,
 * sempre sem tokens.
 */
class GoogleOAuthClient
{
    public const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public const CHANNELS_URL = 'https://www.googleapis.com/youtube/v3/channels';

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.google.youtube_redirect') ?: route('portal.social.oauth.callback', 'youtube');
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return config('social.youtube.scopes');
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes()),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Troca o código de autorização pelos tokens.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scopes: list<string>}
     */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $this->redirectUri(),
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
     * Revoga o token no Google. Token já inválido conta como revogado.
     */
    public function revoke(string $token): bool
    {
        try {
            $response = Http::asForm()->timeout(15)->post(self::REVOKE_URL, ['token' => $token]);
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful() || $response->json('error') === 'invalid_token';
    }

    /**
     * Canal do YouTube da conta autorizada (null se a conta Google não tiver canal).
     *
     * @return array{id: string, handle: ?string, title: ?string, avatar: ?string}|null
     */
    public function fetchOwnChannel(string $accessToken): ?array
    {
        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(15)
                ->get(self::CHANNELS_URL, ['part' => 'snippet', 'mine' => 'true']);
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        if (! $response->successful()) {
            throw new OAuthException($this->errorReason($response, 'channel_lookup_failed'), $response->status());
        }

        $channel = $response->json('items.0');
        if (! is_array($channel) || empty($channel['id'])) {
            return null;
        }

        return [
            'id' => (string) $channel['id'],
            'handle' => $channel['snippet']['customUrl'] ?? null,
            'title' => $channel['snippet']['title'] ?? null,
            'avatar' => $channel['snippet']['thumbnails']['default']['url'] ?? null,
        ];
    }

    private function tokenRequest(array $params): array
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(15)->post(self::TOKEN_URL, array_merge([
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
            ], $params));
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw new OAuthException($this->errorReason($response, 'token_request_failed'), $response->status());
        }

        return [
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'expires_in' => (int) ($response->json('expires_in') ?? 3600),
            'scopes' => array_values(array_filter(explode(' ', (string) $response->json('scope')))),
        ];
    }

    /**
     * Só o código de erro da rede (ex.: invalid_grant), limitado a caracteres seguros.
     */
    private function errorReason(Response $response, string $fallback): string
    {
        $error = $response->json('error');
        if (is_array($error)) {
            $error = $error['errors'][0]['reason'] ?? $error['status'] ?? null;
        }

        return is_string($error) && preg_match('/^[A-Za-z0-9_.-]{1,60}$/', $error) ? $error : $fallback;
    }
}
