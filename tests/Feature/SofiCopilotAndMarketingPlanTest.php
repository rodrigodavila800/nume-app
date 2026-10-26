<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\SofiConversation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BrandDnaCrawlerService;
use App\Services\MarketingPlanGeneratorService;
use App\Services\SofiCopilotChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SofiCopilotAndMarketingPlanTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Production Hub',
            'slug' => 'nume-hub',
            'domain' => 'hub.nume.test',
            'status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Carlos Zaragoza',
            'email' => 'carlos@empresa.com',
            'password' => bcrypt('secret123'),
            'company_name' => 'Clínica Zaragoza Dental',
            'brand_niche' => 'Odontologia Avançada e Implantes',
            'target_audience' => 'Adultos de 30 a 60 anos que buscam estética dental',
            'key_differentials' => 'Tecnologia 3D, sedação consciente e mais de 20 anos de experiência',
            'website' => 'https://zaragozadental.com',
            'instagram_handle' => '@zaragozadental',
        ]);
        $this->cliente->forceFill([
            'anamnesis' => \App\Support\Anamnesis::normalize([
                'objective' => 'sell_more', 'main_offer' => 'Implantes', 'ideal_customer' => 'Adultos', 'tone' => 'expert',
                'priority_networks' => ['instagram'], 'posting_frequency' => '1_week', 'wants_ads' => false,
            ]),
            'anamnesis_completed_at' => now(),
        ])->save();
    }

    public function test_sofi_copilot_service_replies_and_persists_messages(): void
    {
        $chatService = app(SofiCopilotChatService::class);

        $conversation = SofiConversation::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'title' => 'Briefing Inicial',
        ]);

        $reply = $chatService->reply($conversation, 'Sofi, qual o melhor gancho de vídeo para atrair pacientes para implantes?');

        $this->assertNotNull($reply);
        $this->assertEquals('sofi', $reply->sender);
        $this->assertNotEmpty($reply->content);

        // Verifica persistência no banco
        $this->assertDatabaseHas('sofi_messages', [
            'sofi_conversation_id' => $conversation->id,
            'sender' => 'user',
            'content' => 'Sofi, qual o melhor gancho de vídeo para atrair pacientes para implantes?',
        ]);

        $this->assertDatabaseHas('sofi_messages', [
            'sofi_conversation_id' => $conversation->id,
            'sender' => 'sofi',
        ]);
    }

    public function test_api_sofi_chat_endpoint_works_for_authenticated_client(): void
    {
        $response = $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.sofi.chat'), [
                'message' => 'Como posso melhorar a taxa de retenção nos primeiros 3 segundos?',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertArrayHasKey('reply', $response->json());
        $this->assertArrayHasKey('conversation_id', $response->json());
    }

    public function test_marketing_plan_generator_creates_complete_editorial_funnel(): void
    {
        $planGenerator = app(MarketingPlanGeneratorService::class);

        $plan = $planGenerator->generate($this->cliente, 4);

        $this->assertInstanceOf(MarketingPlan::class, $plan);
        $this->assertEquals(4, $plan->items()->count());

        // Verifica que o plano cobre as etapas do funil
        $stages = $plan->items()->pluck('funnel_stage')->toArray();
        $this->assertContains('top', $stages);
        $this->assertContains('middle', $stages);
        $this->assertContains('bottom', $stages);

        // Verifica que os ganchos e horários foram atribuídos
        $firstItem = $plan->items()->first();
        $this->assertNotEmpty($firstItem->hook_text);
        $this->assertNotEmpty($firstItem->scheduled_day_time);
        $this->assertNotEmpty($firstItem->topic);
    }

    public function test_client_can_view_marketing_plan_in_portal(): void
    {
        $response = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.marketing-plan.index'));

        $response->assertStatus(200);
        $response->assertSee('Calendario Editorial &amp; Plan de Marketing', false);
        $response->assertSee('Clínica Zaragoza Dental');
        $response->assertSee('Semana 1');
    }

    public function test_client_cannot_access_other_clients_chat_messages(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Outro Tenant',
            'slug' => 'outro-tenant',
            'domain' => 'outro.test',
            'status' => 'active',
        ]);

        $otherClient = Cliente::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Outro Usuário',
            'email' => 'outro@email.com',
            'password' => bcrypt('password'),
        ]);

        $otherConversation = SofiConversation::create([
            'tenant_id' => $otherTenant->id,
            'cliente_id' => $otherClient->id,
            'title' => 'Conversa Privada',
        ]);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->getJson(route('portal.sofi.messages', $otherConversation->id));

        // O escopo global BelongsToTenant oculta registros de outro tenant com 404
        $response->assertStatus(404);
    }

    public function test_client_can_convert_marketing_plan_item_to_production(): void
    {
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Equipe Real', 'email' => 'equipe@nume-hub.test', 'password' => 'secret12345', 'role' => 'admin']);
        $planGenerator = app(MarketingPlanGeneratorService::class);
        $plan = $planGenerator->generate($this->cliente, 4);
        $plan->approveBy($this->cliente);
        $item = $plan->items()->first();

        $this->assertNull($item->production_id);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.marketing-plan.convert-item', $item));

        $item->refresh();

        $this->assertNotNull($item->production_id);
        $this->assertEquals('in_production', $item->status);

        $production = $item->production;
        $this->assertNotNull($production);
        $this->assertEquals($item->topic, $production->title);
        $this->assertEquals($this->cliente->id, $production->cliente_id);
        $this->assertEquals($this->tenant->id, $production->tenant_id);

        // Verifica que o roteiro inicial foi gerado
        $roteiro = $production->roteiros()->first();
        $this->assertNotNull($roteiro);
        $this->assertStringContainsString('CENA 1', $roteiro->content);
        $this->assertSame($staff->id, $production->created_by);

        $response->assertRedirect(route('portal.productions.show', $production));
    }

    public function test_converting_without_a_team_never_creates_a_user_with_a_known_password(): void
    {
        $plan = app(MarketingPlanGeneratorService::class)->generate($this->cliente, 4);
        $plan->approveBy($this->cliente);
        $item = $plan->items()->first();

        $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.marketing-plan.convert-item', $item))
            ->assertRedirect(route('portal.marketing-plan.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, User::withoutGlobalScopes()->count());
        $this->assertNull($item->fresh()->production_id);
    }

    public function test_idor_protection_prevents_converting_other_clients_plan_item(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Tenant Concorrente',
            'slug' => 'tenant-concorrente',
            'domain' => 'concorrente.test',
            'status' => 'active',
        ]);

        $otherClient = Cliente::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Cliente Invasor',
            'email' => 'invasor@email.com',
            'password' => bcrypt('password'),
        ]);

        $planGenerator = app(MarketingPlanGeneratorService::class);
        $otherPlan = $planGenerator->generate($otherClient, 4);
        $otherItem = $otherPlan->items()->first();

        // Tenta converter item de outro cliente
        $response = $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.marketing-plan.convert-item', $otherItem));

        $response->assertStatus(403);
        $this->assertNull($otherItem->fresh()->production_id);
    }

    public function test_client_can_trigger_sofi_brand_dna_analysis_and_view_dossier(): void
    {
        // Sem rede real: HTML simulado e checagem anti-SSRF (que resolve DNS) desligada só neste teste
        Http::preventStrayRequests();
        Http::fake([
            'zaragozadental.com*' => Http::response(
                '<html><head><title>Clínica Zaragoza Dental</title>'
                .'<meta name="description" content="Implantes dentários guiados e alinhadores invisíveis em Zaragoza."></head>'
                .'<body><h1>Implantes sem dor</h1><p>Estética dental para adultos.</p></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);
        $this->partialMock(BrandDnaCrawlerService::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods()->shouldReceive('isSafeUrl')->andReturn(true);
        });

        $response = $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.profile.analyze-dna'), [
                'website' => 'https://zaragozadental.com',
            ]);

        $response->assertRedirect(route('portal.profile.edit'));
        $this->cliente->refresh();

        $this->assertNotNull($this->cliente->brand_study);
        $this->assertNotNull($this->cliente->brand_study_generated_at);
        $this->assertArrayHasKey('unique_value_proposition', $this->cliente->brand_study);
        $this->assertArrayHasKey('flagship_offerings', $this->cliente->brand_study);
        $this->assertArrayHasKey('target_audience_pains', $this->cliente->brand_study);
        $this->assertArrayHasKey('content_pillars', $this->cliente->brand_study);

        // Acessa a view e valida que o Dossiê é renderizado
        $viewResponse = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.profile.edit'));

        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Dossiê Estratégico &amp; Crawler de Site Sofi IA', false);
        $viewResponse->assertSee('Calibrado');
        $viewResponse->assertSee($this->cliente->brand_study['unique_value_proposition']);
    }

    public function test_marketing_plan_incorporates_brand_study_offerings_and_pains(): void
    {
        $this->cliente->update([
            'brand_study' => [
                'unique_value_proposition' => 'Clínica pioneira em Implantes Guiados por Computador na Espanha.',
                'tone_of_voice' => 'Autoridade médica, acolhedora e precisa',
                'flagship_offerings' => ['Implantes Dentários Guiados', 'Alinhadores Invisíveis'],
                'target_audience_pains' => ['Medo de dor e cirurgias invasivas prolongadas'],
                'content_pillars' => [
                    ['pillar' => 'Mitos de Implante Dental', 'focus' => 'Quebrando o medo da anestesia'],
                ],
                'forbidden_words' => ['preço baixo', 'promoção relâmpago'],
                'suggested_hooks' => [
                    '⚠️ Sabia que implantes dentários modernos não precisam de cortes extensos nem dor?',
                ],
            ],
            'brand_study_generated_at' => now(),
        ]);

        $planGenerator = app(MarketingPlanGeneratorService::class);
        $plan = $planGenerator->generate($this->cliente->fresh(), 4);

        $this->assertStringContainsString('Implantes Guiados', $plan->strategic_goal);

        $firstItem = $plan->items()->first();
        $this->assertStringContainsString('implantes dentários modernos', $firstItem->hook_text);
    }
}


