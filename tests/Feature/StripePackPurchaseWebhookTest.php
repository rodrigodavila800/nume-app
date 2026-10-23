<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripePackPurchaseWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_webhook_successfully_processes_pack_purchase_with_addons(): void
    {
        $secret = 'whsec_test_secret_12345';
        config(['services.stripe.webhook_secret' => $secret]);

        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume-zaragoza',
            'status' => 'active',
        ]);

        $cliente = Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Carlos Zaragoza',
            'email' => 'carlos@empresa.es',
            'password' => bcrypt('secret123'),
            'phone' => '+34 600 000 000',
            'status' => 'active',
        ]);

        $category = Category::create([
            'tenant_id' => $tenant->id,
            'name' => 'Posicionamiento de Marca',
            'slug' => 'posicionamiento-de-marca',
            'type' => 'pack',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $pack = Pack::create([
            'tenant_id' => $tenant->id,
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

        $purchase = PackPurchase::create([
            'tenant_id' => $tenant->id,
            'pack_id' => $pack->id,
            'cliente_id' => $cliente->id,
            'status' => 'pending',
            'amount' => 1150.00,
            'video_quantity' => 8,
            'videos_used' => 0,
            'addons_purchased' => ['Composición con IA', 'Tráfico pagado'],
        ]);

        $eventId = 'evt_test_' . uniqid();
        $payload = json_encode([
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_session_123',
                    'payment_intent' => 'pi_test_payment_intent_999',
                    'metadata' => [
                        'type' => 'pack_purchase',
                        'pack_purchase_id' => $purchase->id,
                        'tenant_id' => $tenant->id,
                    ],
                ],
            ],
        ]);

        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$timestamp},v1={$signature}";

        $response = $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $header,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);

        $purchase->refresh();
        $this->assertEquals('paid', $purchase->status);
        $this->assertEquals('pi_test_payment_intent_999', $purchase->stripe_payment_intent_id);
        $this->assertNotNull($purchase->paid_at);
        $this->assertEquals(['Composición con IA', 'Tráfico pagado'], $purchase->addons_purchased);

        // Idempotency check: sending same event again should be processed without errors
        $retryResponse = $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $header,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $retryResponse->assertStatus(200);
    }
}
