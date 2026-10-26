<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\PackPurchase;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Quotes\QuoteCalculatorService;
use App\Services\Quotes\QuotePdfService;
use App\Support\Anamnesis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Orçamento de anúncios: a NUME cobra criativos e gestão; a verba é paga pelo cliente à plataforma.
 * Dados fictícios.
 */
class AdsQuoteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => null, 'sofi.keys.gemini' => null]);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->admin = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345', 'company_name' => 'Padaria Ficticia', 'brand_niche' => 'Padaria', 'website' => 'https://padaria.ficticio.test']);
    }

    private function adsPayload(array $inputs = []): array
    {
        return [
            'kind' => 'ads', 'client_name' => 'Padaria Ficticia', 'client_email' => 'ana@ficticio.test', 'language' => 'es',
            'inputs' => array_merge([
                'criativos' => 3, 'valor_criativo' => 100, 'gestao_mensal' => 200, 'meses' => 2, 'verba_mensal' => 500,
                'plataformas' => ['meta', 'tiktok'], 'iva' => 21, 'adicional' => 0, 'prazo' => 10,
            ], $inputs),
        ];
    }

    public function test_ads_calculation_excludes_the_ad_budget_from_the_total(): void
    {
        $calc = app(QuoteCalculatorService::class)->calculateAds($this->adsPayload(['plataformas' => ['meta', 'tiktok', 'orkut']])['inputs']);

        $this->assertEquals(300, $calc['criacao']);
        $this->assertEquals(400, $calc['gestao']);
        $this->assertEquals(700, $calc['preco']);
        $this->assertEquals(847, $calc['total_com_iva']);
        $this->assertEquals(847, $calc['sinal'], 'pagamento integral na aprovação');
        $this->assertEquals(0, $calc['na_entrega']);
        $this->assertEquals(1000, $calc['verba_total_info'], 'verba só informativa');
        $this->assertSame(['meta', 'tiktok'], $calc['inputs_sanitized']['plataformas']);
    }

    public function test_team_creates_an_ads_quote_and_the_pdf_explains_the_budget(): void
    {
        $this->actingAs($this->admin)->get(route('quotes.create', ['kind' => 'ads']))->assertOk()->assertSee('Novo orçamento de anúncios');

        $this->actingAs($this->admin)->post(route('quotes.store'), $this->adsPayload())->assertRedirect();

        $quote = Quote::forTenant($this->tenant->id)->sole();
        $this->assertTrue($quote->isAds());
        $this->assertEquals(847, $quote->total_amount);
        $this->assertEquals(0, $quote->balance_amount);

        $this->actingAs($this->admin)->get(route('quotes.edit', $quote))->assertOk()->assertSee('Verba (fora do total)');

        $data = app(QuotePdfService::class)->preparePdfData($quote);
        $this->assertContains('3 creatividades publicitarias (vídeo o imagen)', $data['deliverables']);
        $this->assertStringContainsString('no incluida en este presupuesto', implode(' ', $data['deliverables']));
        $html = view('quotes.pdf', $data)->render();
        $this->assertStringContainsString('100% a la aprobación', $html);
        $this->assertStringNotContainsString('50% a la entrega', $html);
    }

    public function test_approving_an_ads_quote_charges_in_full_and_adds_no_video_quota(): void
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->adsPayload());
        $quote = Quote::forTenant($this->tenant->id)->sole();
        $quote->forceFill(['status' => 'sent', 'approval_token' => str_repeat('a', 48), 'approval_token_expires_at' => now()->addDays(5), 'valid_until' => now()->addDays(5)])->save();
        TenantSetting::set($this->tenant, 'bank_transfer_iban', 'ES9121000418450200051332');
        TenantSetting::set($this->tenant, 'bank_transfer_enabled', '1');

        $this->get(route('quotes.public.show', $quote->approval_token))
            ->assertOk()->assertSee('no está incluida en este importe')->assertDontSee('Sinal del 50% para inicio');

        $this->post(route('quotes.public.approve', $quote->approval_token), ['payment_method' => 'bank_transfer']);

        $purchase = PackPurchase::withoutGlobalScopes()->where('quote_id', $quote->id)->sole();
        $this->assertSame(0, (int) $purchase->video_quantity);
        $this->assertSame('full_upfront', $purchase->billing_mode);
        $this->assertEquals(847, $purchase->amount);
    }

    public function test_client_asking_for_ads_gets_a_separate_ads_request(): void
    {
        $this->cliente->forceFill(['anamnesis' => Anamnesis::normalize([
            'objective' => 'sell_more', 'main_offer' => 'Pão', 'ideal_customer' => 'Bairro', 'tone' => 'close',
            'priority_networks' => ['instagram', 'facebook', 'tiktok'], 'posting_frequency' => '1_week', 'wants_ads' => true,
        ]), 'anamnesis_completed_at' => now()])->save();
        MarketingPlan::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'month_year' => '2026-11'])->approveBy($this->cliente);

        $this->actingAs($this->cliente, 'cliente')->post(route('portal.quotes.request.store'), [
            'videos' => 4, 'social_networks' => 2, 'wants_ads' => '1', 'ads_budget' => '1.500 €',
        ])->assertRedirect();

        $ads = Quote::forTenant($this->tenant->id)->where('kind', Quote::KIND_ADS)->sole();
        $this->assertSame(Quote::STATUS_REQUESTED, $ads->status);
        $this->assertEquals(1500, $ads->inputs['verba_mensal']);
        $this->assertSame(['meta', 'tiktok'], $ads->inputs['plataformas']);
        $this->assertSame(1, Quote::forTenant($this->tenant->id)->where('kind', Quote::KIND_PRODUCTION)->count());
    }

    public function test_unknown_ad_platform_is_refused_by_the_team_form(): void
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->adsPayload(['plataformas' => ['orkut']]))
            ->assertSessionHasErrors('inputs.plataformas.0');
    }

    public function test_view_has_no_inline_css(): void
    {
        $this->assertStringNotContainsString('style="', file_get_contents(resource_path('views/quotes/ads_form.blade.php')));
    }
}
