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
 * Tela "Publicar no TikTok" (regras de UX do Direct Post) e o desvio do TikTok direto no modal genérico.
 * Dados fictícios, fila falsa, API do TikTok simulada.
 */
class TikTokComposerTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://open.tiktokapis.com';

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
        config([
            'upload_post.api_key' => null,
            'services.tiktok.client_key' => 'chave-tiktok-ficticia',
            'services.tiktok.client_secret' => 'segredo-tiktok-ficticio',
            'social.routing.tiktok' => 'native',
        ]);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123'),
            'brand_niche' => 'Saude', 'website' => 'https://clinica-ficticia.test',
        ]);
        PackPurchase::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'status' => 'paid', 'amount' => 500, 'video_quantity' => 5, 'videos_used' => 0]);
        $this->production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $this->staff->id]);

        Storage::disk('videos')->put($this->tenant->id.'/tk.mp4', 'conteudo-ficticio');
        $this->video = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $this->production->id, 'title' => 'Corte Final', 'status' => 'ready',
            'source_disk' => 'videos', 'source_path' => $this->tenant->id.'/tk.mp4', 'source_mime' => 'video/mp4', 'source_size' => 17,
            'duration_seconds' => 30, 'sofi_hashtags' => ['clinica'],
        ]);

        $this->connection = SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'tiktok', 'provider' => 'tiktok',
            'external_account_id' => 'open-id-ficticio', 'handle' => '@clinicaficticia', 'access_token' => 'act.ficticio',
            'refresh_token' => 'rft.ficticio', 'expires_at' => now()->addHours(20),
        ]);
    }

    public function test_screen_follows_the_tiktok_ux_rules(): void
    {
        $this->fakeCreator();

        $html = $this->actingAs($this->cliente, 'cliente')->get(route('portal.videos.tiktok', $this->video))
            ->assertOk()
            ->assertSee('Clinica Ficticia')
            ->assertSee('@clinicaficticia')
            ->assertSee('/media/videos/'.$this->video->id, false)
            ->assertSee('music-usage-confirmation', false)
            ->assertSee('Divulgar conteúdo comercial')
            ->assertDontSee('act.ficticio')
            ->getContent();

        // Privacidade: só as opções do criador e nenhuma pré-selecionada.
        $this->assertStringContainsString('value="PUBLIC_TO_EVERYONE"', $html);
        $this->assertStringContainsString('value="SELF_ONLY"', $html);
        $this->assertStringNotContainsString('value="FOLLOWER_OF_CREATOR"', $html);
        $this->assertMatchesRegularExpression('/<option value="" disabled\s+selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="(PUBLIC_TO_EVERYONE|SELF_ONLY|MUTUAL_FOLLOW_FRIENDS)"\s+selected/', $html);

        // Interações desmarcadas; stitch travado porque o criador desativou.
        $this->assertDoesNotMatchRegularExpression('/name="allow_(comment|duet|stitch)" value="1"\s+checked/', $html);
        $this->assertMatchesRegularExpression('/name="allow_stitch" value="1"\s+disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="allow_comment" value="1"\s+disabled/', $html);

        $this->assertStringContainsString('#clinica', $html, 'legenda sugerida pela Sofi, editável');
    }

    public function test_video_longer_than_the_account_allows_cannot_be_published(): void
    {
        $this->fakeCreator(['max_video_post_duration_sec' => 15]);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.videos.tiktok', $this->video))
            ->assertOk()
            ->assertSee('Não é possível publicá-lo nesta conta.')
            ->assertSee('id="publish-button" disabled', false);
    }

    public function test_publishing_saves_the_choices_and_queues_the_job(): void
    {
        $this->fakeCreator();

        $this->actingAs($this->cliente, 'cliente')->post(route('portal.videos.tiktok.store', $this->video), [
            'title' => 'Minha legenda #clinica',
            'privacy_level' => 'PUBLIC_TO_EVERYONE',
            'allow_comment' => '1', 'allow_duet' => '0', 'allow_stitch' => '1',
            'commercial' => '1', 'brand_organic' => '1', 'brand_content' => '0',
        ])
            ->assertRedirect(route('portal.productions.show', $this->production->uuid))
            ->assertSessionHas('success', 'Vídeo enviado ao TikTok. O processamento pode levar alguns minutos até aparecer no seu perfil.');

        $publication = VideoPublication::forTenant($this->tenant->id)->sole();
        $this->assertSame('tiktok', $publication->driver);
        $this->assertSame($this->connection->id, $publication->social_connection_id);
        $this->assertSame([
            'title' => 'Minha legenda #clinica',
            'privacy_level' => 'PUBLIC_TO_EVERYONE',
            'allow_comment' => true,
            'allow_duet' => false,
            'allow_stitch' => false, // desativado pelo criador: nunca ligado por aqui
            'brand_organic' => true,
            'brand_content' => false,
        ], $publication->resultado['tiktok']);

        Queue::assertPushed(PublishVideoToPlatform::class, fn ($job) => $job->publicationId === $publication->id && $job->tenantId === $this->tenant->id);
    }

    public function test_server_enforces_the_rules_even_if_the_screen_is_bypassed(): void
    {
        $this->fakeCreator(['privacy_level_options' => ['FOLLOWER_OF_CREATOR', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY']]);
        $this->actingAs($this->cliente, 'cliente');

        $this->post(route('portal.videos.tiktok.store', $this->video), ['title' => 'x'])->assertSessionHasErrors('privacy_level');
        $this->post(route('portal.videos.tiktok.store', $this->video), ['privacy_level' => 'PUBLIC_TO_EVERYONE'])->assertSessionHasErrors('privacy_level');
        $this->post(route('portal.videos.tiktok.store', $this->video), ['privacy_level' => 'SELF_ONLY', 'commercial' => '1'])->assertSessionHasErrors('commercial');
        $this->post(route('portal.videos.tiktok.store', $this->video), ['privacy_level' => 'SELF_ONLY', 'commercial' => '1', 'brand_content' => '1'])
            ->assertSessionHasErrors(['privacy_level' => 'Conteúdo de marca não pode ser privado. Escolha outra visibilidade.']);

        $this->assertSame(0, VideoPublication::forTenant($this->tenant->id)->count());
        Queue::assertNothingPushed();
    }

    public function test_creator_blocked_from_posting_is_told_before_anything_is_sent(): void
    {
        Http::fake([self::API.'/v2/post/publish/creator_info/query/' => Http::response(['data' => [], 'error' => ['code' => 'spam_risk_too_many_posts', 'message' => 'x']])]);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.videos.tiktok', $this->video))
            ->assertRedirect(route('portal.productions.show', $this->production->uuid))
            ->assertSessionHas('error', 'O TikTok não permite mais publicações nesta conta hoje. Tente amanhã.');
    }

    public function test_access_rules_match_the_portal_publishing_rules(): void
    {
        $outro = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'B',
            'email' => 'b@ficticio.test',
            'password' => bcrypt('secret123'),
            'brand_niche' => 'Saude',
            'website' => 'https://outro.test',
        ]);
        $this->actingAs($outro, 'cliente')->get(route('portal.videos.tiktok', $this->video))->assertForbidden();

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'status' => 'paid', 'amount' => 500, 'video_quantity' => 5,
            'videos_used' => 0, 'billing_mode' => 'split_50_50', 'balance_amount' => 500, 'balance_status' => 'pending',
        ]);
        $this->production->update(['pack_purchase_id' => $purchase->id]);
        $this->flushSession()->actingAs($this->cliente, 'cliente')->get(route('portal.videos.tiktok', $this->video))->assertForbidden();

        $this->production->update(['pack_purchase_id' => null]);
        $this->connection->update(['status' => SocialConnection::STATUS_REVOKED]);
        $this->flushSession()->actingAs($this->cliente->fresh(), 'cliente')->get(route('portal.videos.tiktok', $this->video))->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_generic_modal_sends_tiktok_to_its_own_screen(): void
    {
        $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.videos.distribute', $this->video), ['titulo' => 'Lancamento', 'plataformas' => ['tiktok']])
            ->assertRedirect(route('portal.videos.tiktok', $this->video));

        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $this->video), ['titulo' => 'Lancamento', 'plataformas' => ['tiktok']])
            ->assertStatus(202)
            ->assertJsonPath('composer.tiktok', route('portal.videos.tiktok', $this->video));

        $this->assertSame(0, VideoPublication::forTenant($this->tenant->id)->count());
        Queue::assertNothingPushed();
    }

    public function test_staff_cannot_publish_to_a_directly_connected_tiktok(): void
    {
        $this->actingAs($this->staff)
            ->post(route('videos.distribute', [$this->production, $this->video]), ['plataformas' => ['tiktok']])
            ->assertRedirect()
            ->assertSessionHas('warning', 'O TikTok desta conta é conectado direto: o cliente precisa publicar pelo portal, em "Publicar no TikTok".');

        $this->assertSame(0, VideoPublication::forTenant($this->tenant->id)->count());
    }

    public function test_production_page_offers_the_tiktok_button_and_lists_direct_networks_in_the_modal(): void
    {
        SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'youtube', 'provider' => 'google',
            'external_account_id' => 'UC-ficticio', 'access_token' => 'ya29.ficticio', 'refresh_token' => '1//ficticio', 'expires_at' => now()->addHour(),
        ]);

        $html = $this->actingAs($this->cliente, 'cliente')->get(route('portal.productions.show', $this->production->uuid))
            ->assertOk()
            ->assertSee(route('portal.videos.tiktok', $this->video), false)
            ->assertSee('Publicar no TikTok')
            ->getContent();

        $this->assertStringContainsString('name="plataformas[]" value="youtube"', $html, 'rede conectada direto aparece no modal');
        $this->assertStringNotContainsString('name="plataformas[]" value="tiktok"', $html, 'TikTok direto vai pela tela própria');
    }

    public function test_composer_screen_has_no_inline_css(): void
    {
        foreach (['portal/social/tiktok-composer', 'portal/productions/show'] as $view) {
            $this->assertStringNotContainsString('style="', file_get_contents(resource_path("views/{$view}.blade.php")), $view);
        }
    }

    private function fakeCreator(array $overrides = []): void
    {
        Http::fake([self::API.'/v2/post/publish/creator_info/query/' => Http::response(['data' => array_merge([
            'creator_username' => 'clinicaficticia', 'creator_nickname' => 'Clinica Ficticia',
            'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'],
            'comment_disabled' => false, 'duet_disabled' => false, 'stitch_disabled' => true, 'max_video_post_duration_sec' => 600,
        ], $overrides), 'error' => ['code' => 'ok']])]);
    }
}
