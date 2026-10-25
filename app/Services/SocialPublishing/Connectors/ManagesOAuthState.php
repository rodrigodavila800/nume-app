<?php

namespace App\Services\SocialPublishing\Connectors;

use App\Models\Cliente;
use App\Services\SocialPublishing\OAuth\OAuthException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * State anti-CSRF (e verificador PKCE, quando a rede aceita) guardado só na sessão,
 * preso ao cliente logado, com validade e de uso único.
 */
trait ManagesOAuthState
{
    /**
     * @param  array<string, mixed>  $extra
     */
    protected function putState(Session $session, string $key, Cliente $cliente, array $extra = []): string
    {
        $state = Str::random(40);

        $session->put($key, array_merge($extra, [
            'state' => $state,
            'cliente_id' => $cliente->id,
            'expires_at' => now()->addMinutes((int) config('social.oauth.state_ttl_minutes', 10))->getTimestamp(),
        ]));

        return $state;
    }

    /**
     * Consome o state (uma vez só) e valida dono e validade.
     *
     * @return array<string, mixed>
     *
     * @throws OAuthException invalid_state
     */
    protected function pullState(Session $session, string $key, Cliente $cliente, ?string $state): array
    {
        $pending = $session->pull($key);

        if (! is_array($pending) || ! is_string($state) || ! hash_equals((string) ($pending['state'] ?? ''), $state)) {
            throw new OAuthException('invalid_state');
        }

        if (($pending['cliente_id'] ?? null) !== $cliente->id || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            throw new OAuthException('invalid_state');
        }

        return $pending;
    }
}
