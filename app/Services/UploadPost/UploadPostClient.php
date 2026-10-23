<?php

namespace App\Services\UploadPost;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Encapsula toda comunicação com a API do Upload-Post.
 *
 * Única classe que manipula a API key — nenhum controller, job ou view
 * deve montar a URL/headers na mão. Isso facilita rotação de chave,
 * auditoria e troca transparente de provedor no futuro sem tocar no resto do app.
 */
class UploadPostClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $timeout = 20,
    ) {
    }

    public static function fromConfig(): self
    {
        // Prioriza chave em Setting (banco criptografado) ou cai no .env/config
        $apiKey = (string) (Setting::get('upload_post_api_key') ?: config('upload_post.api_key', ''));
        $baseUrl = (string) (Setting::get('upload_post_base_url') ?: config('upload_post.base_url', 'https://api.upload-post.com/api'));
        $timeout = (int) config('upload_post.timeout', 20);

        return new self(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            timeout: $timeout,
        );
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Cria o perfil de usuário no Upload-Post (container das contas
     * sociais daquele cliente). Idempotente: se já existir, seguimos o fluxo.
     */
    public function createUserProfile(string $username): array
    {
        if (! $this->isConfigured()) {
            Log::info('upload_post.simulation: createUserProfile called without API key configured', ['username' => $username]);
            return ['status' => 'mock_created', 'username' => $username];
        }

        $response = $this->client()->post('/uploadposts/users', [
            'username' => $username,
        ]);

        if ($response->status() === 409 || $response->status() === 422) {
            return ['already_exists' => true];
        }

        $this->throwLogged($response);

        return $response->json();
    }

    /**
     * Gera a URL segura (JWT) para o cliente conectar as redes sociais.
     * Essa URL expira em 48h (definido pela Upload-Post).
     */
    public function generateConnectUrl(string $username, ?string $redirectUrl = null, ?string $logoImage = null): string
    {
        $redirectUrl ??= (string) config('upload_post.redirect_url');
        $logoImage ??= (string) config('upload_post.logo_image');

        if (! $this->isConfigured()) {
            Log::info('upload_post.simulation: generateConnectUrl called without API key', [
                'username' => $username,
                'redirect_url' => $redirectUrl,
            ]);
            // Em ambiente local sem chave real, retorna URL simulada com token de demonstração
            return $redirectUrl . '?simulated=1&connected=true';
        }

        $payload = [
            'username' => $username,
            'redirect_url' => $redirectUrl,
        ];

        if (! empty($logoImage)) {
            $payload['logo_image'] = $logoImage;
        }

        $response = $this->client()->post('/uploadposts/users/generate-jwt', $payload);

        $this->throwLogged($response);

        return (string) $response->json('access_url');
    }

    /**
     * Confirma, do lado da Upload-Post, se o perfil está conectado e
     * quais plataformas têm conta vinculada.
     */
    public function getUserProfile(string $username): ?array
    {
        if (! $this->isConfigured()) {
            return [
                'username' => $username,
                'social_accounts' => [
                    'instagram' => ['connected' => true, 'handle' => '@nume.creative'],
                    'tiktok' => ['connected' => true, 'handle' => '@nume.creative'],
                    'youtube' => ['connected' => true, 'handle' => 'NUME Videos'],
                ],
            ];
        }

        $response = $this->client()->get('/uploadposts/users', [
            'username' => $username,
        ]);

        if ($response->status() === 404) {
            return null;
        }

        $this->throwLogged($response);

        return $response->json('profile');
    }

    /**
     * Publica um vídeo já hospedado (URL pública) nas plataformas
     * conectadas daquele usuário.
     *
     * @param  string[]  $platforms  slugs: instagram, youtube, tiktok, facebook, linkedin...
     */
    public function uploadVideo(string $username, string $videoUrl, string $title, array $platforms): array
    {
        if (! $this->isConfigured()) {
            Log::info('upload_post.simulation: uploadVideo simulated', [
                'username' => $username,
                'video_url' => $videoUrl,
                'title' => $title,
                'platforms' => $platforms,
            ]);

            return [
                'request_id' => 'mock_req_' . bin2hex(random_bytes(8)),
                'status' => 'queued',
                'message' => 'Simulação de publicação disparada com sucesso.',
            ];
        }

        $multipart = [
            ['name' => 'video', 'contents' => $videoUrl],
            ['name' => 'title', 'contents' => $title],
            ['name' => 'user', 'contents' => $username],
            ['name' => 'async_upload', 'contents' => 'true'],
        ];

        foreach ($platforms as $platform) {
            $multipart[] = ['name' => 'platform[]', 'contents' => $platform];
        }

        $response = $this->client()->asMultipart()->post('/upload', $multipart);

        $this->throwLogged($response);

        return $response->json();
    }

    public function getUploadStatus(string $requestId): array
    {
        if (! $this->isConfigured() || str_starts_with($requestId, 'mock_req_')) {
            return [
                'request_id' => $requestId,
                'status' => 'completed',
                'details' => [
                    'instagram' => ['status' => 'success', 'url' => 'https://instagram.com'],
                    'tiktok' => ['status' => 'success', 'url' => 'https://tiktok.com'],
                ],
            ];
        }

        $response = $this->client()->get('/uploadposts/status', [
            'request_id' => $requestId,
        ]);

        $this->throwLogged($response);

        return $response->json();
    }

    public function getAnalytics(string $username): array
    {
        if (! $this->isConfigured()) {
            return [
                'views' => 12450,
                'engagement_rate' => 0.082,
            ];
        }

        $response = $this->client()->get("/analytics/{$username}");

        $this->throwLogged($response);

        return $response->json();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['Authorization' => "Apikey {$this->apiKey}"])
            ->timeout($this->timeout);
    }

    private function throwLogged(Response $response): void
    {
        if ($response->failed()) {
            Log::warning('upload_post.api_error', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
        }

        $response->throw();
    }
}
