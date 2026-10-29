<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MagicVideoSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Cliente $cliente;

    protected User $staff;

    protected Production $production;

    protected Video $video;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha']);
        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Empresa Segura',
            'company_name' => 'Segura S.A.',
            'email' => 'cliente@segura.com',
            'phone' => '+34600998877',
            'password' => bcrypt('senhaForte123!'),
            'brand_niche' => 'Segurança da Informação',
            'target_audience' => 'Diretores de Segurança',
        ]);

        $this->production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->staff->id,
            'title' => 'Vídeo Institucional Final',
            'status' => 'in_production',
        ]);

        $this->video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'title' => 'Corte Final V1',
            'status' => 'processing',
        ]);
    }

    public function test_magic_video_url_uses_uuid_and_not_integer_id(): void
    {
        $this->assertNotEmpty($this->video->uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $this->video->uuid);

        $url = $this->video->getMagicApprovalUrl(14);

        // A URL gerada DEVE conter o UUID e NÃO o id inteiro sequencial
        $this->assertStringContainsString($this->video->uuid, $url);
        $this->assertStringNotContainsString('/m/videos/'.$this->video->id.'?', $url);
    }

    public function test_opening_magic_video_link_does_not_authenticate_user_and_renders_approval_view(): void
    {
        $magicUrl = $this->video->getMagicApprovalUrl(14);

        $response = $this->get($magicUrl);

        $response->assertStatus(200);
        $response->assertViewIs('magic.video');
        $response->assertSee('Corte Final V1');

        // GARANTIA MÁXIMA DE SEGURANÇA: Nenhum usuário/cliente deve estar logado
        $this->assertGuest('cliente');
        $this->assertGuest('web');
    }

    public function test_visitor_cannot_access_portal_after_opening_magic_link(): void
    {
        $magicUrl = $this->video->getMagicApprovalUrl(14);

        // Abre o link mágico (página pública de aprovação com assinatura válida)
        $this->get($magicUrl)->assertStatus(200);

        // Tenta acessar o portal de clientes logados
        $portalResponse = $this->get(route('portal.productions.show', $this->production->uuid));

        // Deve ser redirecionado para o login obrigatoriamente
        $portalResponse->assertRedirect(route('portal.login'));
        $this->assertGuest('cliente');
    }

    public function test_invalid_or_expired_signature_is_rejected(): void
    {
        // URL adulterada (sem assinatura válida)
        $tamperedUrl = url("/m/videos/{$this->video->uuid}?expires=9999999999&signature=invalida");

        $response = $this->get($tamperedUrl);

        $response->assertStatus(403);
    }

    public function test_approving_video_via_magic_link_works_without_session_login(): void
    {
        $approveUrl = URL::temporarySignedRoute('magic.videos.approve', now()->addMinutes(30), [
            'video' => $this->video->uuid,
        ]);

        $response = $this->post($approveUrl);

        $response->assertRedirect();
        $this->video->refresh();

        $this->assertSame('ready', $this->video->status);
        $this->assertGuest('cliente');
    }
}
