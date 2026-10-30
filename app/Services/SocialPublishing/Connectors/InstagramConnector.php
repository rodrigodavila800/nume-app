<?php

namespace App\Services\SocialPublishing\Connectors;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Services\SocialPublishing\OAuth\MetaOAuthClient;
use App\Services\SocialPublishing\OAuth\OAuthException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Instagram profissional pelo login unificado da Meta: a pessoa entra com o Instagram ou com o
 * Facebook e não precisa de Página. Usa o mesmo app da Meta com outra configuração do Facebook
 * Login for Business (services.meta.instagram_config_id).
 *
 * O token é de usuário do sistema do negócio do cliente (sem expiração na configuração padrão)
 * e publica em graph.facebook.com, pelo mesmo InstagramReelsPublisher das contas ligadas à Página.
 * Se o cliente atribuir mais de uma conta, a lista (com o token) fica criptografada na sessão
 * por poucos minutos até ele escolher.
 */
class InstagramConnector implements SocialConnector
{
    use ManagesOAuthState;

    private const SESSION_KEY = 'social_oauth.instagram';

    private const ACCOUNTS_KEY = 'social_oauth.instagram_accounts';

    public function __construct(private readonly MetaOAuthClient $meta) {}

    public function provider(): string
    {
        return 'instagram';
    }

    public function isConfigured(): bool
    {
        return $this->meta->isConfigured() && filled(config('services.meta.instagram_config_id'));
    }

    public function start(Cliente $cliente, Session $session): string
    {
        return $this->meta->authorizationUrl(
            $this->putState($session, self::SESSION_KEY, $cliente),
            (string) config('services.meta.instagram_config_id'),
            $this->meta->instagramRedirectUri(),
        );
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

        $token = $this->meta->exchangeBusinessCode($code, $this->meta->instagramRedirectUri());
        $accounts = $this->meta->assignedInstagramAccounts($token['access_token']);

        if ($accounts === []) {
            throw new OAuthException('no_instagram_account');
        }

        if (count($accounts) === 1) {
            return ConnectResult::connected([$this->connect($cliente, $accounts[0], $token)]);
        }

        $session->put(self::ACCOUNTS_KEY, [
            'cliente_id' => $cliente->id,
            'expires_at' => now()->addMinutes((int) config('social.meta.page_selection_minutes', 10))->getTimestamp(),
            'payload' => Crypt::encryptString(json_encode(['accounts' => $accounts, 'token' => $token])),
        ]);

        return ConnectResult::selection('portal.social.instagram.accounts');
    }

    /**
     * Contas aguardando escolha, sem o token (para a tela).
     *
     * @return list<array{id: string, username: ?string}>|null
     */
    public function pendingAccounts(Cliente $cliente, Session $session): ?array
    {
        return $this->readPending($cliente, $session)['accounts'] ?? null;
    }

    /**
     * @throws OAuthException invalid_state quando a escolha expirou ou a conta não está na lista
     */
    public function chooseAccount(Cliente $cliente, Session $session, string $accountId): SocialConnection
    {
        $pending = $this->readPending($cliente, $session);
        $session->forget(self::ACCOUNTS_KEY);

        $account = collect($pending['accounts'] ?? [])->firstWhere('id', $accountId);
        if ($account === null) {
            throw new OAuthException('invalid_state');
        }

        return $this->connect($cliente, $account, $pending['token']);
    }

    /**
     * Apagamos o token aqui; o cliente remove o app nas Integrações comerciais do portfólio dele
     * (explicado em /exclusao-de-dados), o que invalida o token na Meta.
     */
    public function disconnect(SocialConnection $connection): void
    {
        $connection->cancelPendingPublications();

        Log::info('social.instagram.disconnected', [
            'tenant_id' => $connection->tenant_id,
            'cliente_id' => $connection->cliente_id,
            'social_connection_id' => $connection->id,
        ]);

        $connection->delete();
    }

    public function successMessage(array $connections): string
    {
        return __('Instagram :perfil conectado com sucesso!', ['perfil' => $connections[0]?->handle ?? '']);
    }

    public function failureMessage(string $reason): ?string
    {
        return match ($reason) {
            'access_denied' => __('Você cancelou a autorização. Nada foi conectado.'),
            'no_instagram_account' => __('Nenhuma conta do Instagram foi liberada para a NUME. Conecte de novo e, na tela da Meta, marque a sua conta profissional do Instagram.'),
            'invalid_state' => null,
            default => __('Não foi possível conectar o Instagram agora. Tente novamente em alguns minutos.'),
        };
    }

    /**
     * @param  array{id: string, username: ?string}  $account
     * @param  array{access_token: string, expires_in: ?int}  $token
     */
    private function connect(Cliente $cliente, array $account, array $token): SocialConnection
    {
        $connection = SocialConnection::forTenant($cliente->tenant_id)->firstOrNew([
            'cliente_id' => $cliente->id,
            'platform' => 'instagram',
            'external_account_id' => $account['id'],
        ]);

        $connection->fill([
            'tenant_id' => $cliente->tenant_id,
            'provider' => 'instagram',
            'handle' => $account['username'] ? '@'.ltrim($account['username'], '@') : null,
            'display_name' => $account['username'],
            'avatar_url' => null,
            'access_token' => $token['access_token'],
            'refresh_token' => null,
            // Sem expiração na configuração: o token vale até o cliente remover o app do portfólio.
            'expires_at' => $token['expires_in'] ? now()->addSeconds($token['expires_in']) : null,
            'scopes' => null,
            'status' => SocialConnection::STATUS_ACTIVE,
            'last_refreshed_at' => now(),
            'last_error' => null,
        ])->save();

        Log::info('social.instagram.connected', [
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'social_connection_id' => $connection->id,
        ]);

        return $connection;
    }

    private function readPending(Cliente $cliente, Session $session): ?array
    {
        $pending = $session->get(self::ACCOUNTS_KEY);

        if (! is_array($pending) || ($pending['cliente_id'] ?? null) !== $cliente->id || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($pending['payload']), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($data['accounts'] ?? null) && is_string($data['token']['access_token'] ?? null) ? $data : null;
    }
}
