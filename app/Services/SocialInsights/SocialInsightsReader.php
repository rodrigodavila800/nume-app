<?php

namespace App\Services\SocialInsights;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\GoogleOAuthClient;
use App\Services\SocialPublishing\OAuth\MetaOAuthClient;
use App\Services\SocialPublishing\OAuth\PinterestOAuthClient;
use App\Services\SocialPublishing\OAuth\ThreadsOAuthClient;
use App\Services\SocialPublishing\SocialTokenManager;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lê perfil e posts recentes das contas que o cliente conectou pelo app da NUME, só com as
 * permissões que a conexão já tem (nada de nova autorização nem de leitura de perfis alheios).
 *
 * Leitura nunca altera a conexão: um erro aqui não marca a conta como expirada, porque isso
 * derrubaria a publicação. Rede que falhar fica de fora com 'unavailable'.
 * O resultado é conteúdo de terceiros: quem usar no prompt deve tratá-lo como dado não confiável.
 */
class SocialInsightsReader
{
    public function __construct(
        private readonly SocialTokenManager $tokens,
        private readonly MetaOAuthClient $meta,
        private readonly ThreadsOAuthClient $threads,
        private readonly PinterestOAuthClient $pinterest,
    ) {}

    /**
     * @return array{read_at: string, accounts: list<array<string, mixed>>}
     */
    public function read(Cliente $cliente): array
    {
        $platforms = config('social.insights.platforms', []);

        $connections = SocialConnection::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->active()
            ->orderBy('id')
            ->get()
            ->unique('platform');

        $accounts = [];
        foreach ($connections as $connection) {
            if (! in_array($connection->platform, $platforms, true)) {
                continue;
            }

            try {
                $account = match ($connection->platform) {
                    'youtube' => $this->youtube($connection),
                    'instagram' => $this->instagram($connection),
                    'facebook' => $this->facebook($connection),
                    'threads' => $this->threadsAccount($connection),
                    'pinterest' => $this->pinterestAccount($connection),
                };
                $accounts[] = ['platform' => $connection->platform, 'handle' => $connection->handle, 'status' => 'ok'] + $account;
            } catch (Throwable $e) {
                Log::info('social.insights.unavailable', [
                    'tenant_id' => $connection->tenant_id,
                    'social_connection_id' => $connection->id,
                    'platform' => $connection->platform,
                    'error' => get_class($e),
                ]);
                $accounts[] = ['platform' => $connection->platform, 'handle' => $connection->handle, 'status' => 'unavailable'];
            }
        }

        return ['read_at' => now()->toDateTimeString(), 'accounts' => $accounts];
    }

    private function youtube(SocialConnection $connection): array
    {
        $http = $this->client($connection);

        $channel = $http->get(GoogleOAuthClient::CHANNELS_URL, ['part' => 'snippet,statistics,contentDetails', 'mine' => 'true'])
            ->throw()->json('items.0');
        $uploads = $channel['contentDetails']['relatedPlaylists']['uploads'] ?? null;

        $posts = [];
        if ($uploads) {
            $items = $http->get('https://www.googleapis.com/youtube/v3/playlistItems', [
                'part' => 'snippet', 'playlistId' => $uploads, 'maxResults' => $this->limit(),
            ])->throw()->json('items', []);

            $ids = array_filter(array_map(fn ($i) => $i['snippet']['resourceId']['videoId'] ?? null, $items));
            $stats = $ids === [] ? [] : collect($http->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'statistics', 'id' => implode(',', $ids),
            ])->throw()->json('items', []))->keyBy('id');

