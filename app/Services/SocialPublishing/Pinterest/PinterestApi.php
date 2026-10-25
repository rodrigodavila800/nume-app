<?php

namespace App\Services\SocialPublishing\Pinterest;

use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\PinterestOAuthClient;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\SocialTokenManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Chamadas da API v5 do Pinterest com o token da conta conectada e erros classificados.
 */
class PinterestApi
{
    public function __construct(
        private readonly PinterestOAuthClient $oauth,
        private readonly SocialTokenManager $tokens,
    ) {}

    /**
     * Pastas em que a conta pode criar pins (até 4 páginas de 250).
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws PublishException
     */
    public function boards(SocialConnection $connection): array
    {
        $boards = [];
        $bookmark = null;

        for ($page = 0; $page < 4; $page++) {
            $data = $this->request($connection, 'get', 'boards', array_filter(['page_size' => 250, 'bookmark' => $bookmark]));

            foreach ($data['items'] ?? [] as $board) {
                if (is_array($board) && is_string($board['id'] ?? null) && preg_match('/^\d{1,30}$/', $board['id'])) {
                    $boards[] = ['id' => $board['id'], 'name' => (string) ($board['name'] ?? $board['id'])];
                }
            }

            $bookmark = $data['bookmark'] ?? null;
            if (! is_string($bookmark) || $bookmark === '') {
                break;
            }
        }

        return $boards;
    }

    /**
     * @throws PublishException
     */
    public function request(SocialConnection $connection, string $method, string $path, array $data = []): array
    {
        try {
            $request = Http::withToken($this->tokens->accessToken($connection))->acceptJson()->timeout(30);
            $response = $method === 'get'
                ? $request->get($this->oauth->apiUrl($path), $data)
                : $request->asJson()->post($this->oauth->apiUrl($path), $data);
        } catch (ConnectionException) {
            throw new PublishException('network', retryable: true);
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw $this->classify($response, $connection);
    }

    public function classify(Response $response, SocialConnection $connection): PublishException
    {
        $reason = PinterestOAuthClient::errorReason($response, 'pinterest_http_'.$response->status());

        if ($response->status() === 401) {
            $connection->markFailed($reason, SocialConnection::STATUS_EXPIRED);

            return new PublishException('reconnect_required');
        }

        if ($response->status() === 429) {
            return new PublishException('rate_limited', retryable: true);
        }

        return new PublishException($reason, retryable: $response->serverError());
    }
}
