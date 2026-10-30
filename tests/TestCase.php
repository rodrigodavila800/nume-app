<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Trava de segurança: a suíte só roda no SQLite em memória do phpunit.xml.
     *
     * Com `config:cache` ligado, o Laravel ignora o <env> do phpunit.xml e a suíte passaria a apontar
     * para o banco real; os testes com RefreshDatabase fazem `migrate:fresh` e apagam todos os dados.
     * A checagem roda logo após criar a aplicação, ANTES de qualquer trait (RefreshDatabase) tocar no banco.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $default = $this->app['config']->get('database.default');
        $database = $this->app['config']->get("database.connections.{$default}.database");

        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Testes abortados: conexão '{$default}' (banco '{$database}') não é o SQLite em memória. ".
                'Rode `php artisan optimize:clear` (cache de config ativo) e tente de novo. Nenhum dado foi alterado.'
            );
        }

        // Testes nunca saem para a rede: toda chamada HTTP precisa de Http::fake(). Uma chamada real
        // gasta cota de API paga, depende da internet e deixa a suíte lenta e instável.
        Http::preventStrayRequests();
    }

    /**
     * O middleware AuthenticateSession (sessão única por usuário) guarda na sessão o hash da senha de
     * quem fez login. No navegador cada pessoa tem a própria sessão; no teste, trocar de usuário com
     * actingAs reaproveita a mesma sessão, o hash não bate e o novo usuário é deslogado (302).
     * Ao trocar de usuário, o teste começa como uma sessão nova para esse guard.
     */
    public function actingAs($user, $guard = null)
    {
        $guard ??= config('auth.defaults.guard');
        $this->app['session']->forget("password_hash_{$guard}");

        return parent::actingAs($user, $guard);
    }
}
