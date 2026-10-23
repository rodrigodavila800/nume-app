<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Services\Billing\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StripePackPurchaseWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret_12345';

    private Tenant $tenant;

    private PackPurchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.stripe.webhook_secret' => self::SECRET, 'services.stripe.secret' => null]);

        $this->tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume-zaragoza',
            'status' => 'active',
        ]);

        $cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Carlos Zaragoza',
            'email' => 'carlos@empresa.es',
            'password' => bcrypt('secret123'),
            'phone' => '+34 600 000 000',
            'status' => 'active',
        ]);

        $category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Posicionamiento de Marca',
            'slug' => 'posicionamiento-de-marca',
            'type' => 'pack',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'name' => 'Pack Pro',
            'slug' => 'pack-pro',
            'description' => 'Pack Pro Audiovisual',
            'price' => 850.00,
            'currency' => 'eur',
            'video_quantity' => 8,
            'addons' => [
                ['name' => 'Composición con IA', 'price' => 100],
                ['name' => 'Tráfico pagado', 'price' => 200],
            ],
            'vat_rate' => 21.00,
            'is_active' => true,
        ]);

        $this->purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $pack->id,
            'cliente_id' => $cliente->id,
            'status' => 'pending',
            'amount' => 1150.00,
            'video_quantity' => 8,
            'videos_used' => 0,
            'addons_purchased' => ['Composición con IA', 'Tráfico pagado'],
            'stripe_checkout_session_id' => 'cs_test_session_123',
        ]);
    }

    public function test_stripe_webhook_successfully_processes_pack_purchase_with_addons(): void
    {
        $payload = $this->event('checkout.session.completed');

        $this->sendWebhook($payload)->assertStatus(200);

        $purchase = $this->purchase->fresh();
        $this->assertEquals('paid', $purchase->status);
        $this->assertEquals('pi_test_payment_intent_999', $purchase->stripe_payment_intent_id);
        $this->assertNotNull($purchase->paid_at);
        $this->assertEquals(['Composición con IA', 'Tráfico pagado'], $purchase->addons_purchased);

        // Idempotency check: sending same event again should be processed without errors
        $this->sendWebhook($payload)->assertStatus(200);
    }

    public function test_unpaid_session_keeps_purchase_pending(): void
    {
        $this->sendWebhook($this->event('checkout.session.completed', ['payment_status' => 'unpaid']))->assertOk();

        $this->assertSame('pending', $this->purchase->fresh()->status);
    }

    public function test_amount_or_currency_mismatch_keeps_purchase_pending(): void
    {
        $this->sendWebhook($this->event('checkout.session.completed', ['amount_total' => 100]))->assertOk();
        $this->assertSame('pending', $this->purchase->fresh()->status);

        $this->sendWebhook($this->event('checkout.session.completed', ['currency' => 'usd']))->assertOk();
        $this->assertSame('pending', $this->purchase->fresh()->status);
    }

    public function test_session_or_tenant_of_another_purchase_keeps_purchase_pending(): void
    {
        $this->sendWebhook($this->event('checkout.session.completed', ['id' => 'cs_test_outra_sessao']))->assertOk();
        $this->assertSame('pending', $this->purchase->fresh()->status);

        $this->sendWebhook($this->event('checkout.session.completed', ['metadata' => [
            'type' => 'pack_purchase',
            'pack_purchase_id' => $this->purchase->id,
            'tenant_id' => $this->tenant->id + 99,
        ]]))->assertOk();
        $this->assertSame('pending', $this->purchase->fresh()->status);
    }

    public function test_async_payment_succeeded_marks_paid_and_replays_issue_a_single_invoice(): void
    {
        $invoices = $this->spyInvoices();

        $this->sendWebhook($this->event('checkout.session.completed', ['payment_status' => 'unpaid']))->assertOk();
        $this->assertSame('pending', $this->purchase->fresh()->status);

        $succeeded = $this->event('checkout.session.async_payment_succeeded');
        $this->sendWebhook($succeeded)->assertOk();
        $this->sendWebhook($succeeded)->assertOk();
        $this->sendWebhook($this->event('checkout.session.async_payment_succeeded'))->assertOk();

        $this->assertSame('paid', $this->purchase->fresh()->status);
        $this->assertSame(1, $invoices->count);
    }

    public function test_async_payment_failed_marks_failed_without_releasing_anything(): void
    {
        $this->sendWebhook($this->event('checkout.session.async_payment_failed', ['payment_status' => 'unpaid']))->assertOk();

        $purchase = $this->purchase->fresh();
        $this->assertSame('failed', $purchase->status);
        $this->assertNull($purchase->paid_at);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->sendWebhook($this->event('checkout.session.completed'), 'whsec_outro_segredo')->assertStatus(400);

        $this->assertSame('pending', $this->purchase->fresh()->status);
    }

    private function event(string $type, array $session = []): string
    {
        return json_encode([
            'id' => 'evt_test_'.uniqid(),
            'type' => $type,
            'data' => [
                'object' => array_merge([
                    'id' => 'cs_test_session_123',
                    'mode' => 'payment',
                    'payment_status' => 'paid',
                    'amount_total' => 115000,
                    'currency' => 'eur',
                    'payment_intent' => 'pi_test_payment_intent_999',
                    'metadata' => [
                        'type' => 'pack_purchase',
                        'pack_purchase_id' => $this->purchase->id,
                        'tenant_id' => $this->tenant->id,
                    ],
                ], $session),
            ],
        ]);
    }

    private function sendWebhook(string $payload, string $secret = self::SECRET)
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    private function spyInvoices(): object
    {
        $spy = new class
        {
            public int $count = 0;
        };

        $this->mock(InvoiceService::class, function ($mock) use ($spy) {
            $mock->shouldReceive('issueAndNotify')->andReturnUsing(function () use ($spy) {
                $spy->count++;
            });
        });

        return $spy;
    }
}
