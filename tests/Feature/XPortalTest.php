<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * X na tela de Redes Sociais: conexão direta e explicação quando os créditos da API acabam.
 */
class XPortalTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'upload_post.api_key' => null,
            'services.x.client_id' => 'cliente-x-ficticio',
            'services.x.client_secret' => 'segredo-x-ficticio',
            'social.routing.x' => 'native',
        ]);

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->cliente = Cliente::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123'),
            'brand_niche' => 'Saude', 'website' => 'https://clinica-ficticia.test',
        ]);
    }

    public function test_x_card_offers_the_direct_connection(): void
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee(route('portal.social.oauth.redirect', 'x'), false);
    }

    public function test_history_explains_when_the_x_credits_run_out(): void
    {
        $connection = SocialConnection::create([
            'tenant_id' => $this->cliente->tenant_id, 'cliente_id' => $this->cliente->id, 'platform' => 'x', 'provider' => 'x',
            'external_account_id' => '1234567890', 'handle' => '@clinicaficticia', 'access_token' => 'acesso', 'refresh_token' => 'atualizacao',
            'expires_at' => now()->addHour(),
        ]);
        VideoPublication::create([
            'tenant_id' => $this->cliente->tenant_id, 'cliente_id' => $this->cliente->id, 'driver' => 'x', 'platform' => 'x',
            'social_connection_id' => $connection->id, 'plataformas' => ['x'], 'titulo' => 'Sem credito',
            'status' => 'falhou', 'last_error' => 'x_credits_exhausted',
        ]);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee('@clinicaficticia')
            ->assertSee('O envio ao X está temporariamente indisponível. Fale com a sua produtora.')
            ->assertDontSee('x_credits_exhausted');
    }
}
