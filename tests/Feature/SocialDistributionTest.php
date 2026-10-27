<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\Setting;
use App\Models\SocialProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialDistributionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Cliente $cliente;

    private User $staff;

    private Pack $pack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Audiovisual Teste',
            'slug' => 'produtora-teste',
            'is_active' => true,
        ]);

        $this->staff = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Editor Staff',
            'email' => 'editor@produtora.com',
            'password' => bcrypt('Senha@Segura123'),
            'role' => 'editor',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Social Teste',
            'email' => 'social@cliente.com',
            'password' => bcrypt('Senha@Segura123'),
            'company_name' => 'Empresa do Cliente',
            'brand_niche' => 'Moda e E-commerce',
            'instagram_handle' => '@cliente.moda',
        ]);

        $this->pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Social 5 Vídeos',
            'slug' => 'pack-social-5',
            'price' => 500.00,
            'video_quantity' => 5,
            'status' => 'active',
        ]);

        PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'amount' => 500.00,
            'status' => 'paid',
            'video_quantity' => 5,
            'videos_used' => 1,
            'paid_at' => now(),
        ]);
    }

    public function test_client_can_access_social_distribution_dashboard(): void
    {
        $response = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.social.index'));

        $response->assertStatus(200);
        $response->assertSee('Conexão de Redes Sociais');
        $response->assertSee('Instagram Reels');
        $response->assertSee('TikTok');
        $response->assertSee('YouTube Shorts');
    }

    public function test_client_can_generate_connect_url(): void
    {
        $response = $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.social.connect-url'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['access_url']);

        $this->assertDatabaseHas('social_profiles', [
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
        ]);
    }

    public function test_client_can_sync_connection_status(): void
    {
        $profile = SocialProfile::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'upload_post_username' => (string) Str::uuid(),
            'connected' => false,
            'platforms_connected' => [],
        ]);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.social.sync'));

        $response->assertRedirect(route('portal.social.index'));
        $this->assertTrue($profile->fresh()->connected);
    }

    public function test_client_can_publish_video_from_production(): void
    {
        $profile = SocialProfile::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'upload_post_username' => (string) Str::uuid(),
            'connected' => true,
            'platforms_connected' => ['instagram', 'tiktok'],
        ]);

        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->staff->id,
            'title' => 'Campanha Primavera Verão',
            'uuid' => (string) Str::uuid(),
            'status' => 'approved',
        ]);

        $video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'title' => 'Vídeo Oficial Corte 1',
            'video_url' => 'https://storage.nume.app/videos/campanha-01.mp4',
            'status' => 'ready',
            'sofi_seo_title' => 'Como montar o look ideal para Primavera',
            'sofi_seo_description' => 'Aprenda combinações práticas com nosso guia.',
            'sofi_hashtags' => ['#Moda', '#Looks'],
        ]);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $video), [
                'video_url' => $video->video_url,
                'titulo' => 'Como montar o look ideal para Primavera',
                'plataformas' => ['instagram', 'tiktok'],
            ]);

        $response->assertStatus(202);
        $response->assertJsonPath('publication.status', 'processando');

        $this->assertDatabaseHas('video_publications', [
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'video_id' => $video->id,
            'titulo' => 'Como montar o look ideal para Primavera',
        ]);

        $this->assertEquals('published', $video->fresh()->status);
    }

    public function test_idor_protection_prevents_client_from_publishing_other_clients_video(): void
    {
        // Outro cliente do mesmo tenant
        $outroCliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outro Cliente Concorrente',
            'email' => 'concorrente@teste.com',
            'password' => bcrypt('Senha@Segura123'),
        ]);

        $outroProfile = SocialProfile::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'upload_post_username' => (string) Str::uuid(),
            'connected' => true,
            'platforms_connected' => ['instagram'],
        ]);

        $productionOutro = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $outroCliente->id,
            'created_by' => $this->staff->id,
            'title' => 'Produção Confidencial Outro Cliente',
            'uuid' => (string) Str::uuid(),
            'status' => 'approved',
        ]);

        $videoOutro = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $productionOutro->id,
            'title' => 'Vídeo Secreto',
            'video_url' => 'https://storage.nume.app/videos/secreto.mp4',
            'status' => 'ready',
        ]);

        // Cliente tenta disparar vídeo do outro cliente
        $response = $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $videoOutro), [
                'video_url' => $videoOutro->video_url,
                'titulo' => 'Tentativa Indevida',
                'plataformas' => ['instagram'],
            ]);

        $response->assertStatus(403);
    }

    public function test_client_cannot_publish_when_quota_is_exhausted_and_no_addon(): void
    {
        $profile = SocialProfile::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'upload_post_username' => (string) Str::uuid(),
            'connected' => true,
            'platforms_connected' => ['instagram'],
        ]);

        // Cota era 5, vamos registrar 5 publicações concluídas
        for ($i = 1; $i <= 5; $i++) {
            VideoPublication::create([
                'tenant_id' => $this->tenant->id,
                'cliente_id' => $this->cliente->id,
                'titulo' => "Post Antigo {$i}",
                'plataformas' => ['instagram'],
                'status' => 'publicado',
            ]);
        }

        $this->assertEquals(0, $this->cliente->getRemainingDistributionQuota());
        $this->assertFalse($this->cliente->canDistributeSocial());

        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->staff->id,
            'title' => 'Campanha Sem Cota',
            'uuid' => (string) Str::uuid(),
            'status' => 'approved',
        ]);

        $video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'title' => 'Vídeo Excedente',
            'video_url' => 'https://storage.nume.app/videos/excedente.mp4',
            'status' => 'ready',
        ]);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $video), [
                'video_url' => $video->video_url,
                'titulo' => 'Tentativa Sem Cota',
                'plataformas' => ['instagram'],
            ]);

        $response->assertStatus(403);
    }

    public function test_client_with_addon_can_publish_even_with_zero_remaining_pack_quota(): void
    {
        $profile = SocialProfile::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'upload_post_username' => (string) Str::uuid(),
            'connected' => true,
            'platforms_connected' => ['instagram'],
        ]);

        // Esgota a cota normal de 5 vídeos
        for ($i = 1; $i <= 5; $i++) {
            VideoPublication::create([
                'tenant_id' => $this->tenant->id,
                'cliente_id' => $this->cliente->id,
                'titulo' => "Post Esgotado {$i}",
                'plataformas' => ['instagram'],
                'status' => 'publicado',
            ]);
        }

        // Compra com Add-on de distribuição ativo
        PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'amount' => 49.00,
            'status' => 'paid',
            'video_quantity' => 0,
            'addons_purchased' => ['Distribuição Automática Multi-Rede'],
            'paid_at' => now(),
        ]);

        $this->assertTrue($this->cliente->fresh()->hasDistributionAddon());
        $this->assertTrue($this->cliente->fresh()->canDistributeSocial());

        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->staff->id,
            'title' => 'Campanha com Addon',
            'uuid' => (string) Str::uuid(),
            'status' => 'approved',
        ]);

        $video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'title' => 'Vídeo Liberado por Addon',
            'video_url' => 'https://storage.nume.app/videos/addon.mp4',
            'status' => 'ready',
        ]);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $video), [
                'video_url' => $video->video_url,
                'titulo' => 'Vídeo Liberado por Addon',
                'plataformas' => ['instagram'],
            ]);

        $response->assertStatus(202);
    }

    public function test_admin_can_configure_encrypted_social_oauth_credentials_and_activate_portal_connect_buttons(): void
    {
        $this->tenant->update([
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Produtora',
            'email' => 'admin@produtora.com',
            'password' => bcrypt('Senha@Segura123'),
            'role' => 'admin',
        ]);

        $saveResponse = $this->actingAs($admin)
            ->post(route('admin.settings.platform.update'), [
                'meta_client_id' => 'meta_app_id_123',
                'meta_client_secret' => 'meta_super_secret_456',
                'tiktok_client_key' => 'tiktok_key_789',
                'tiktok_client_secret' => 'tiktok_secret_999',
            ]);

        $saveResponse->assertRedirect(route('admin.settings.platform'));

        // Verifica que foi salvo criptografado e que loadIntoConfig popula config('services.*')
        $rawMetaSecret = Setting::where('key', 'meta_client_secret')->value('value');
        $this->assertNotNull($rawMetaSecret);
        $this->assertNotEquals('meta_super_secret_456', $rawMetaSecret);
        $this->assertEquals('meta_super_secret_456', Setting::get('meta_client_secret'));
        $this->assertEquals('meta_app_id_123', config('services.meta.client_id'));
        $this->assertEquals('tiktok_key_789', config('services.tiktok.client_key'));

        // A tela do admin nunca deve exibir o secret em texto puro no HTML
        $adminView = $this->actingAs($admin)->get(route('admin.settings.platform'));
        $adminView->assertStatus(200);
        $adminView->assertDontSee('meta_super_secret_456');
        $adminView->assertSee('OAuth Ativo');

        // Agora no Portal do Cliente, Instagram, Facebook e TikTok exibem o botão "Conectar"
        $portalView = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.social.index'));

        $portalView->assertStatus(200);
        $portalView->assertSee('Conectar Instagram');
        $portalView->assertSee('Conectar Facebook');
        $portalView->assertSee('Conectar TikTok');
    }
}
