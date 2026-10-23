<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MagicApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Cliente $cliente;
    private Production $production;
    private Roteiro $roteiro;
    private Video $video;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Alpha',
            'slug' => 'produtora-alpha',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Staff Alpha',
            'email' => 'alpha@nume.test',
            'password' => bcrypt('secret123'),
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Alpha',
            'email' => 'cliente@alpha.test',
            'company_name' => 'Alpha Motors',
            'password' => bcrypt('secret123'),
        ]);

        $this->production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->user->id,
            'title' => 'Vídeo Institucional Lançamento',
            'status' => 'in_production',
        ]);

        $this->roteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'created_by' => $this->user->id,
            'content' => "HOOK: Você não vai acreditar nisso...\nCORPO: Explicamos os diferenciais...\nCTA: Comente EU QUERO.",
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'title' => 'Corte Final v1',
            'video_url' => 'https://vimeo.com/12345678',
            'status' => 'processing',
        ]);
    }

    public function test_unsigned_access_to_magic_roteiro_is_forbidden(): void
    {
        $response = $this->get(route('magic.roteiros.show', $this->roteiro));

        $response->assertStatus(403);
    }

    public function test_signed_magic_link_allows_unauthenticated_client_to_view_roteiro(): void
    {
        $signedUrl = $this->roteiro->getMagicApprovalUrl(7);

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertSee('Vídeo Institucional Lançamento');
        $response->assertSee('Alpha Motors');
        $response->assertSee('Você não vai acreditar nisso');
        $response->assertSee('Aprovar Roteiro Agora (1-Clique)');
    }

    public function test_client_can_approve_roteiro_via_signed_post(): void
    {
        $signedApproveUrl = URL::temporarySignedRoute(
            'magic.roteiros.approve',
            now()->addHour(),
            ['roteiro' => $this->roteiro->uuid]
        );

        $response = $this->post($signedApproveUrl);

        $response->assertSessionHas('success');
        $this->roteiro->refresh();
        $this->assertEquals('approved', $this->roteiro->status);
        $this->assertEquals($this->cliente->id, $this->roteiro->approved_by_cliente_id);
    }

    public function test_client_can_reject_roteiro_with_feedback_via_signed_post(): void
    {
        $signedRejectUrl = URL::temporarySignedRoute(
            'magic.roteiros.reject',
            now()->addHour(),
            ['roteiro' => $this->roteiro->uuid]
        );

        $response = $this->post($signedRejectUrl, [
            'rejection_reason' => 'Por favor, ajuste o gancho inicial para citar a nova filial.',
        ]);

        $response->assertSessionHas('success');
        $this->roteiro->refresh();
        $this->assertEquals('rejected', $this->roteiro->status);
        $this->assertStringContainsString('nova filial', $this->roteiro->rejection_reason);
    }

    public function test_signed_magic_link_allows_unauthenticated_client_to_view_and_approve_video(): void
    {
        $signedUrl = $this->video->getMagicApprovalUrl(7);

        $response = $this->get($signedUrl);

        $response->assertStatus(302);
        $response->assertRedirect(route('portal.productions.show', $this->production->uuid));
        $this->assertAuthenticatedAs($this->cliente, 'cliente');

        $signedApproveUrl = URL::temporarySignedRoute(
            'magic.videos.approve',
            now()->addHour(),
            ['video' => $this->video->id]
        );

        $postResponse = $this->post($signedApproveUrl);

        $postResponse->assertSessionHas('success');
        $this->video->refresh();
        $this->assertEquals('ready', $this->video->status);
    }

    public function test_whatsapp_share_url_is_properly_formatted(): void
    {
        $waRoteiroUrl = $this->roteiro->getWhatsAppShareUrl();
        $this->assertStringContainsString('api.whatsapp.com/send?text=', $waRoteiroUrl);
        $this->assertStringContainsString(urlencode($this->cliente->name), $waRoteiroUrl);

        $waVideoUrl = $this->video->getWhatsAppShareUrl();
        $this->assertStringContainsString('api.whatsapp.com/send?text=', $waVideoUrl);
        $this->assertStringContainsString(urlencode('Corte Final v1'), $waVideoUrl);
    }
}
