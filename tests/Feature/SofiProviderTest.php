<?php

namespace Tests\Feature;

use App\Ai\SofiProvider;
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
}
