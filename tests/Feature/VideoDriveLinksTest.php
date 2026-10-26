<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Links do Drive (revisão e entrega) no vídeo. Dados fictícios.
 */
class VideoDriveLinksTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Cliente $cliente;

    private Production $production;

    private Video $video;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->admin = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345', 'brand_niche' => 'Padaria', 'website' => 'https://padaria.ficticio.test']);
        $purchase = PackPurchase::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'amount' => 100, 'status' => 'paid', 'video_quantity' => 2]);
        $this->production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'pack_purchase_id' => $purchase->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $this->admin->id]);
        $this->video = Video::create(['tenant_id' => $this->tenant->id, 'production_id' => $this->production->id, 'title' => 'Corte', 'status' => 'published', 'video_url' => 'https://cdn.ficticio.test/v.mp4']);
    }

    public function test_team_sets_drive_links_even_after_publishing(): void
    {
        $this->actingAs($this->admin)->patch(route('videos.links', [$this->production, $this->video]), [
            'review_url' => 'https://drive.google.com/file/d/revisao-ficticia/view',
            'delivery_url' => 'https://drive.google.com/drive/folders/entrega-ficticia',
        ])->assertRedirect(route('videos.show', [$this->production, $this->video]));

        $this->assertSame('https://drive.google.com/drive/folders/entrega-ficticia', $this->video->fresh()->delivery_url);
    }

    public function test_only_https_links_are_accepted(): void
    {
        $this->actingAs($this->admin)->patch(route('videos.links', [$this->production, $this->video]), [
            'delivery_url' => 'http://drive.ficticio.test/x',
        ])->assertSessionHasErrors('delivery_url');

        $this->actingAs($this->admin)->patch(route('videos.links', [$this->production, $this->video]), [
            'review_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('review_url');
    }

    public function test_client_sees_the_delivery_link_once_released(): void
    {
        $this->video->update(['delivery_url' => 'https://drive.google.com/drive/folders/entrega-ficticia']);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.productions.show', $this->production))
            ->assertOk()->assertSee('Arquivo final (Drive)')->assertSee('entrega-ficticia');
    }

    public function test_viewer_cannot_change_links(): void
    {
        $viewer = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Visitante', 'email' => 'viewer@ficticio.test', 'password' => 'secret12345', 'role' => 'viewer']);

        $this->actingAs($viewer)->patch(route('videos.links', [$this->production, $this->video]), ['delivery_url' => 'https://drive.google.com/x'])
            ->assertForbidden();
    }
}
