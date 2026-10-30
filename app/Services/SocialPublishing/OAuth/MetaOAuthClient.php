<?php

namespace App\Services\SocialPublishing\OAuth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Login do Facebook (Facebook Login for Business) para Páginas e Instagram profissional.
 *
 * Fluxo: código -> token curto -> token longo do usuário (60 dias) -> tokens das Páginas
 * (sem validade fixa). Tokens vão no header Authorization e toda chamada leva appsecret_proof.
 * O único log é o da chamada que falhou (nome e códigos da Meta); erros saem como
 * OAuthException com código curto.
 */
class MetaOAuthClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.meta.client_id')) && filled(config('services.meta.client_secret'));
    }

    public function graphUrl(string $path = ''): string
    {
        return 'https://graph.facebook.com/'.config('social.meta.graph_version', 'v26.0').($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    public function redirectUri(): string
    {
        return config('services.meta.redirect') ?: route('portal.social.oauth.callback', 'meta');
    }

    public function authorizationUrl(string $state): string
    {
        $params = [
            'client_id' => config('services.meta.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'state' => $state,
        ];

        // Facebook Login for Business: a configuração do app define as permissões.
        if (filled(config('services.meta.config_id'))) {
            $params['config_id'] = config('services.meta.config_id');
        } else {
            $params['scope'] = implode(',', config('social.meta.scopes'));
        }

        return 'https://www.facebook.com/'.config('social.meta.graph_version', 'v26.0').'/dialog/oauth?'.http_build_query($params);
    }

    /**
     * Código -> token longo do usuário.
     *
     * @return array{access_token: string, expires_in: ?int}
     */
    public function exchangeCode(string $code): array
    {
        $short = $this->tokenRequest([
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);

        return $this->tokenRequest([
            'grant_type' => 'fb_exchange_token',
            'fb_exchange_token' => $short['access_token'],
        ]);
    }

    /**
     * Permissões que a pessoa realmente concedeu (ela pode desmarcar algumas na tela da Meta).
     *
     * @return list<string>
     */
    public function grantedPermissions(string $userToken): array
    {
        $response = $this->get($userToken, 'me/permissions');

        return collect($response->json('data', []))
            ->filter(fn ($row) => is_array($row) && ($row['status'] ?? null) === 'granted' && is_string($row['permission'] ?? null))
            ->pluck('permission')
            ->values()
            ->all();
    }

    /**
     * Páginas que a pessoa administra, com o token da Página e o Instagram profissional ligado.
     *
     * @return list<array{id: string, name: ?string, access_token: string, tasks: list<string>, picture: ?string, instagram: ?array{id: string, username: ?string, picture: ?string}}>
     */
    public function pages(string $userToken): array
    {
        // Lotes pequenos: a Meta responde com o erro 1 ("reduza a quantidade de dados") quando se pede
        // muitas Páginas de uma vez com Instagram e foto aninhados.
        $query = [
            'fields' => 'id,name,access_token,tasks,picture{url},instagram_business_account{id,username,profile_picture_url}',
            'limit' => max(1, (int) config('social.meta.pages_batch_size', 25)),
        ];

        $pages = [];
        for ($batch = 0, $maxBatches = max(1, (int) config('social.meta.pages_max_batches', 8)); $batch < $maxBatches; $batch++) {
            $response = $this->get($userToken, 'me/accounts', $query);

            foreach ($response->json('data', []) as $page) {
                if (! is_array($page) || empty($page['id']) || ! is_string($page['access_token'] ?? null)) {
                    continue;
                }

                $instagram = $page['instagram_business_account'] ?? null;

                $pages[] = [
                    'id' => (string) $page['id'],
                    'name' => isset($page['name']) ? (string) $page['name'] : null,
                    'access_token' => $page['access_token'],
                    'tasks' => array_values(array_filter($page['tasks'] ?? [], 'is_string')),
                    'picture' => $page['picture']['data']['url'] ?? null,
                    'instagram' => is_array($instagram) && ! empty($instagram['id']) ? [
                        'id' => (string) $instagram['id'],
                        'username' => $instagram['username'] ?? null,
                        'picture' => $instagram['profile_picture_url'] ?? null,
                    ] : null,
                ];
            }

            $after = $response->json('paging.cursors.after');
            if (! is_string($after) || $after === '' || ! is_string($response->json('paging.next'))) {
                break;
            }

            $query['after'] = $after;
        }

        return $pages;
    }

    /**
     * Requisição autenticada na Graph API (token no header + appsecret_proof).
     */
    public function request(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->withQueryParameters(['appsecret_proof' => hash_hmac('sha256', $token, (string) config('services.meta.client_secret'))]);
    }

    /**
     * Só o código do erro da Graph API (ex.: meta_190), seguro para log e banco.
     */
    public static function errorReason(Response $response, string $fallback): string
    {
        $code = $response->json('error.code');
        $subcode = $response->json('error.error_subcode');

        if (is_int($code) || (is_string($code) && ctype_digit($code))) {
            return 'meta_'.$code.(is_int($subcode) ? '_'.$subcode : '');
        }

        return $fallback;
    }

    private function get(string $token, string $path, array $query = []): Response
    {
        try {
            $response = $this->request($token)->get($this->graphUrl($path), $query);
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        if (! $response->successful()) {
            // Só o nome da chamada e os códigos da Meta (nunca token, corpo ou mensagem), para saber
            // em qual etapa do login a conexão falhou.
            Log::warning('social.meta.graph_failed', [
                'step' => $path,
                'http_status' => $response->status(),
                'code' => $response->json('error.code'),
                'subcode' => $response->json('error.error_subcode'),
            ]);

            throw new OAuthException(self::errorReason($response, 'graph_request_failed'), $response->status());
        }

        return $response;
    }

    /**
     * @return array{access_token: string, expires_in: ?int}
     */
    private function tokenRequest(array $params): array
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(15)->post($this->graphUrl('oauth/access_token'), array_merge([
                'client_id' => config('services.meta.client_id'),
                'client_secret' => config('services.meta.client_secret'),
            ], $params));
        } catch (ConnectionException) {
            throw new OAuthException('network');
        }

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw new OAuthException(self::errorReason($response, 'token_request_failed'), $response->status());
        }

        return [
            'access_token' => $response->json('access_token'),
            'expires_in' => is_numeric($response->json('expires_in')) ? (int) $response->json('expires_in') : null,
        ];
    }
}
