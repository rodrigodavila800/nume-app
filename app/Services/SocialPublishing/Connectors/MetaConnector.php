<?php

namespace App\Services\SocialPublishing\Connectors;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\MetaOAuthClient;
use App\Services\SocialPublishing\OAuth\OAuthException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Conecta Página do Facebook + Instagram profissional ligado a ela, num único login da Meta.
 *
 * Guardamos só o token da Página (sem validade fixa): o token do usuário é descartado.
 * Se a pessoa administra mais de uma Página, a lista (com tokens) fica criptografada na sessão
 * por poucos minutos até ela escolher.
 */
class MetaConnector implements SocialConnector
{
    use ManagesOAuthState;

    private const SESSION_KEY = 'social_oauth.meta';

    private const PAGES_KEY = 'social_oauth.meta_pages';

    /** Sem estas a Página não publica. */
    private const PAGE_PERMISSIONS = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts'];

    private const INSTAGRAM_PERMISSIONS = ['instagram_basic', 'instagram_content_publish'];

    public function __construct(private readonly MetaOAuthClient $meta) {}

    public function provider(): string
    {
        return 'meta';
    }

    public function isConfigured(): bool
    {
        return $this->meta->isConfigured();
    }

    public function start(Cliente $cliente, Session $session): string
    {
        return $this->meta->authorizationUrl($this->putState($session, self::SESSION_KEY, $cliente));
    }

    public function finish(Cliente $cliente, Session $session, ?string $state, ?string $code, ?string $error): ConnectResult
    {
        $this->pullState($session, self::SESSION_KEY, $cliente, $state);

        if ($error !== null) {
            throw new OAuthException($error === 'access_denied' ? 'access_denied' : 'authorization_failed');
        }

        if (! is_string($code) || $code === '') {
            throw new OAuthException('missing_code');
        }

        $userToken = $this->meta->exchangeCode($code)['access_token'];
        $granted = $this->meta->grantedPermissions($userToken);

        if (array_diff(self::PAGE_PERMISSIONS, $granted) !== []) {
            throw new OAuthException('scope_not_granted');
        }

        // Só Páginas em que a pessoa pode criar conteúdo (requisito da Meta para publicar Reels).
        $pages = array_values(array_filter(
            $this->meta->pages($userToken),
            fn (array $page) => in_array('CREATE_CONTENT', $page['tasks'], true)
        ));

        if ($pages === []) {
            throw new OAuthException('no_page');
        }

        $withInstagram = array_diff(self::INSTAGRAM_PERMISSIONS, $granted) === [];

        if (count($pages) === 1) {
            return ConnectResult::connected($this->connectPage($cliente, $pages[0], $withInstagram, $granted));
        }

        $session->put(self::PAGES_KEY, [
            'cliente_id' => $cliente->id,
            'expires_at' => now()->addMinutes((int) config('social.meta.page_selection_minutes', 10))->getTimestamp(),
            'payload' => Crypt::encryptString(json_encode(['pages' => $pages, 'instagram' => $withInstagram, 'granted' => $granted])),
        ]);

        return ConnectResult::selection('portal.social.meta.pages');
    }

    /**
     * Páginas aguardando escolha, sem tokens (para a tela).
     *
     * @return list<array{id: string, name: ?string, picture: ?string, instagram: ?string}>|null
     */
    public function pendingPages(Cliente $cliente, Session $session): ?array
    {
        $pending = $this->readPending($cliente, $session);

        return $pending === null ? null : array_map(fn (array $page) => [
            'id' => $page['id'],
            'name' => $page['name'],
            'picture' => $page['picture'],
            'instagram' => $pending['instagram'] ? ($page['instagram']['username'] ?? null) : null,
        ], $pending['pages']);
    }

    /**
     * @return list<SocialConnection>
     *
     * @throws OAuthException invalid_state quando a escolha expirou ou a Página não está na lista
     */
    public function choosePage(Cliente $cliente, Session $session, string $pageId): array
    {
        $pending = $this->readPending($cliente, $session);
        $session->forget(self::PAGES_KEY);

        $page = collect($pending['pages'] ?? [])->firstWhere('id', $pageId);
        if ($page === null) {
            throw new OAuthException('invalid_state');
        }

        return $this->connectPage($cliente, $page, $pending['instagram'], $pending['granted']);
    }

