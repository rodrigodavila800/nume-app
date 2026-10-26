<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VideoType;
use App\Services\VideoTypes\VideoTypeCatalog;
use App\Support\Anamnesis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido de orçamento do cliente no portal: a calculadora recebe a solicitação. Dados fictícios.
 */
class QuoteRequestTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Cliente $cliente;

    private VideoType $sales;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => null, 'sofi.keys.gemini' => null]);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345',
            'company_name' => 'Padaria Ficticia', 'brand_niche' => 'Padaria', 'website' => 'https://padaria.ficticio.test',
        ]);
        $this->cliente->forceFill(['anamnesis' => Anamnesis::normalize([
            'objective' => 'sell_more', 'main_offer' => 'Pão', 'ideal_customer' => 'Bairro', 'tone' => 'close',
            'priority_networks' => ['instagram', 'tiktok'], 'posting_frequency' => '1_week', 'wants_ads' => true, 'ads_monthly_budget' => '200 €',
        ]), 'anamnesis_completed_at' => now()])->save();
        $this->sales = app(VideoTypeCatalog::class)->active($this->tenant->id)->first();
    }

    private function approvedPlan(int $items = 4): MarketingPlan
    {
        $plan = MarketingPlan::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'month_year' => '2026-11']);
        for ($i = 1; $i <= $items; $i++) {
            $plan->items()->create(['week_number' => $i, 'topic' => "Vídeo {$i}", 'video_type_id' => $this->sales->id]);
        }
        $plan->approveBy($this->cliente);

        return $plan;
    }

    public function test_request_needs_an_approved_plan(): void
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.quotes.request'))
            ->assertRedirect(route('portal.marketing-plan.index'));
        $this->actingAs($this->cliente, 'cliente')->post(route('portal.quotes.request.store'), ['videos' => 4, 'social_networks' => 2])
            ->assertRedirect(route('portal.marketing-plan.index'));

        $this->assertSame(0, Quote::forTenant($this->tenant->id)->count());
    }

    public function test_form_is_prefilled_from_the_plan_and_anamnesis(): void
    {
        $this->approvedPlan(6);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.quotes.request'))
            ->assertOk()
            ->assertSee('name="videos" type="number" min="1" max="60" required value="6"', false)
            ->assertSee('name="social_networks" type="number" min="1" max="8" required value="2"', false)
            ->assertSee('value="200 €"', false)
            ->assertSee('<option value="'.$this->sales->id.'" selected>', false);
    }

    public function test_client_request_becomes_a_quote_calculated_from_the_request(): void
    {
        $plan = $this->approvedPlan();

        $this->actingAs($this->cliente, 'cliente')->post(route('portal.quotes.request.store'), [
            'videos' => 8, 'video_type_id' => $this->sales->id, 'social_networks' => 3,
            'wants_ads' => '1', 'ads_budget' => '300 €', 'message' => 'Queremos começar em novembro',
        ])->assertRedirect(route('portal.quotes.index'))->assertSessionHas('status');

        $quote = Quote::forTenant($this->tenant->id)->sole();
        $this->assertSame(Quote::STATUS_REQUESTED, $quote->status);
        $this->assertSame(Quote::KIND_PRODUCTION, $quote->kind);
        $this->assertSame($this->cliente->id, $quote->cliente_id);
        $this->assertSame($plan->id, $quote->marketing_plan_id);
        $this->assertSame(8, $quote->inputs['videos'], 'a calculadora recebe a quantidade pedida');
        $this->assertEquals(24, $quote->inputs['edicao'], '8 vídeos × 3 h de edição');
        $this->assertGreaterThan(0, $quote->total_amount);
        $this->assertSame('Vendas (30 s)', $quote->request_details['video_type_name']);
        $this->assertSame(3, $quote->request_details['social_networks']);
        $this->assertSame('300 €', $quote->request_details['ads_budget']);
        $this->assertStringContainsString('Mensagem do cliente: Queremos começar em novembro', $quote->request_text);
        $this->assertNull($quote->approval_token, 'sem link público até a equipe enviar');

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.quotes.index'))
            ->assertOk()->assertSee('Em análise pela produtora')->assertSee('A produtora está calculando');
    }

    public function test_second_click_does_not_duplicate_the_request(): void
    {
        $this->approvedPlan();
        $payload = ['videos' => 4, 'social_networks' => 2];

        $this->actingAs($this->cliente, 'cliente')->post(route('portal.quotes.request.store'), $payload);
        $this->actingAs($this->cliente, 'cliente')->post(route('portal.quotes.request.store'), $payload)->assertSessionHas('status');

        $this->assertSame(1, Quote::forTenant($this->tenant->id)->count());
    }

    public function test_video_type_of_another_tenant_is_refused(): void
    {
        $this->approvedPlan();
        $outra = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $alheio = app(VideoTypeCatalog::class)->active($outra->id)->first();

        $this->actingAs($this->cliente, 'cliente')->post(route('portal.quotes.request.store'), [
            'videos' => 4, 'social_networks' => 2, 'video_type_id' => $alheio->id,
        ])->assertSessionHasErrors('video_type_id');

        $this->assertSame(0, Quote::forTenant($this->tenant->id)->count());
    }

    public function test_team_sees_the_request_in_its_own_tab(): void
    {
        $this->approvedPlan();
        $this->actingAs($this->cliente, 'cliente')->post(route('portal.quotes.request.store'), ['videos' => 4, 'social_networks' => 2]);
        $editor = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Editor', 'email' => 'editor@ficticio.test', 'password' => 'secret12345', 'role' => 'editor']);

        $this->actingAs($editor)->get(route('quotes.index', ['status' => 'requested']))
            ->assertOk()->assertSee('Pedidos de clientes (1)')->assertSee('Pedido do cliente')->assertSee('Padaria Ficticia');
    }

    public function test_view_has_no_inline_css(): void
    {
        $this->assertStringNotContainsString('style="', file_get_contents(resource_path('views/portal/quotes/request.blade.php')));
    }
}
