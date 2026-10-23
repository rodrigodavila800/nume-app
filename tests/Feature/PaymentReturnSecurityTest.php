<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Services\Billing\InvoiceService;
use App\Services\Payment\RedsysService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

/**
 * As URLs de retorno (Stripe success_url e Redsys URL OK) são abertas pelo navegador
 * do visitante e podem ser forjadas. Só o webhook assinado, ou uma confirmação validada
 * na API do gateway, pode marcar a compra como paga.
 */
class PaymentReturnSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    /** @var array<string, array> Sessões que a "API da Stripe" falsa devolve, por id. */
    private array $stripeSessions = [];

    private int $invoices = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.stripe.secret' => 'sk_test_fake_para_testes']);

        // API da Stripe falsa: só responde GET de sessões registradas; qualquer outra chamada falha o teste.
        $sessions = &$this->stripeSessions;
        ApiRequestor::setHttpClient(new class($sessions) implements ClientInterface
        {
            public function __construct(private array &$sessions) {}

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                if ($method === 'get' && preg_match('#/v1/checkout/sessions/([^/?]+)#', $absUrl, $m) && isset($this->sessions[$m[1]])) {
                    return [json_encode($this->sessions[$m[1]]), 200, []];
                }

                throw new RuntimeException("Chamada inesperada à Stripe: {$method} {$absUrl}");
            }
        });

        $this->mock(InvoiceService::class, function ($mock) {
            $mock->shouldReceive('issueAndNotify')->andReturnUsing(function () {
                $this->invoices++;
            });
        });

        $this->tenantA = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->tenantB = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    public function test_stripe_success_url_without_payment_keeps_purchase_pending(): void
    {
        $purchase = $this->pendingPurchase($this->tenantA, 'stripe', ['stripe_checkout_session_id' => 'cs_test_nao_pago']);

        $this->get("/vitrine/produtora-a/compra-confirmada?purchase_id={$purchase->id}&session_id=cs_test_forjado")
            ->assertOk()
            ->assertSee('Estamos confirmando seu pagamento');

        $purchase->refresh();
        $this->assertSame('pending', $purchase->status, 'A success_url marcou a compra como paga sem pagamento confirmado.');
        $this->assertNull($purchase->paid_at);
        $this->assertSame(0, $this->videosReleasedFor($purchase));
    }

    public function test_purchase_of_another_tenant_is_neither_shown_nor_changed(): void
    {
        $purchaseB = $this->pendingPurchase($this->tenantB, 'stripe', ['stripe_checkout_session_id' => 'cs_test_sessao_do_b'], 'Pacote Secreto B');
        $this->stripeSessions['cs_test_sessao_do_b'] = $this->paidSession($purchaseB, 'cs_test_sessao_do_b');

        // Nem pelo purchase_id, nem pela sessão (real) do tenant B aberta na vitrine do A.
        $this->get("/vitrine/produtora-a/compra-confirmada?purchase_id={$purchaseB->id}")->assertDontSee('Pacote Secreto B');
        $this->get('/vitrine/produtora-a/compra-confirmada?session_id=cs_test_sessao_do_b')->assertDontSee('Pacote Secreto B');

        $purchaseB->refresh();
        $this->assertSame('pending', $purchaseB->status, 'A vitrine A alterou uma compra do tenant B.');
    }

    public function test_paid_session_confirmed_on_the_api_marks_paid_once(): void
    {
        $purchase = $this->pendingPurchase($this->tenantA, 'stripe', ['stripe_checkout_session_id' => 'cs_test_pago_ok']);
        $this->stripeSessions['cs_test_pago_ok'] = $this->paidSession($purchase, 'cs_test_pago_ok');

        $this->get('/vitrine/produtora-a/compra-confirmada?session_id=cs_test_pago_ok')
            ->assertOk()
            ->assertSee('Seu pacote foi ativado!')
            ->assertSee('Reels Starter');
        $this->get('/vitrine/produtora-a/compra-confirmada?session_id=cs_test_pago_ok')->assertOk();

        $this->assertSame('paid', $purchase->fresh()->status);
        $this->assertSame(1, $this->invoices);
    }

    public function test_api_session_with_divergent_amount_or_unpaid_keeps_pending(): void
    {
        $purchase = $this->pendingPurchase($this->tenantA, 'stripe', ['stripe_checkout_session_id' => 'cs_test_divergente']);

        $this->stripeSessions['cs_test_divergente'] = ['amount_total' => 100] + $this->paidSession($purchase, 'cs_test_divergente');
        $this->get('/vitrine/produtora-a/compra-confirmada?session_id=cs_test_divergente')->assertSee('Estamos confirmando seu pagamento');
        $this->assertSame('pending', $purchase->fresh()->status);

        $this->stripeSessions['cs_test_divergente'] = ['payment_status' => 'unpaid'] + $this->paidSession($purchase, 'cs_test_divergente');
        $this->get('/vitrine/produtora-a/compra-confirmada?session_id=cs_test_divergente');
        $this->assertSame('pending', $purchase->fresh()->status);
        $this->assertSame(0, $this->invoices);
    }

    public function test_redsys_return_url_without_valid_signature_keeps_purchase_pending(): void
    {
        $purchase = $this->pendingPurchase($this->tenantA, 'bizum', ['gateway_order_reference' => '260000001234']);

        // Retorno "OK" sem Ds_Signature, e outro com parâmetros forjados e assinatura inválida.
        $this->get("/pagamento/redsys/sucesso?order=260000001234&purchase_id={$purchase->id}")->assertOk();

        $forged = base64_encode(json_encode(['Ds_Order' => '260000001234', 'Ds_Response' => '0000', 'Ds_Amount' => '59290']));
        $this->get("/pagamento/redsys/sucesso?order=260000001234&purchase_id={$purchase->id}&Ds_SignatureVersion=HMAC_SHA256_V1&Ds_MerchantParameters={$forged}&Ds_Signature=assinatura-invalida")
            ->assertOk();

        $purchase->refresh();
        $this->assertSame('pending', $purchase->status, 'A URL de retorno do Redsys marcou a compra como paga sem assinatura válida.');
        $this->assertNull($purchase->paid_at);
    }

    public function test_redsys_return_with_valid_signature_confirms_once_together_with_the_webhook(): void
    {
        $purchase = $this->pendingPurchase($this->tenantA, 'bizum', ['gateway_order_reference' => '260000005678']);
        [$parameters, $signature] = $this->signedRedsys('260000005678', '59290');

        $this->get('/pagamento/redsys/sucesso?'.http_build_query(['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $parameters, 'Ds_Signature' => $signature]))
            ->assertOk()
            ->assertDontSee('Reels Starter'); // visitante não logado: sem detalhes da compra

        $this->post('/webhooks/redsys', ['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $parameters, 'Ds_Signature' => $signature])->assertOk();

        $purchase->refresh();
        $this->assertSame('paid', $purchase->status);
        $this->assertSame(1, $this->invoices);

        // O cliente dono da compra vê os detalhes.
        $this->actingAs($purchase->cliente, 'cliente')
            ->get('/pagamento/redsys/sucesso?order=260000005678')
            ->assertSee('Reels Starter');
    }

    public function test_subscription_is_only_active_after_confirmed_payment(): void
    {
        Pack::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Plano Mensal', 'slug' => 'plano-mensal', 'price' => 490, 'currency' => 'eur',
            'video_quantity' => 4, 'status' => 'active', 'billing_type' => 'subscription', 'billing_interval' => 'month', 'commitment_months' => 3,
        ]);

        // Checkout pela vitrine com Bizum (Redsys): nenhuma chamada à Stripe.
        $this->post('/vitrine/produtora-a/pacotes/plano-mensal/checkout', [
            'payment_method' => 'bizum',
            'email' => 'novo-cliente@empresa.com',
            'password' => 'secret123',
            'name' => 'Novo Cliente',
        ])->assertOk();

        $purchase = PackPurchase::withoutGlobalScope('tenant')->latest('id')->firstOrFail();
        $this->assertSame('pending', $purchase->status);
        $this->assertNull($purchase->subscription_status, 'Assinatura nasceu ativa antes do pagamento.');
        $this->assertSame(0, $this->videosReleasedFor($purchase), 'Assinatura ainda não paga libera vídeos.');

        // Webhook assinado do Redsys com o valor da compra: só então a assinatura fica ativa.
        [$parameters, $signature] = $this->signedRedsys($purchase->gateway_order_reference, (string) Money::cents($purchase->amount));
        $this->post('/webhooks/redsys', ['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $parameters, 'Ds_Signature' => $signature])->assertOk();

        $purchase->refresh();
        $this->assertSame('paid', $purchase->status);
        $this->assertSame('active', $purchase->subscription_status);
        $this->assertSame(4, $this->videosReleasedFor($purchase));
    }

    /**
     * Mesmo critério do portal (DashboardController): compras pagas ou assinaturas ativas.
     */
    private function videosReleasedFor(PackPurchase $purchase): int
    {
        return (int) PackPurchase::withoutGlobalScope('tenant')
            ->whereKey($purchase->id)
            ->where(fn ($q) => $q->where('status', 'paid')->orWhere('subscription_status', 'active'))
            ->sum('video_quantity');
    }

    private function paidSession(PackPurchase $purchase, string $sessionId): array
    {
        return [
            'id' => $sessionId,
            'object' => 'checkout.session',
            'mode' => 'payment',
            'payment_status' => 'paid',
            'amount_total' => 59290,
            'currency' => 'eur',
            'payment_intent' => 'pi_test_123',
            'metadata' => ['type' => 'pack_purchase', 'pack_purchase_id' => (string) $purchase->id, 'tenant_id' => (string) $purchase->tenant_id],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function signedRedsys(string $order, string $amount): array
    {
        $redsys = app(RedsysService::class);
        $parameters = base64_encode(json_encode([
            'Ds_Amount' => $amount,
            'Ds_Currency' => '978',
            'Ds_Order' => $order,
            'Ds_MerchantCode' => $redsys->merchantCode(),
            'Ds_Response' => '0000',
            'Ds_PayMethod' => 'z',
        ]));

        return [$parameters, $redsys->calculateSignature($parameters, $order)];
    }

    private function pendingPurchase(Tenant $tenant, string $gateway, array $extra = [], string $packName = 'Reels Starter'): PackPurchase
    {
        $pack = Pack::create(['tenant_id' => $tenant->id, 'name' => $packName, 'slug' => str($packName)->slug(), 'price' => 490, 'currency' => 'eur', 'video_quantity' => 4, 'status' => 'active']);
        $cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste', 'email' => "cliente-{$tenant->slug}-".uniqid().'@empresa.com', 'password' => bcrypt('secret123')]);

        return PackPurchase::create([
            'tenant_id' => $tenant->id,
            'pack_id' => $pack->id,
            'cliente_id' => $cliente->id,
            'payment_gateway' => $gateway,
            'status' => 'pending',
            'amount' => 592.90,
            'total_amount' => 592.90,
            'video_quantity' => 4,
            'videos_used' => 0,
            ...$extra,
        ]);
    }
}
