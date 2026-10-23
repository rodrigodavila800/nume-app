<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Rate limit do chat público da vitrine, pela rota real (POST /vitrine/{slug}/sofi-chat).
 */
class SofiChatRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        foreach (['vitrine-a', 'vitrine-b'] as $slug) {
            Tenant::create(['name' => "Produtora {$slug}", 'slug' => $slug, 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        }
    }

    public function test_seventh_message_in_the_same_minute_returns_429(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->chat('vitrine-a', '10.0.0.1')->assertOk();
        }

        $this->chat('vitrine-a', '10.0.0.1')->assertStatus(429);

        // Outro IP na mesma vitrine segue atendido.
        $this->chat('vitrine-a', '10.0.0.2')->assertOk();
    }

    public function test_storefronts_have_separate_tenant_counters(): void
    {
        config(['sofi.chat_rate_limits.per_hour_tenant' => 3]);

        foreach (['10.0.1.1', '10.0.1.2', '10.0.1.3'] as $ip) {
            $this->chat('vitrine-a', $ip)->assertOk();
        }

        // Teto da vitrine A atingido, mesmo com um IP novo.
        $this->chat('vitrine-a', '10.0.1.4')->assertStatus(429);

        // A vitrine B tem o próprio contador.
        $this->chat('vitrine-b', '10.0.1.4')->assertOk();
    }

    public function test_reset_has_its_own_limit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->reset('vitrine-a', '10.0.2.1')->assertOk();
        }

        $this->reset('vitrine-a', '10.0.2.1')->assertStatus(429);

        // Esgotar o reset não consome o limite do chat, e vice-versa.
        $this->chat('vitrine-a', '10.0.2.1')->assertOk();

        for ($i = 1; $i <= 6; $i++) {
            $this->chat('vitrine-a', '10.0.2.9');
        }
        $this->chat('vitrine-a', '10.0.2.9')->assertStatus(429);
        $this->reset('vitrine-a', '10.0.2.9')->assertOk();
    }

    private function chat(string $slug, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson("/vitrine/{$slug}/sofi-chat", ['message' => 'Quanto custa um pacote?']);
    }

    private function reset(string $slug, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson("/vitrine/{$slug}/sofi-chat/reset");
    }
}