            foreach ($items as $item) {
                $id = $item['snippet']['resourceId']['videoId'] ?? null;
                $posts[] = $this->post(
                    $item['snippet']['title'] ?? '',
                    $item['snippet']['publishedAt'] ?? null,
                    views: $stats[$id]['statistics']['viewCount'] ?? null,
                    likes: $stats[$id]['statistics']['likeCount'] ?? null,
                    comments: $stats[$id]['statistics']['commentCount'] ?? null,
                );
            }
        }

        return [
            'followers' => $this->int($channel['statistics']['subscriberCount'] ?? null),
            'bio' => $this->text($channel['snippet']['description'] ?? ''),
            'posts' => $posts,
        ];
    }

    private function instagram(SocialConnection $connection): array
    {
        $http = $this->client($connection);
        $id = $connection->external_account_id;

        $profile = $http->get($this->meta->graphUrl($id), ['fields' => 'followers_count,media_count,biography'])->throw()->json();
        $media = $http->get($this->meta->graphUrl($id.'/media'), [
            'fields' => 'caption,like_count,comments_count,timestamp,media_type', 'limit' => $this->limit(),
        ])->throw()->json('data', []);

        return [
            'followers' => $this->int($profile['followers_count'] ?? null),
            'bio' => $this->text($profile['biography'] ?? ''),
            'posts' => array_map(fn ($m) => $this->post(
                $m['caption'] ?? '', $m['timestamp'] ?? null,
                likes: $m['like_count'] ?? null, comments: $m['comments_count'] ?? null, format: $m['media_type'] ?? null,
            ), $media),
        ];
    }

    private function facebook(SocialConnection $connection): array
    {
        $http = $this->client($connection);
        $id = $connection->external_account_id;

        $page = $http->get($this->meta->graphUrl($id), ['fields' => 'followers_count,fan_count,about'])->throw()->json();
        $feed = $http->get($this->meta->graphUrl($id.'/posts'), ['fields' => 'message,created_time', 'limit' => $this->limit()])
            ->throw()->json('data', []);

        return [
            'followers' => $this->int($page['followers_count'] ?? $page['fan_count'] ?? null),
            'bio' => $this->text($page['about'] ?? ''),
            'posts' => array_map(fn ($p) => $this->post($p['message'] ?? '', $p['created_time'] ?? null), $feed),
        ];
    }

    private function threadsAccount(SocialConnection $connection): array
    {
        $http = $this->client($connection);

        $profile = $http->get($this->threads->apiUrl('me'), ['fields' => 'username,threads_biography'])->throw()->json();
        $posts = $http->get($this->threads->apiUrl('me/threads'), ['fields' => 'text,timestamp', 'limit' => $this->limit()])
            ->throw()->json('data', []);

        return [
            'followers' => null,
            'bio' => $this->text($profile['threads_biography'] ?? ''),
            'posts' => array_map(fn ($p) => $this->post($p['text'] ?? '', $p['timestamp'] ?? null), $posts),
        ];
    }

    private function pinterestAccount(SocialConnection $connection): array
    {
        $http = $this->client($connection);

        $account = $http->get($this->pinterest->apiUrl('user_account'))->throw()->json();
        $pins = $http->get($this->pinterest->apiUrl('pins'), ['page_size' => $this->limit()])->throw()->json('items', []);

        return [
            'followers' => $this->int($account['follower_count'] ?? null),
            'bio' => $this->text($account['about'] ?? ''),
            'posts' => array_map(fn ($p) => $this->post(trim(($p['title'] ?? '').' '.($p['description'] ?? '')), $p['created_at'] ?? null), $pins),
        ];
    }

    private function client(SocialConnection $connection): PendingRequest
    {
        return Http::withToken($this->tokens->accessToken($connection))
            ->acceptJson()
            ->connectTimeout(4)
            ->timeout((int) config('social.insights.timeout', 10));
    }

    private function post(string $text, ?string $publishedAt, mixed $views = null, mixed $likes = null, mixed $comments = null, ?string $format = null): array
    {
        return array_filter([
            'text' => $this->text($text),
            'published_at' => $publishedAt,
            'views' => $this->int($views),
            'likes' => $this->int($likes),
            'comments' => $this->int($comments),
            'format' => $format,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function text(string $text): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($text))), 0, 300);
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function limit(): int
    {
        return max(1, min(25, (int) config('social.insights.posts_per_account', 8)));
    }
}
