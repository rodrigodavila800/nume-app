<?php

namespace App\Services\SocialPublishing\Meta;

use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\MetaOAuthClient;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\SocialTokenManager;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Chamadas de publicação na Graph API e no rupload da Meta, com erros classificados.
 * Códigos em https://developers.facebook.com/docs/graph-api/guides/error-handling.
 */
class MetaGraphPublisher
{
    public const RUPLOAD_HOST = 'https://rupload.facebook.com/';

    public function __construct(
        private readonly MetaOAuthClient $oauth,
        private readonly SocialTokenManager $tokens,
    ) {}

    /** @throws PublishException */
    public function get(SocialConnection $connection, string $path, array $query = []): array
    {
        return $this->send($connection, fn (string $token) => $this->oauth->request($token)->get($this->oauth->graphUrl($path), $query));
    }

    /** @throws PublishException */
    public function post(SocialConnection $connection, string $path, array $data = []): array
    {
        return $this->send($connection, fn (string $token) => $this->oauth->request($token)->asForm()->post($this->oauth->graphUrl($path), $data));
    }

    /**
     * Envia o arquivo para o rupload (Authorization: OAuth, offset, file_size). Só aceita host da Meta.
     *
     * @param  resource  $resource
     *
     * @throws PublishException
     */
    public function upload(SocialConnection $connection, string $url, $resource, int $size): array
    {
        if (! str_starts_with($url, self::RUPLOAD_HOST)) {
            throw new PublishException('upload_init_failed', retryable: true);
        }

        $stream = Utils::streamFor($resource);

        try {
            return $this->send($connection, fn (string $token) => Http::withHeaders([
                'Authorization' => 'OAuth '.$token,
                'offset' => '0',
                'file_size' => (string) $size,
            ])
                ->withBody($stream, 'application/octet-stream')
                ->timeout((int) config('social.youtube.upload_timeout', 3600))
                ->post($url), uploading: true);
        } finally {
            $stream->close();
        }
    }

    /**
     * @param  callable(string): Response  $call
     *
     * @throws PublishException
     */
    private function send(SocialConnection $connection, callable $call, bool $uploading = false): array
    {
        try {
            $response = $call($this->tokens->accessToken($connection));
        } catch (ConnectionException) {
            throw new PublishException($uploading ? 'upload_interrupted' : 'network', retryable: true);
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw $this->classify($response, $connection);
    }

    public function classify(Response $response, SocialConnection $connection): PublishException
    {
        $code = (int) $response->json('error.code');
        $reason = MetaOAuthClient::errorReason($response, 'meta_http_'.$response->status());

        // Token inválido, expirado ou permissão retirada: só reconectando.
        if ($code === 190 || $code === 10 || ($code >= 200 && $code <= 299)) {
            $connection->markFailed($reason, SocialConnection::STATUS_EXPIRED);

            return new PublishException('reconnect_required');
        }

        // Limite de chamadas: passa em minutos. Limite diário de posts: só amanhã.
        if (in_array($code, [4, 17, 32, 613], true)) {
            return new PublishException('rate_limited', retryable: true);
        }

        if ($code === 9) {
            return new PublishException('publish_limit_reached');
        }

        if (in_array($code, [1, 2], true) || $response->serverError() || $response->status() === 429) {
            return new PublishException($reason, retryable: true);
        }

        return new PublishException($reason);
    }
}
