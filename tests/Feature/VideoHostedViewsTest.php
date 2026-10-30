<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Telas com vídeo hospedado na NUME (arquivo próprio). Dados fictícios, disco falso, sem rede.
 */
class VideoHostedViewsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Cliente $cliente;

    private Production $production;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Http::preventStrayRequests();

        $tenant = Tenant::create(['name' => 'Produtora Aurora', 'slug' => 'produtora-aurora', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->staff = User::create(['tenant_id' => $tenant->id, 'name' => 'Staff A', 'email' => 'staff-a@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'cliente-a@ficticio.test', 'password' => bcrypt('secret123'),
            'brand_niche' => 'Saude', 'website' => 'https://clinica-ficticia.test',
        ]);
        $this->production = Production::create(['tenant_id' => $tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Producao Ficticia', 'status' => 'draft', 'created_by' => $this->staff->id]);
    }

    public function test_create_form_is_accessible_without_approved_script(): void
    {
        $this->actingAs($this->staff)->get(route('videos.create', $this->production))
            ->assertOk()
            ->assertSee('Cadastrar Novo Vídeo');
    }

    public function test_create_form_accepts_a_file_and_the_link_is_optional(): void
    {
        Roteiro::create([
            'tenant_id' => $this->production->tenant_id,
            'production_id' => $this->production->id,
            'created_by' => $this->staff->id,
            'content' => 'Roteiro ficticio aprovado',
            'status' => 'approved',
        ]);

        $this->actingAs($this->staff)->get(route('videos.create', $this->production))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="video_file"', false)
            ->assertSee('accept="video/mp4,video/quicktime,.mp4,.mov"', false)
            ->assertDontSee('name="video_url" id="video_url" required', false);
    }

    public function test_edit_form_shows_the_hosted_file_and_the_remove_option(): void
    {
        $video = $this->hostedVideo(['status' => 'processing']);

        $this->actingAs($this->staff)->get(route('videos.edit', [$this->production, $video]))
            ->assertOk()
            ->assertSee('Arquivo hospedado na NUME')
            ->assertSee('name="remove_video_file"', false)
            ->assertSee('Substituir arquivo de vídeo');
    }

    public function test_staff_page_plays_the_hosted_file_and_offers_download(): void
    {
        $video = $this->hostedVideo();

        $this->actingAs($this->staff)->get(route('videos.show', [$this->production, $video]))
            ->assertOk()
            ->assertSee('/media/videos/'.$video->id, false)
            ->assertSee('download=1', false)
            ->assertSee('Baixar arquivo do vídeo')
            ->assertSee('Será enviado o arquivo hospedado na NUME')
            ->assertDontSee('Nenhuma URL de vídeo cadastrada.');
    }

    public function test_portal_plays_the_hosted_file_with_download_and_publish_when_paid(): void
    {
        $video = $this->hostedVideo();

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.productions.show', $this->production->uuid))
            ->assertOk()
            ->assertSee('/media/videos/'.$video->id, false)
            ->assertSee('download=1', false)
            ->assertSee('Publicar Direto nas Redes Conectadas');
    }

    public function test_portal_keeps_preview_but_hides_download_while_in_escrow(): void
    {
        $video = $this->hostedVideo();
        $purchase = PackPurchase::create([
            'tenant_id' => $this->production->tenant_id, 'cliente_id' => $this->cliente->id, 'status' => 'paid',
            'amount' => 500, 'video_quantity' => 5, 'videos_used' => 0,
            'billing_mode' => 'split_50_50', 'balance_amount' => 500, 'balance_status' => 'pending',
        ]);
        $this->production->update(['pack_purchase_id' => $purchase->id]);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.productions.show', $this->production->uuid))
            ->assertOk()
            ->assertSee('/media/videos/'.$video->id, false)
            ->assertSee('Aprovar Corte para Liberar Download')
            ->assertDontSee('download=1', false);
    }

    public function test_portal_link_only_video_is_unchanged(): void
    {
        Video::create([
            'tenant_id' => $this->production->tenant_id, 'production_id' => $this->production->id,
            'title' => 'So link', 'status' => 'ready', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.productions.show', $this->production->uuid))
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertDontSee('/media/videos/', false);
    }

    public function test_legacy_magic_page_plays_the_file_without_exposing_download(): void
    {
        $video = $this->hostedVideo();

        $this->get(URL::temporarySignedRoute('magic.videos.approval-legacy', now()->addHour(), ['video' => $video->uuid]))
            ->assertOk()
            ->assertSee('/media/videos/'.$video->id, false)
            ->assertSee('disponível para download no seu portal')
            ->assertDontSee('download=1', false);
    }

    public function test_changed_views_have_no_inline_css(): void
    {
        foreach (['videos/_source-fields', 'videos/create', 'videos/edit', 'videos/show', 'portal/productions/show', 'magic/video'] as $view) {
            $this->assertStringNotContainsString('style="', file_get_contents(resource_path("views/{$view}.blade.php")), "CSS inline em {$view}");
        }
    }

    private function hostedVideo(array $attributes = []): Video
    {
        $path = $this->production->tenant_id.'/'.uniqid().'.mp4';
        Storage::disk('videos')->put($path, 'conteudo-ficticio');

        return Video::create(array_merge([
            'tenant_id' => $this->production->tenant_id,
            'production_id' => $this->production->id,
            'title' => 'Corte Final',
            'status' => 'ready',
            'source_disk' => 'videos',
            'source_path' => $path,
            'source_mime' => 'video/mp4',
            'source_size' => 17,
        ], $attributes));
    }
}
