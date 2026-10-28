<?php

namespace Tests\Feature;

use App\Jobs\PublishVideoToPlatform;
use App\Models\Cliente;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tela "Publicar no Pinterest" (escolha da pasta) e o desvio do Pinterest direto no modal genérico.
 */
class PinterestComposerTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.pinterest.com/v5';

    private Tenant $tenant;

    private User $staff;

    private Cliente $cliente;

    private Production $production;

    private Video $video;

    private SocialConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Queue::fake();
        Http::preventStrayRequests();
        config(['upload_post.api_key' => null, 'social.pinterest.api_base' => self::API, 'social.routing.pinterest' => 'native']);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123'),
            'brand_niche' => 'Saude', 'website' => 'https://clinica-ficticia.test',
        ]);
        PackPurchase::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'status' => 'paid', 'amount' => 500, 'video_quantity' => 5, 'videos_used' => 0]);
        $this->production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $this->staff->id]);

        Storage::disk('videos')->put($this->tenant->id.'/pin.mp4', 'conteudo-ficticio');
        $this->video = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $this->production->id, 'title' => 'Corte Final', 'status' => 'ready',
            'source_disk' => 'videos', 'source_path' => $this->tenant->id.'/pin.mp4', 'source_mime' => 'video/mp4', 'source_size' => 17,
            'sofi_seo_title' => 'Sorriso novo', 'sofi_seo_description' => 'Tres passos', 'sofi_hashtags' => ['clinica'],
        ]);

        $this->connection = SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'pinterest', 'provider' => 'pinterest',
            'external_account_id' => 'conta-ficticia', 'handle' => '@clinicaficticia', 'access_token' => 'pina.ficticio',
            'refresh_token' => 'pinr.ficticio', 'expires_at' => now()->addDays(20),
        ]);
    }

    public function test_screen_lists_the_account_boards_with_no_default_choice(): void
    {
        $this->fakeBoards();

        $html = $this->actingAs($this->cliente, 'cliente')->get(route('portal.videos.pinterest', $this->video))
            ->assertOk()
            ->assertSee('@clinicaficticia')
            ->assertSee('Receitas de Sorriso')
            ->assertSee('Antes e Depois')
            ->assertSee('Sorriso novo')
            ->assertSee('https://clinica-ficticia.test', false)
            ->assertDontSee('pina.ficticio')
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="" disabled\s+selected/', $html);
    }

    public function test_account_without_boards_is_told_to_create_one(): void
    {
        Http::fake([self::API.'/boards*' => Http::response(['items' => [], 'bookmark' => null])]);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.videos.pinterest', $this->video))
            ->assertRedirect(route('portal.productions.show', $this->production->uuid))
            ->assertSessionHas('error', 'Sua conta do Pinterest não tem nenhuma pasta. Crie uma pasta no Pinterest e tente de novo.');
    }

    public function test_publishing_saves_the_chosen_board_and_queues_the_job(): void
    {
        $this->fakeBoards();

        $this->actingAs($this->cliente, 'cliente')->post(route('portal.videos.pinterest.store', $this->video), [
            'board_id' => '5550002', 'title' => 'Sorriso novo', 'description' => 'Tres passos #clinica', 'link' => 'https://clinica-ficticia.test/agendar',
        ])
            ->assertRedirect(route('portal.productions.show', $this->production->uuid))
            ->assertSessionHas('success');

        $publication = VideoPublication::forTenant($this->tenant->id)->sole();
        $this->assertSame('pinterest', $publication->driver);
        $this->assertSame([
            'board_id' => '5550002', 'title' => 'Sorriso novo', 'description' => 'Tres passos #clinica', 'link' => 'https://clinica-ficticia.test/agendar',
        ], $publication->resultado['pinterest']);
        Queue::assertPushed(PublishVideoToPlatform::class, 1);
    }

    public function test_board_outside_the_account_and_insecure_link_are_rejected(): void
    {
        $this->fakeBoards();
        $this->actingAs($this->cliente, 'cliente');

        $this->post(route('portal.videos.pinterest.store', $this->video), ['board_id' => '999'])->assertSessionHasErrors('board_id');
        $this->post(route('portal.videos.pinterest.store', $this->video), ['board_id' => '5550001', 'link' => 'http://inseguro.test'])->assertSessionHasErrors('link');

        $this->assertSame(0, VideoPublication::forTenant($this->tenant->id)->count());
        Queue::assertNothingPushed();
    }

    public function test_generic_modal_and_staff_panel_send_pinterest_to_its_own_screen(): void
    {
        $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.videos.distribute', $this->video), ['titulo' => 'Lancamento', 'plataformas' => ['pinterest']])
            ->assertRedirect(route('portal.videos.pinterest', $this->video));

        $response = $this->flushSession()
            ->actingAs($this->staff)
            ->post(route('videos.distribute', [$this->production, $this->video]), ['plataformas' => ['pinterest']]);
        $response->assertSessionHas('warning', 'O Pinterest desta conta é conectado direto: o cliente escolhe a pasta e publica pelo portal, em "Publicar no Pinterest".');

        $this->assertSame(0, VideoPublication::forTenant($this->tenant->id)->count());
    }

    public function test_production_page_offers_the_pinterest_button(): void
    {
        $html = $this->actingAs($this->cliente, 'cliente')->get(route('portal.productions.show', $this->production->uuid))
            ->assertOk()
            ->assertSee(route('portal.videos.pinterest', $this->video), false)
            ->assertSee('Publicar no Pinterest')
            ->getContent();

        $this->assertStringNotContainsString('name="plataformas[]" value="pinterest"', $html);
    }

    public function test_screen_has_no_inline_css(): void
    {
        $this->assertStringNotContainsString('style="', file_get_contents(resource_path('views/portal/social/pinterest-composer.blade.php')));
    }

    private function fakeBoards(): void
    {
        Http::fake([self::API.'/boards*' => Http::response(['items' => [
            ['id' => '5550001', 'name' => 'Receitas de Sorriso', 'privacy' => 'PUBLIC'],
            ['id' => '5550002', 'name' => 'Antes e Depois', 'privacy' => 'PUBLIC'],
        ], 'bookmark' => null])]);
    }
}
