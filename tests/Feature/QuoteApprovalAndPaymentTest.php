<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Payment\PackCheckoutDispatcher;
use App\Services\Payment\PackPaymentConfirmer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteApprovalAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Aurora Film',
            'slug' => 'aurora-film',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        // Habilita métodos no tenant
        TenantSetting::set($this->tenant, 'bank_transfer_iban', 'ES9121000418450200051332');
        TenantSetting::set($this->tenant, 'bank_transfer_enabled', '1');
        TenantSetting::set($this->tenant, 'bank_transfer_instructions', 'Transferir e indicar a referência');

        TenantSetting::set($this->tenant, 'redsys_merchant_code', '999008881');
        TenantSetting::set($this->tenant, 'redsys_terminal', '1');
        TenantSetting::set($this->tenant, 'redsys_secret_key', 'sq7HjrUqKpRwXjz57tEsYXppmEAUqqEy');
        TenantSetting::set($this->tenant, 'redsys_enabled', '1');
        TenantSetting::set($this->tenant, 'redsys_card_enabled', '1');
        TenantSetting::set($this->tenant, 'redsys_bizum_enabled', '1');

        $this->quote = Quote::create([
            'tenant_id' => $this->tenant->id,
            'number' => 'ORC-2026-0001',
            'project_title' => 'Campanha Publicitária de Lançamento',
            'client_name' => 'TechCorp S.L.',
            'client_email' => 'financeiro@techcorp.es',
            'status' => 'sent',
            'valid_until' => now()->addDays(10),
            'approval_token' => Str::random(48),
            'approval_token_expires_at' => now()->addDays(10)->endOfDay(),
            'subtotal' => 2000.00,
            'tax_rate' => 21.00,
            'tax_amount' => 420.00,
            'total_amount' => 2420.00,
            'deposit_amount' => 1210.00,
            'balance_amount' => 1210.00,
            'inputs' => ['videos' => 2, 'prazo' => 15],
            'calculation' => ['total' => 2420.00],
        ]);
    }

    public function test_public_quote_requires_valid_token_or_returns_404(): void
    {
        $response = $this->get('/orcamento/token-completamente-invalido');
        $response->assertNotFound();
    }

    public function test_valid_quote_displays_show_view_with_tenant_payment_methods(): void
    {
        $response = $this->get("/orcamento/{$this->quote->approval_token}");

        $response->assertOk();
        $response->assertViewIs('quotes.public.show');
        $response->assertSee('Campanha Publicitária de Lançamento');
        $response->assertSee('TechCorp S.L.');
        $response->assertSee('€ 2.420,00');
        $response->assertSee('€ 1.210,00');
        $response->assertSee('Transferencia Bancaria');
    }

    public function test_expired_quote_displays_expired_view(): void
    {
        $this->quote->update([
            'approval_token_expires_at' => now()->subDay(),
            'valid_until' => now()->subDay(),
        ]);

        $response = $this->get("/orcamento/{$this->quote->approval_token}");

        $response->assertOk();
        $response->assertViewIs('quotes.public.expired');
        $response->assertSee('Presupuesto Expirado');
    }

    public function test_rejected_quote_displays_rejected_view(): void
    {
        $this->quote->update(['status' => 'rejected']);

        $response = $this->get("/orcamento/{$this->quote->approval_token}");

        $response->assertOk();
        $response->assertViewIs('quotes.public.rejected');
        $response->assertSee('Presupuesto Declinado');
    }

    public function test_accepted_quote_displays_accepted_view(): void
    {
        $this->quote->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $response = $this->get("/orcamento/{$this->quote->approval_token}");

        $response->assertOk();
        $response->assertViewIs('quotes.public.accepted');
        $response->assertSee('¡Presupuesto Aprobado con Éxito!');
    }

    public function test_client_can_reject_quote(): void
    {
        $response = $this->post("/orcamento/{$this->quote->approval_token}/recusar");

        $response->assertRedirect("/orcamento/{$this->quote->approval_token}");
        $this->quote->refresh();
        $this->assertEquals('rejected', $this->quote->status);
    }

    public function test_client_cannot_reject_already_accepted_quote(): void
    {
        $this->quote->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $response = $this->post("/orcamento/{$this->quote->approval_token}/recusar");

        $response->assertRedirect("/orcamento/{$this->quote->approval_token}");
        $this->quote->refresh();
        $this->assertEquals('accepted', $this->quote->status);
    }

    public function test_approval_rejects_unsupported_or_inactive_payment_method(): void
    {
        // Desativa Redsys Card
        TenantSetting::set($this->tenant, 'redsys_card_enabled', '0');

        $response = $this->post("/orcamento/{$this->quote->approval_token}/aprovar", [
            'payment_method' => 'redsys_card',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('pack_purchases', 0);
    }

    public function test_approval_via_bank_transfer_creates_purchase_and_renders_instructions(): void
    {
        $response = $this->post("/orcamento/{$this->quote->approval_token}/aprovar", [
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertRedirect();
        $this->followRedirects($response)
            ->assertOk()
            ->assertViewIs('storefront.bank_transfer');

        $purchase = PackPurchase::where('quote_id', $this->quote->id)->first();
        $this->assertNotNull($purchase);
        $this->assertNull($purchase->pack_id);
        $this->assertEquals($this->tenant->id, $purchase->tenant_id);
        $this->assertEquals(1210.00, (float) $purchase->amount);
        $this->assertEquals('pending', $purchase->status);
        $this->assertEquals('bank_transfer', $purchase->payment_gateway);
        $this->assertEquals('pending_billing', $purchase->platform_fee_status);
        $this->assertEquals(200.00, (float) $purchase->application_fee_amount); // 10% do subtotal 2000
        $this->assertNotNull($purchase->bank_transfer_reference);

        // Cliente foi automaticamente provisionado para o tenant
        $cliente = Cliente::where('tenant_id', $this->tenant->id)->where('email', 'financeiro@techcorp.es')->first();
        $this->assertNotNull($cliente);
        $this->assertEquals($cliente->id, $purchase->cliente_id);
    }

    public function test_approval_via_redsys_card_creates_purchase_and_renders_signed_form(): void
    {
        $response = $this->post("/orcamento/{$this->quote->approval_token}/aprovar", [
            'payment_method' => 'redsys_card',
        ]);

        $response->assertOk();
        $response->assertViewIs('storefront.redsys_redirect');
        $response->assertViewHas('payload');

        $purchase = PackPurchase::where('quote_id', $this->quote->id)->first();
        $this->assertNotNull($purchase);
        $this->assertEquals('redsys_card', $purchase->payment_gateway);
        $this->assertEquals('pending_billing', $purchase->platform_fee_status);
    }

    public function test_approval_via_stripe_calls_dispatcher_and_creates_purchase(): void
    {
        $mockDispatcher = $this->mock(PackCheckoutDispatcher::class);
        $mockDispatcher->shouldReceive('dispatchStripe')
            ->once()
            ->andReturn(redirect('https://checkout.stripe.com/pay/cs_test_mock'));

        $this->app->instance(PackCheckoutDispatcher::class, $mockDispatcher);

        // Habilita Stripe e torna tenant apto a receber pagamentos Stripe
        Setting::set('stripe_enabled', '1');
        Setting::set('stripe_secret_key', 'sk_test_123');
        config(['services.stripe.secret' => 'sk_test_123']);
        $this->tenant->update([
            'stripe_account_id' => 'acct_test_aurora',
            'charges_enabled' => true,
        ]);

        $response = $this->post("/orcamento/{$this->quote->approval_token}/aprovar", [
            'payment_method' => 'stripe',
        ]);

        $response->assertRedirect('https://checkout.stripe.com/pay/cs_test_mock');

        $purchase = PackPurchase::where('quote_id', $this->quote->id)->first();
        $this->assertNotNull($purchase);
        $this->assertEquals('stripe', $purchase->payment_gateway);
    }

    public function test_confirming_payment_via_gateway_confirms_quote_and_creates_production(): void
    {
        $cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'TechCorp S.L.',
            'email' => 'financeiro@techcorp.es',
            'password' => bcrypt('password123'),
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'pack_id' => null,
            'quote_id' => $this->quote->id,
            'amount' => 1210.00,
            'subtotal' => 2000.00,
            'tax_rate' => 21.00,
            'tax_amount' => 420.00,
            'total_amount' => 2420.00,
            'deposit_amount' => 1210.00,
            'balance_amount' => 1210.00,
            'billing_mode' => 'split_50_50',
            'status' => 'pending',
            'payment_gateway' => 'stripe',
            'application_fee_amount' => 200.00,
            'platform_fee_status' => 'collected_at_gateway',
            'video_quantity' => 1,
            'stripe_checkout_session_id' => 'cs_test_quote_123',
        ]);

        $fakeSession = (object) [
            'id' => 'cs_test_quote_123',
            'payment_status' => 'paid',
            'amount_total' => 121000,
            'currency' => 'eur',
            'metadata' => (object) [
                'pack_purchase_id' => (string) $purchase->id,
                'tenant_id' => (string) $this->tenant->id,
            ],
        ];

        $confirmer = app(PackPaymentConfirmer::class);
        $result = $confirmer->confirmStripeSession($purchase, $fakeSession);

        $this->assertEquals(PackPaymentConfirmer::PAID, $result);
        $purchase->refresh();
        $this->quote->refresh();

        $this->assertEquals('paid', $purchase->status);
        $this->assertEquals('accepted', $this->quote->status);
        $this->assertNotNull($this->quote->accepted_at);
        $this->assertNotNull($this->quote->production_id);

        $production = Production::find($this->quote->production_id);
        $this->assertNotNull($production);
        $this->assertEquals('Produção #1 - Campanha Publicitária de Lançamento', $production->title);
    }

    public function test_confirming_payment_updates_quote_provisions_production_and_links_production_id(): void
    {
        // Cria a compra de sinal vinculada à quote
        $cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'TechCorp S.L.',
            'email' => 'financeiro@techcorp.es',
            'password' => bcrypt('password123'),
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'pack_id' => null,
            'quote_id' => $this->quote->id,
            'amount' => 1210.00,
            'subtotal' => 2000.00,
            'tax_rate' => 21.00,
            'tax_amount' => 420.00,
            'total_amount' => 2420.00,
            'deposit_amount' => 1210.00,
            'balance_amount' => 1210.00,
            'billing_mode' => 'split_50_50',
            'status' => 'pending',
            'payment_gateway' => 'bank_transfer',
            'application_fee_amount' => 200.00,
            'platform_fee_status' => 'pending_billing',
            'video_quantity' => 1,
            'bank_transfer_reference' => 'BT-TESTQUOTE1',
            'bank_transfer_expires_at' => now()->addDays(5),
        ]);

        $confirmer = app(PackPaymentConfirmer::class);
        $confirmer->confirmBankTransfer($purchase, $this->admin);

        $purchase->refresh();
        $this->quote->refresh();

        $this->assertEquals('paid', $purchase->status);
        $this->assertEquals('accepted', $this->quote->status);
        $this->assertNotNull($this->quote->accepted_at);
        $this->assertNotNull($this->quote->production_id);

        $production = Production::find($this->quote->production_id);
        $this->assertNotNull($production);
        $this->assertEquals($this->tenant->id, $production->tenant_id);
        $this->assertEquals($cliente->id, $production->cliente_id);
        $this->assertEquals($purchase->id, $production->pack_purchase_id);
        $this->assertEquals('Produção #1 - Campanha Publicitária de Lançamento', $production->title);
    }

    public function test_confirming_payment_is_idempotent_and_reuses_existing_production(): void
    {
        $cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'TechCorp S.L.',
            'email' => 'financeiro@techcorp.es',
            'password' => bcrypt('password123'),
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'pack_id' => null,
            'quote_id' => $this->quote->id,
            'amount' => 1210.00,
            'subtotal' => 2000.00,
            'tax_rate' => 21.00,
            'tax_amount' => 420.00,
            'total_amount' => 2420.00,
            'deposit_amount' => 1210.00,
            'balance_amount' => 1210.00,
            'billing_mode' => 'split_50_50',
            'status' => 'pending',
            'payment_gateway' => 'bank_transfer',
            'application_fee_amount' => 200.00,
            'platform_fee_status' => 'pending_billing',
            'video_quantity' => 1,
            'bank_transfer_reference' => 'BT-TESTQUOTE2',
            'bank_transfer_expires_at' => now()->addDays(5),
        ]);

        // Simula que uma produção já havia sido criada previamente para esta compra
        $existingProduction = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'created_by' => $this->admin->id,
            'pack_purchase_id' => $purchase->id,
            'title' => 'Produção Prévia Existente',
            'status' => 'in_production',
        ]);

        $confirmer = app(PackPaymentConfirmer::class);
        $confirmer->confirmBankTransfer($purchase, $this->admin);

        $this->quote->refresh();
        $this->assertEquals('accepted', $this->quote->status);
        $this->assertEquals($existingProduction->id, $this->quote->production_id);
        $this->assertEquals(1, Production::where('pack_purchase_id', $purchase->id)->count());
    }

    public function test_quote_controller_send_generates_approval_token_and_expiration(): void
    {
        Mail::fake();

        $quote = Quote::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'number' => 'ORC-2026-0002',
            'project_title' => 'Vídeo Institucional 4K',
            'client_name' => 'Empresa Teste',
            'client_email' => 'contato@empresa.es',
            'status' => 'draft',
            'valid_until' => now()->addDays(20)->toDateString(),
            'subtotal' => 1500.00,
            'tax_rate' => 21.00,
            'tax_amount' => 315.00,
            'total_amount' => 1815.00,
            'deposit_amount' => 907.50,
            'balance_amount' => 907.50,
            'inputs' => ['videos' => 1],
            'calculation' => ['total' => 1815.00],
        ]);

        $this->assertNull($quote->approval_token);
        $this->assertNull($quote->approval_token_expires_at);

        $response = $this->actingAs($this->admin)->post(route('quotes.send', $quote), [
            'email' => 'contato@empresa.es',
            'subject' => 'Sua Proposta',
            'message' => 'Segue proposta para apreciação.',
        ]);

        $response->assertRedirect();
        $quote->refresh();

        $this->assertNotNull($quote->approval_token);
        $this->assertNotNull($quote->approval_token_expires_at);
        $this->assertEquals('sent', $quote->status);
    }
}
