<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Pack;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SofiSalesConsultantTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Pack $starterPack;
    private Pack $authorityPack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Zaragoza Audiovisual',
            'slug' => 'nume-zaragoza',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Redes Sociais',
            'slug' => 'redes-sociais',
            'is_active' => true,
        ]);

        $this->starterPack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'name' => 'Pack Reels Starter',
            'slug' => 'pack-reels-starter',
            'price' => 350.00,
            'video_quantity' => 4,
            'description' => '4 vídeos de alta retenção para Reels e TikTok com roteiro Sofi IA.',
            'status' => 'active',
        ]);

        $this->authorityPack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'name' => 'Pack Autoridade Executiva B2B',
            'slug' => 'pack-autoridade-executiva',
            'price' => 850.00,
            'video_quantity' => 8,
            'description' => '8 vídeos verticais e horizontais em 4K focados em liderança e posicionamento.',
            'status' => 'active',
        ]);
    }

    public function test_storefront_renders_sofi_sales_widget(): void
    {
        $response = $this->get(route('storefront.show', $this->tenant));

        $response->assertOk();
        $response->assertSee('sofiSalesWidget');
        $response->assertSee('Sofi Sales Copilot');
    }

    public function test_public_visitor_can_chat_with_sofi_sales_copilot(): void
    {
        $response = $this->postJson(route('storefront.sofi-chat', $this->tenant), [
            'message' => 'Olá! Qual é o melhor formato de vídeo para atrair clientes para a minha clínica?',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'agent',
            'reply',
            'recommended_pack',
            'quick_replies',
        ]);
        $response->assertJson([
            'status' => 'success',
            'agent' => 'Sofi Sales Copilot',
        ]);
    }

    public function test_sofi_recommends_pack_for_specific_inquiry(): void
    {
        $response = $this->postJson(route('storefront.sofi-chat', $this->tenant), [
            'message' => 'Quero fazer reels para viralizar no TikTok e Instagram.',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertNotEmpty($data['reply']);
        $this->assertNotNull($data['recommended_pack']);
        $this->assertEquals($this->starterPack->id, $data['recommended_pack']['id']);
        $this->assertStringContainsString('350,00', $data['recommended_pack']['price']);
    }

    public function test_sofi_sales_answers_payment_inquiries(): void
    {
        $response = $this->postJson(route('storefront.sofi-chat', $this->tenant), [
            'message' => 'Quais são as formas de pagamento aceitas?',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertTrue(
            str_contains(mb_strtolower($data['reply']), 'bizum') || 
            str_contains(mb_strtolower($data['reply']), 'cartão') || 
            str_contains(mb_strtolower($data['reply']), 'tarjeta')
        );
    }

    public function test_sofi_respects_spanish_locale(): void
    {
        $response = $this->withSession(['locale' => 'es'])
            ->postJson(route('storefront.sofi-chat', $this->tenant), [
                'message' => '¿Dónde está ubicada vuestra productora y cómo grabáis?',
            ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertTrue(
            str_contains(mb_strtolower($data['reply']), 'zaragoza') || 
            str_contains(mb_strtolower($data['reply']), 'nuestra') || 
            str_contains(mb_strtolower($data['reply']), 'españa')
        );
    }

    public function test_sofi_triggers_dna_registration_when_user_asks_for_full_script_or_content_plan(): void
    {
        $response = $this->postJson(route('storefront.sofi-chat', $this->tenant), [
            'message' => 'Quero um roteiro completo e uma grade editorial de 30 dias com base no meu nicho.',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals('register_dna', $data['call_to_action']);
        $this->assertNotEmpty($data['dna_register_url']);
        $this->assertTrue(
            str_contains(mb_strtolower($data['reply']), 'dna') || 
            str_contains(mb_strtolower($data['reply']), 'adn') ||
            str_contains(mb_strtolower($data['reply']), 'portal')
        );
    }

    public function test_storefront_renders_focused_pack_modal(): void
    {
        $response = $this->get(route('storefront.show', $this->tenant));

        $response->assertOk();
        $response->assertSee('sofiPackFocusedModal');
        $response->assertSee('modalPackTitle');
        $response->assertSee('modalRegisterBtn');
    }

    public function test_sofi_chat_validation_rejects_empty_message(): void
    {
        $response = $this->postJson(route('storefront.sofi-chat', $this->tenant), [
            'message' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_reset_sofi_session_via_endpoint(): void
    {
        // Define histórico prévio na sessão
        session(['sofi_sales_history_' . $this->tenant->id => [
            ['role' => 'user', 'text' => 'Sou dentista e quero vídeos'],
        ]]);

        $response = $this->postJson(route('storefront.sofi-chat.reset', $this->tenant));

        $response->assertOk();
        $this->assertFalse(session()->has('sofi_sales_history_' . $this->tenant->id));
    }

    public function test_sofi_handles_change_niche_intent_cleanly(): void
    {
        // Define histórico prévio com outro nicho
        session(['sofi_sales_history_' . $this->tenant->id => [
            ['role' => 'user', 'text' => 'Sou dono de hamburgueria'],
        ]]);

        $response = $this->postJson(route('storefront.sofi-chat', $this->tenant), [
            'message' => 'Quero escolher outro nicho',
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertFalse(session()->has('sofi_sales_history_' . $this->tenant->id));
        $this->assertNotEmpty($data['quick_replies']);
        $this->assertTrue(
            str_contains(mb_strtolower($data['reply']), 'nicho') || 
            str_contains(mb_strtolower($data['reply']), 'estratégia')
        );
    }
}
