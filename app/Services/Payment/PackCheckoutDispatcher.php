<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\Money;
use App\Support\StripeCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Stripe\StripeClient;

class PackCheckoutDispatcher
{
    /**
     * Despacha uma compra já criada para o TPV Redsys (Bizum ou Cartão).
     */
    public function dispatchRedsys(
        PackPurchase $purchase,
        string $paymentMethod,
        ?string $urlOk = null,
        ?string $urlKo = null,
        ?string $notificationUrl = null
    ): View {
        $tenant = $purchase->tenant;
        $redsys = RedsysService::forTenant($tenant);
        $orderNumber = $redsys->generateOrderNumber();
        $purchase->update(['gateway_order_reference' => $orderNumber]);

        $payMethodCode = ($paymentMethod === 'bizum' || $paymentMethod === 'redsys_bizum') ? 'z' : 'T';

        $productDesc = $purchase->pack
            ? "{$purchase->pack->name} - {$tenant->name}"
            : "Orçamento #{$purchase->quote?->number} - {$tenant->name}";

        $urlOk = $urlOk ?: ($purchase->quote
            ? route('quotes.public.show', ['token' => $purchase->quote->approval_token, 'success' => 1])
            : route('storefront.success', $tenant->slug));

        $urlKo = $urlKo ?: ($purchase->quote
            ? route('quotes.public.show', ['token' => $purchase->quote->approval_token, 'canceled' => 1])
            : route('storefront.show', $tenant->slug));

        $notificationUrl = $notificationUrl ?: (route('redsys.webhook', [], false) ? url('/webhooks/redsys') : url('/webhooks/redsys'));

        $payload = $redsys->createPaymentPayload(
            amountInEuros: (float) $purchase->amount,
            orderNumber: $orderNumber,
            urlOk: $urlOk,
            urlKo: $urlKo,
            notificationUrl: $notificationUrl,
            payMethod: $payMethodCode,
            productDescription: $productDesc,
            titular: $purchase->billing_legal_name ?: ($purchase->cliente?->name ?? 'Cliente')
        );

        return view('storefront.redsys_redirect', [
            'payload' => $payload,
            'purchase' => $purchase,
            'pack' => $purchase->pack,
            'tenant' => $tenant,
        ]);
    }

    /**
     * Despacha uma compra para o fluxo de Transferência Bancária Manual.
     */
    public function dispatchBankTransfer(PackPurchase $purchase, ?string $redirectUrl = null): RedirectResponse
    {
        $tenant = $purchase->tenant;

        do {
            $ref = 'BT-'.strtoupper(Str::random(8));
        } while (PackPurchase::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('bank_transfer_reference', $ref)
            ->exists());

        $days = (int) (TenantSetting::get($tenant, 'bank_transfer_expiration_days')
            ?: TenantSetting::get($tenant, 'bank_transfer_expires_days', 3));

        $purchase->update([
            'bank_transfer_reference' => $ref,
            'gateway_order_reference' => $ref,
            'expires_at' => now()->addDays($days),
        ]);

        $redirectUrl = $redirectUrl ?: route('storefront.bank_transfer', [
            'tenant' => $tenant->slug,
            'purchase' => $purchase->id,
        ]);

        return redirect()->to($redirectUrl);
    }

    /**
     * Despacha uma compra para o Stripe Checkout Session.
     */
    public function dispatchStripe(
        PackPurchase $purchase,
        string $successUrl,
        string $cancelUrl
    ): RedirectResponse {
        $tenant = $purchase->tenant;
        $stripe = new StripeClient(StripeCredentials::secret());
        $currency = strtolower($purchase->pack?->currency ?: 'eur');

        if ($purchase->pack) {
            $pack = $purchase->pack;
            if ($purchase->isSplit5050()) {
                $depPct = $pack->getEffectiveDepositPercentage();
                $lineItems = [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'product_data' => [
                                'name' => "{$pack->name} — Sinal ({$depPct}%)",
                                'description' => "Entrada de {$depPct}%. Saldo restante a pagar na aprovação do corte final.",
                            ],
                            'unit_amount' => Money::cents($purchase->amount),
                        ],
                        'quantity' => 1,
                    ],
                ];
            } else {
                $lineItems = [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'product_data' => [
                                'name' => "{$pack->name}",
                                'description' => "Pacote de produção audiovisual — {$tenant->name}",
                            ],
                            'unit_amount' => Money::cents($purchase->amount),
                        ],
                        'quantity' => 1,
                    ],
                ];
            }
        } else {
            // Compra oriunda de Orçamento (Quote)
            $quote = $purchase->quote;
            $title = $purchase->isSplit5050()
                ? 'Sinal de Entrada: '.($quote?->project_title ?? 'Proposta Audiovisual')
                : 'Pagamento Integral: '.($quote?->project_title ?? 'Proposta Audiovisual');

            $lineItems = [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => $title,
                            'description' => "Orçamento #{$quote?->number} — {$tenant->name}",
                        ],
                        'unit_amount' => Money::cents($purchase->amount),
                    ],
                    'quantity' => 1,
                ],
            ];
        }

        $sessionParams = [
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $purchase->cliente?->email,
            'metadata' => [
                'pack_purchase_id' => (string) $purchase->id,
                'tenant_id' => (string) $tenant->id,
            ],
        ];

        // Se o tenant estiver conectado via Stripe Connect, aplica repasse/taxa
        if ($tenant->isStripeConnectReady() && $tenant->slug !== 'nume') {
            $feeAmount = (float) $purchase->application_fee_amount;
            $sessionParams['payment_intent_data'] = [
                'application_fee_amount' => Money::cents($feeAmount),
                'transfer_data' => [
                    'destination' => $tenant->stripe_connect_account_id,
                ],
            ];
        }

        $session = $stripe->checkout->sessions->create($sessionParams);

        $purchase->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return redirect()->away($session->url);
    }
}
