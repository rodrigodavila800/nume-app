<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LinkedIn na tela de Redes Sociais: conectar, aviso de reconexão (sem renovação automática) e histórico.
 */
class LinkedInPortalTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'upload_post.api_key' => null,
            'services.linkedin.client_id' => 'cliente-linkedin-ficticio',
            'services.linkedin.client_secret' => 'segredo-linkedin-ficticio',
            'social.routing.linkedin' => 'native',
        ]);

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->cliente = Cliente::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123'),
            'brand_niche' => 'Saude', 'website' => 'https://clinica-ficticia.test',
        ]);
    }

    public function test_linkedin_card_offers_the_direct_connection(): void
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee('Conectar Conta')
            ->assertSee(route('portal.social.oauth.redirect', 'linkedin'), false);
    }

    public function test_connection_far_from_expiring_shows_no_warning(): void
    {
        $this->connect(now()->addDays(40));

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee('Ana Ficticia')
            ->assertDontSee('O LinkedIn pede reconexão');
    }

    public function test_connection_close_to_expiring_asks_to_reconnect_in_time(): void
    {
        $connection = $this->connect(now()->addDays(5));

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee('O LinkedIn pede reconexão a cada 60 dias. Reconecte até '.$connection->expires_at->format('d/m/Y'))
            ->assertSee('Reconectar')
            ->assertSee(route('portal.social.oauth.redirect', 'linkedin'), false);
    }

    public function test_history_explains_linkedin_limits(): void
    {
        $connection = $this->connect(now()->addDays(40));
        VideoPublication::create([
            'tenant_id' => $this->cliente->tenant_id, 'cliente_id' => $this->cliente->id, 'driver' => 'linkedin', 'platform' => 'linkedin',
            'social_connection_id' => $connection->id, 'plataformas' => ['linkedin'], 'titulo' => 'Grande demais',
            'status' => 'falhou', 'last_error' => 'linkedin_file_size_invalid',
        ]);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee('O LinkedIn aceita vídeos de 75 KB a 500 MB.');
    }

    private function connect($expiresAt): SocialConnection
    {
        return SocialConnection::create([
            'tenant_id' => $this->cliente->tenant_id, 'cliente_id' => $this->cliente->id, 'platform' => 'linkedin', 'provider' => 'linkedin',
            'external_account_id' => 'membro-ficticio', 'display_name' => 'Ana Ficticia', 'access_token' => 'AQV.ficticio',
            'expires_at' => $expiresAt,
        ]);
    }
}
