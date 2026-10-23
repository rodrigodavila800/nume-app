<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use App\Services\Payment\RedsysService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Notificação do Redsys (/webhooks/redsys): assinatura, valor, moeda, comerciante e idempotência.
 */
class RedsysWebhookPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER = '260000004321';

    private PackPurchase $purchase;

    private int $invoices = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        User::create(['tenant_id' => $tenant->id, 'name' => 'Staff A', 'email' => 'staff-a@produtora.es', 'password' => 'secret12345', 'role' => 'admin']);
        $pack = Pack::create(['tenant_id' => $tenant->id, 'name' => 'Reels Starter', 'slug' => 'reels-starter', 'price' => 490, 'video_quantity' => 4, 'status' => 'active']);
        $cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'cliente-a@empresa.com', 'password' => bcrypt('secret123')]);

        $this->purchase = PackPurchase::create([
            'tenant_id' => $tenant->id,
            'pack_id' => $pack->id,
            'cliente_id' => $cliente->id,
            'payment_gateway' => 'bizum',
            'status' => 'pending',
            'amount' => 592.90,
            'total_amount' => 592.90,
            'video_quantity' => 4,
            'videos_used' => 0,
            'gateway_order_reference' => self::ORDER,
        ]);

        $this->mock(InvoiceService::class, function ($mock) {
            $mock->shouldReceive('issueAndNotify')->andReturnUsing(function () {
                $this->invoices++;
            });
        });
    }

    public function test_valid_notification_marks_paid_once_with_one_invoice_and_one_production(): void
    {
        $this->notify()->assertOk();
        $this->notify()->assertOk();

        $purchase = $this->purchase->fresh();
        $this->assertSame('paid', $purchase->status);
        $this->assertSame('redsys_bizum', $purchase->payment_gateway);
        $this->assertSame(1, $this->invoices);
        $this->assertSame(1, $purchase->productions()->count());
    }

    public function test_valid_signature_with_wrong_amount_currency_or_merchant_keeps_pending(): void
    {
        $this->notify(['Ds_Amount' => '100'])->assertOk();
        $this->notify(['Ds_Currency' => '840'])->assertOk();
        $this->notify(['Ds_MerchantCode' => '000000001'])->assertOk();

        $this->assertSame('pending', $this->purchase->fresh()->status);
        $this->assertSame(0, $this->invoices);
    }

    public function test_declined_or_malformed_response_code_never_marks_paid(): void
    {
        $this->notify(['Ds_Response' => ''])->assertOk();
        $this->assertSame('failed', $this->purchase->fresh()->status);

        $this->purchase->update(['status' => 'pending']);
        $this->notify(['Ds_Response' => '0190'])->assertOk();
        $this->assertSame('failed', $this->purchase->fresh()->status);
        $this->assertSame(0, $this->invoices);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $parameters = base64_encode(json_encode($this->params()));

        $this->post('/webhooks/redsys', ['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $parameters, 'Ds_Signature' => 'invalida'])
            ->assertStatus(400);

        $this->assertSame('pending', $this->purchase->fresh()->status);
    }

    private function params(array $overrides = []): array
    {
        return array_merge([
            'Ds_Amount' => '59290',
            'Ds_Currency' => '978',
            'Ds_Order' => self::ORDER,
            'Ds_MerchantCode' => app(RedsysService::class)->merchantCode(),
            'Ds_Response' => '0000',
            'Ds_PayMethod' => 'z',
        ], $overrides);
    }

    private function notify(array $overrides = [])
    {
        $parameters = base64_encode(json_encode($this->params($overrides)));

        return $this->post('/webhooks/redsys', [
            'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
            'Ds_MerchantParameters' => $parameters,
            'Ds_Signature' => app(RedsysService::class)->calculateSignature($parameters, self::ORDER),
        ]);
    }
}
