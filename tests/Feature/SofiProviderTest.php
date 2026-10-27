<?php

namespace Tests\Feature;

use App\Ai\SofiProvider;
use App\Models\AiUsageLog;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SofiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_key_returns_null(): void
    {
        config(['sofi.provider' => 'gemini', 'sofi.keys.gemini' => null]);

        $this->assertNull(app(SofiProvider::class)->options());
    }

    public function test_name_has_no_tenant_suffix_without_tenant(): void
    {
        config(['sofi.provider' => 'gemini', 'sofi.keys.gemini' => 'chave-de-teste', 'sofi.model' => null, 'sofi.timeout' => 9]);

        $this->assertSame(['provider' => 'sofi_gemini', 'timeout' => 9], app(SofiProvider::class)->options());
    }

    public function test_name_carries_tenant_id_and_nothing_from_the_key(): void
    {
        config(['sofi.provider' => 'anthropic', 'sofi.keys.anthropic' => 'chave-de-teste', 'sofi.model' => 'claude-haiku-5-5']);

        $tenant = Tenant::create([
            'name' => 'Produtora A',
            'slug' => 'produtora-a',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $options = app(SofiProvider::class)->options($tenant);

        $this->assertSame("sofi_anthropic_t{$tenant->id}", $options['provider']);
        $this->assertSame('claude-haiku-5-5', $options['model']);
        $this->assertStringNotContainsString(md5('chave-de-teste'), $options['provider']);
    }

    public function test_returns_null_when_tenant_monthly_token_budget_is_exhausted(): void
    {
        config([
            'sofi.provider' => 'gemini',
            'sofi.keys.gemini' => 'chave-de-teste',
            'sofi.budgets.monthly_tokens_per_tenant' => 1000,
        ]);

        $tenantA = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $tenantB = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);

        // Uso do mês passado não conta
        $oldLog = AiUsageLog::create([
            'tenant_id' => $tenantA->id, 'agent' => 'TestAgent', 'provider' => 'gemini', 'model' => 'gemini-flash',
            'input_tokens' => 900, 'output_tokens' => 200,
        ]);
        $oldLog->forceFill(['created_at' => now()->subMonth()])->save();

        $this->assertNotNull(app(SofiProvider::class)->options($tenantA));

        // Uso deste mês atinge 1000 tokens
        AiUsageLog::create([
            'tenant_id' => $tenantA->id, 'agent' => 'TestAgent', 'provider' => 'gemini', 'model' => 'gemini-flash',
            'input_tokens' => 600, 'output_tokens' => 400,
        ]);

        $this->assertNull(app(SofiProvider::class)->options($tenantA));
        $this->assertNotNull(app(SofiProvider::class)->options($tenantB), 'Outro tenant não é afetado');
    }

    public function test_returns_null_when_cliente_monthly_token_budget_is_exhausted(): void
    {
        config([
            'sofi.provider' => 'gemini',
            'sofi.keys.gemini' => 'chave-de-teste',
            'sofi.budgets.monthly_tokens_per_tenant' => 10000,
            'sofi.budgets.monthly_tokens_per_cliente' => 500,
        ]);

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $clienteA = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => 'secret12345']);
        $clienteB = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente B', 'email' => 'b@ficticio.test', 'password' => 'secret12345']);

        AiUsageLog::create([
            'tenant_id' => $tenant->id, 'cliente_id' => $clienteA->id, 'agent' => 'TestAgent', 'provider' => 'gemini', 'model' => 'gemini-flash',
            'input_tokens' => 300, 'output_tokens' => 200,
        ]);

        $this->assertNull(app(SofiProvider::class)->options($tenant, $clienteA->id));
        $this->assertNotNull(app(SofiProvider::class)->options($tenant, $clienteB->id), 'Outro cliente do mesmo tenant continua liberado');
    }
}