    /**
     * Token de Página não tem revogação por usuário: apagamos aqui e a pessoa pode remover o app
     * em Configurações > Apps e sites do Facebook (explicado em /exclusao-de-dados).
     */
    public function disconnect(SocialConnection $connection): void
    {
        $connection->cancelPendingPublications();

        Log::info('social.meta.disconnected', [
            'tenant_id' => $connection->tenant_id,
            'cliente_id' => $connection->cliente_id,
            'social_connection_id' => $connection->id,
            'platform' => $connection->platform,
        ]);

        $connection->delete();
    }

    public function successMessage(array $connections): string
    {
        $facebook = collect($connections)->firstWhere('platform', 'facebook');
        $instagram = collect($connections)->firstWhere('platform', 'instagram');

        return $instagram
            ? __('Página :pagina e Instagram :instagram conectados com sucesso!', ['pagina' => $facebook?->display_name ?? '', 'instagram' => $instagram->handle ?? ''])
            : __('Página :pagina conectada com sucesso! Para publicar no Instagram, ligue uma conta profissional a esta Página e conecte de novo.', ['pagina' => $facebook?->display_name ?? '']);
    }

    public function failureMessage(string $reason): ?string
    {
        return match ($reason) {
            'access_denied' => __('Você cancelou a autorização no Facebook. Nada foi conectado.'),
            'scope_not_granted' => __('Para publicar, aceite todas as permissões pedidas na tela do Facebook.'),
            'no_page' => __('Não encontramos nenhuma Página do Facebook em que você possa publicar. Verifique se você é administrador da Página.'),
            'invalid_state' => null,
            default => __('Não foi possível conectar o Facebook agora. Tente novamente em alguns minutos.'),
        };
    }

    /**
     * @return list<SocialConnection>
     */
    private function connectPage(Cliente $cliente, array $page, bool $withInstagram, array $granted): array
    {
        $connections = [$this->upsert($cliente, 'facebook', $page['id'], [
            'handle' => null,
            'display_name' => $page['name'] ? Str::limit($page['name'], 97) : null,
            'avatar_url' => $this->safeUrl($page['picture']),
            'access_token' => $page['access_token'],
            'scopes' => $granted,
        ])];

        if ($withInstagram && $page['instagram'] !== null) {
            $connections[] = $this->upsert($cliente, 'instagram', $page['instagram']['id'], [
                'handle' => $page['instagram']['username'] ? '@'.ltrim($page['instagram']['username'], '@') : null,
                'display_name' => $page['instagram']['username'],
                'avatar_url' => $this->safeUrl($page['instagram']['picture']),
                // O Instagram profissional publica com o token da Página a que está ligado.
                'access_token' => $page['access_token'],
                'scopes' => $granted,
            ]);
        }

        Log::info('social.meta.connected', [
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'social_connection_ids' => array_map(fn (SocialConnection $c) => $c->id, $connections),
        ]);

        return $connections;
    }

    private function upsert(Cliente $cliente, string $platform, string $externalId, array $attributes): SocialConnection
    {
        $connection = SocialConnection::forTenant($cliente->tenant_id)->firstOrNew([
            'cliente_id' => $cliente->id,
            'platform' => $platform,
            'external_account_id' => $externalId,
        ]);

        $connection->fill(array_merge($attributes, [
            'tenant_id' => $cliente->tenant_id,
            'provider' => 'meta',
            'refresh_token' => null,
            'expires_at' => null,
            'status' => SocialConnection::STATUS_ACTIVE,
            'last_refreshed_at' => now(),
            'last_error' => null,
        ]))->save();

        return $connection;
    }

    private function readPending(Cliente $cliente, Session $session): ?array
    {
        $pending = $session->get(self::PAGES_KEY);

        if (! is_array($pending) || ($pending['cliente_id'] ?? null) !== $cliente->id || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($pending['payload']), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($data['pages'] ?? null) ? $data : null;
    }

    private function safeUrl(?string $url): ?string
    {
        return $url && mb_strlen($url) <= 500 && str_starts_with($url, 'https://') ? $url : null;
    }
}
