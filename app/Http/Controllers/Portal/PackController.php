<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Setting;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\TaxCalculationService;
use App\Services\Payment\PackCheckoutPricing;
use App\Services\Payment\RedsysService;
use App\Support\Money;
use App\Support\StripeCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class PackController extends Controller
{
    public function plans(Request $request): View
    {
        $cliente = $request->user('cliente');
        $tenantId = $cliente->tenant_id;

        $plans = Pack::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('billing_type', 'subscription')
            ->with(['category', 'addons', 'showcaseVideo'])
            ->orderBy('price')
            ->get();

        $fiscalRegime = Setting::get('fiscal_regime', 'autonomo');
        $fiscalIrpfRate = ($fiscalRegime === 'autonomo') ? (float) Setting::get('fiscal_irpf_rate', 15.00) : 0.00;
        $taxDefaultIvaRate = (float) Setting::get('tax_default_iva_rate', 21.00);

        return view('portal.plans.index', [
            'plans' => $plans,
            'tenant' => $cliente->tenant,
            'activePaymentMethods' => Setting::getActivePaymentMethods($cliente->tenant),
            'fiscalSettings' => [
                'regime' => $fiscalRegime,
                'irpf_rate' => $fiscalIrpfRate,
                'iva_rate' => $taxDefaultIvaRate,
            ],
        ]);
    }

    public function index(Request $request): View
    {
        $cliente = $request->user('cliente');
        $tenantId = $cliente->tenant_id;

        // Pacotes avulsos de escopo fechado
        $packs = Pack::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('billing_type', '!=', 'subscription')
                    ->orWhereNull('billing_type');
            })
            ->with(['category', 'addons', 'showcaseVideo'])
            ->orderBy('price')
            ->get();

        $fiscalRegime = Setting::get('fiscal_regime', 'autonomo');
        $fiscalIrpfRate = ($fiscalRegime === 'autonomo') ? (float) Setting::get('fiscal_irpf_rate', 15.00) : 0.00;
        $taxDefaultIvaRate = (float) Setting::get('tax_default_iva_rate', 21.00);

        return view('portal.packs.index', [
            'packs' => $packs,
            'tenant' => $cliente->tenant,
            'activePaymentMethods' => Setting::getActivePaymentMethods($cliente->tenant),
            'fiscalSettings' => [
                'regime' => $fiscalRegime,
                'irpf_rate' => $fiscalIrpfRate,
                'iva_rate' => $taxDefaultIvaRate,
            ],
        ]);
    }

    public function checkout(
        Request $request,
        Pack $pack,
        TaxCalculationService $taxService,
        RedsysService $redsysService
    ): Response|RedirectResponse|View|JsonResponse {
        $cliente = $request->user('cliente');
        $tenant = $cliente->tenant;

        $isEmbedded = $request->expectsJson() || $request->ajax() || $request->boolean('embedded');

        if (! $pack->isActive()) {
            if ($isEmbedded) {
                return response()->json(['status' => 'error', 'message' => 'Este pacote não está mais disponível.'], 422);
            }

            return back()->with('error', 'Este pacote não está mais disponível.');
        }

        $paymentMethod = $request->input('payment_method', 'bizum');
        if (! in_array($paymentMethod, ['bizum', 'redsys_card', 'stripe'], true)) {
            $paymentMethod = 'bizum';
        }

        // Valida se o método escolhido está devidamente ativo nas configurações para a moeda do pacote
        if (! Setting::isPaymentMethodActive($paymentMethod, $tenant, $pack)) {
            $msg = __('O método de pagamento selecionado não está disponível para este pacote.');

            if ($isEmbedded) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        // Dados Fiscais de Faturamento
        $billingType = $request->input('billing_type', 'particular');
        $billingTaxId = trim($request->input('billing_tax_id', $request->input('tax_id', '')));
        $billingLegalName = trim($request->input('billing_legal_name', $request->input('customer_name', $cliente->company_name ?? $cliente->name)));
        $billingAddress = trim($request->input('billing_address', $request->input('customer_address', '')));
        $billingPostalCode = trim($request->input('billing_postal_code', $request->input('customer_postal_code', '')));
        $billingCity = trim($request->input('billing_city', ''));
        $billingCountry = strtoupper(trim($request->input('billing_country', $request->input('tax_country', 'ES')))) ?: 'ES';

        // Atualiza a identificação fiscal no cadastro do cliente se estiver vazia
        if ($billingTaxId !== '' && empty($cliente->tax_id)) {
            $cliente->update(['tax_id' => $billingTaxId]);
        }

        // Add-ons selecionados resolvidos pelo tenant do pacote
        $selectedAddons = $request->input('selected_addons', []);
        if (! is_array($selectedAddons)) {
            $selectedAddons = [];
        }

        $pricing = PackCheckoutPricing::resolve($pack, $selectedAddons);
        $subtotal = $pricing->subtotal;
        $selectedAddonNames = $pricing->addonNames;

        $fiscalRegime = Setting::get('fiscal_regime', 'autonomo');
        $irpfRate = ($fiscalRegime === 'autonomo') ? (float) Setting::get('fiscal_irpf_rate', 15.00) : 0.00;

        // Execução do Motor Fiscal (IVA 21%, IRPF e Isenções)
        $taxData = $taxService->calculate(
            subtotal: $subtotal,
            countryCode: $billingCountry,
            postalCode: $billingPostalCode,
            taxId: $billingTaxId,
            isCompany: ($billingType === 'empresa_autonomo'),
            irpfRate: $irpfRate
        );

        $applicationFeePercent = (float) config('services.stripe.platform_fee_percent', 10);
        $applicationFeeAmount = round($taxData['subtotal'] * ($applicationFeePercent / 100), 2);

        // Registro Seguro do PackPurchase no Banco de Dados
        $isSubscription = ($pack->billing_type === 'subscription');
        $commitmentMonths = $isSubscription ? max(1, (int) ($pack->commitment_months ?: 3)) : 1;
        $billingInterval = $isSubscription ? ($pack->billing_interval ?: 'month') : 'month';
        $intervalMonths = ($billingInterval === 'quarter') ? 3 : 1;
        $cyclesTotal = ($billingInterval === 'quarter') ? (int) ceil($commitmentMonths / 3) : $commitmentMonths;

        $purchaseData = [
            'payment_gateway' => $paymentMethod,
            'subtotal' => $taxData['subtotal'],
            'tax_rate' => $taxData['tax_rate'],
            'tax_amount' => $taxData['tax_amount'],
            'irpf_rate' => $taxData['irpf_rate'],
            'irpf_amount' => $taxData['irpf_amount'],
            'total_amount' => $taxData['total_amount'],
            'amount' => $taxData['total_amount'],
            'billing_type' => $billingType,
            'billing_tax_id' => $billingTaxId ?: null,
            'billing_legal_name' => $billingLegalName ?: null,
            'billing_address' => $billingAddress ?: null,
            'billing_postal_code' => $billingPostalCode ?: null,
            'billing_city' => $billingCity ?: null,
            'billing_country' => $billingCountry,
            'application_fee_amount' => $applicationFeeAmount,
            'video_quantity' => $pack->video_quantity,
            'addons_purchased' => $selectedAddonNames,
            'addons_snapshot' => $pricing->addonsSnapshot,
            'status' => 'pending',
            'subscription_status' => null, // vira 'active' só no PackPaymentConfirmer, após o pagamento
            'subscription_interval' => $billingInterval,
            'commitment_months' => $commitmentMonths,
            'subscription_cycle_current' => 1,
            'subscription_cycles_total' => $cyclesTotal,
            'subscription_renews_at' => $isSubscription ? now()->addMonths($intervalMonths) : null,
            'subscription_ends_at' => $isSubscription ? now()->addMonths($commitmentMonths) : null,
        ];

        // Reutiliza pedido pendente recente para o mesmo pacote e cliente, se existir
        $purchase = PackPurchase::where('tenant_id', $tenant->id)
            ->where('cliente_id', $cliente->id)
            ->where('pack_id', $pack->id)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subHours(2))
            ->latest()
            ->first();

        if ($purchase) {
            $purchase->update($purchaseData);
        } else {
            $purchase = PackPurchase::create(array_merge([
                'tenant_id' => $tenant->id,
                'cliente_id' => $cliente->id,
                'pack_id' => $pack->id,
            ], $purchaseData));
        }

        // A) Redsys (Bizum ou Cartão)
        if (in_array($paymentMethod, ['bizum', 'redsys_card'], true)) {
            $orderNumber = $redsysService->generateOrderNumber();
            $purchase->update(['gateway_order_reference' => $orderNumber]);

            $payMethodCode = ($paymentMethod === 'bizum') ? 'z' : 'T';

            $productDesc = $isSubscription
                ? "{$pack->name} (Contrato {$commitmentMonths}M) - {$tenant->name}"
                : "{$pack->name} - {$tenant->name}";

            // Mesmo campo conferido no Ds_Amount pelo PackPaymentConfirmer
            $payload = $redsysService->createPaymentPayload(
                amountInEuros: (float) $purchase->amount,
                orderNumber: $orderNumber,
                urlOk: route('redsys.success', ['order' => $orderNumber, 'purchase_id' => $purchase->id]),
                urlKo: route('redsys.cancel', ['order' => $orderNumber, 'purchase_id' => $purchase->id]),
                notificationUrl: url('/webhooks/redsys'),
                payMethod: $payMethodCode,
                productDescription: $productDesc,
                titular: $cliente->name
            );

            return view('storefront.redsys_redirect', compact('payload', 'purchase', 'pack', 'tenant'));
        }

        // B) Stripe
        $stripeSecret = StripeCredentials::secret();
        if (empty($stripeSecret)) {
            if ($isEmbedded) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'O gateway Stripe não está configurado no momento.',
                ], 422);
            }

            return back()->with('error', 'O gateway Stripe não está configurado no momento.');
        }

        $stripe = new StripeClient($stripeSecret);
        $currency = strtolower($pack->currency ?: 'eur');
        $addonsSummary = ! empty($selectedAddonNames) ? (' + Opcionais: '.implode(', ', $selectedAddonNames)) : '';

        $recurringConfig = $isSubscription ? [
            'interval' => 'month',
            'interval_count' => ($billingInterval === 'quarter' ? 3 : 1),
        ] : null;

        $mainPriceData = [
            'currency' => $currency,
            'product_data' => [
                'name' => $pack->name.($isSubscription ? " (Contrato {$commitmentMonths} Meses)" : ''),
                'description' => "Pacote de {$pack->video_quantity} vídeos/ciclo — {$tenant->name}{$addonsSummary}",
            ],
            'unit_amount' => Money::cents($pack->price),
        ];
        if ($recurringConfig) {
            $mainPriceData['recurring'] = $recurringConfig;
        }

        $lineItems = [[
            'price_data' => $mainPriceData,
            'quantity' => 1,
        ]];

        foreach ($pricing->addonsSnapshot as $addonItem) {
            if ($addonItem['price'] > 0) {
                $addonPriceData = [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => '+ '.$addonItem['name'],
                        'description' => "Add-on opcional para {$pack->name}",
                    ],
                    'unit_amount' => Money::cents($addonItem['price']),
                ];
                if ($recurringConfig) {
                    $addonPriceData['recurring'] = $recurringConfig;
                }

                $lineItems[] = [
                    'price_data' => $addonPriceData,
                    'quantity' => 1,
                ];
            }
        }

        if ($taxData['tax_amount'] > 0) {
            $taxPriceData = [
                'currency' => $currency,
                'product_data' => ['name' => "IVA ({$taxData['tax_rate']}%)"],
                'unit_amount' => Money::cents($taxData['tax_amount']),
            ];
            if ($recurringConfig) {
                $taxPriceData['recurring'] = $recurringConfig;
            }

            $lineItems[] = [
                'price_data' => $taxPriceData,
                'quantity' => 1,
            ];
        }

        // O PackPaymentConfirmer confere amount_total == Money::cents($purchase->amount):
        // a sessão só é criada se os line items (menos o cupom de IRPF) baterem com esse valor.
        $irpfDiscountCents = (($taxData['irpf_amount'] ?? 0) > 0) ? Money::cents($taxData['irpf_amount']) : 0;
        if (Money::lineItemsTotal($lineItems) - $irpfDiscountCents !== Money::cents($purchase->amount)) {
            Log::error("Checkout do portal: valor dos itens diverge da compra #{$purchase->id}.");
            $message = __('Não foi possível iniciar o pagamento. Tente novamente em instantes.');

            return $isEmbedded
                ? response()->json(['status' => 'error', 'message' => $message], 422)
                : back()->with('error', $message);
        }

        $sessionPayload = [
            'mode' => $isSubscription ? 'subscription' : 'payment',
            'customer_email' => $cliente->email,
            'line_items' => $lineItems,
            'metadata' => [
                'type' => 'pack_purchase',
                'pack_purchase_id' => $purchase->id,
                'is_subscription' => $isSubscription ? '1' : '0',
                'commitment_months' => (string) $commitmentMonths,
            ],
        ];

        if ($isEmbedded) {
            $sessionPayload['ui_mode'] = 'embedded_page';
            $sessionPayload['return_url'] = route('portal.packs.success').'?session_id={CHECKOUT_SESSION_ID}&purchase_id='.$purchase->id;
        } else {
            $sessionPayload['success_url'] = route('portal.packs.success').'?session_id={CHECKOUT_SESSION_ID}&purchase_id='.$purchase->id;
            $sessionPayload['cancel_url'] = route('portal.packs.cancel');
        }

        // Dedução Oficial de IRPF (Autônomo B2B na Espanha)
        if (($taxData['irpf_amount'] ?? 0) > 0) {
            $irpfCoupon = $stripe->coupons->create([
                'amount_off' => Money::cents($taxData['irpf_amount']),
                'currency' => $currency,
                'duration' => 'once',
                'name' => "Retención IRPF ({$taxData['irpf_rate']}%)",
            ]);
            $sessionPayload['discounts'] = [
                ['coupon' => $irpfCoupon->id],
            ];
        }

        if ($isSubscription) {
            $sessionPayload['subscription_data'] = [
                'application_fee_percent' => (float) config('services.stripe.platform_fee_percent', 10),
                'transfer_data' => ['destination' => $tenant->stripe_account_id],
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'pack_purchase_id' => $purchase->id,
                    'commitment_months' => (string) $commitmentMonths,
                ],
            ];
        } else {
            $sessionPayload['payment_intent_data'] = [
                'application_fee_amount' => Money::cents($applicationFeeAmount),
                'transfer_data' => ['destination' => $tenant->stripe_account_id],
            ];
        }

        try {
            $session = $stripe->checkout->sessions->create($sessionPayload);
            $purchase->update(['stripe_checkout_session_id' => $session->id]);

            if ($isEmbedded) {
                return response()->json([
                    'status' => 'success',
                    'gateway' => 'stripe_embedded',
                    'client_secret' => $session->client_secret,
                    'publishable_key' => StripeCredentials::publishableKey(),
                    'purchase_id' => $purchase->id,
                ]);
            }

            return redirect($session->url);
        } catch (\Exception $e) {
            $stripeCode = ($e instanceof ApiErrorException) ? $e->getStripeCode() : null;
            Log::error('Erro ao criar sessão de checkout da Stripe: '.get_class($e), [
                'stripe_code' => $stripeCode,
            ]);

            $genericMessage = __('Não foi possível iniciar o checkout seguro da Stripe. Tente novamente em instantes.');

            if ($isEmbedded) {
                return response()->json([
                    'status' => 'error',
                    'message' => $genericMessage,
                ], 422);
            }

            return back()->with('error', $genericMessage);
        }
    }

    public function success(Request $request): View
    {
        $cliente = $request->user('cliente');
        $latestPurchase = PackPurchase::where('cliente_id', $cliente->id)
            ->where('status', 'paid')
            ->latest()
            ->first();

        return view('portal.packs.success', [
            'purchase' => $latestPurchase,
        ]);
    }

    public function cancel(): View
    {
        return view('portal.packs.cancel');
    }

    /**
     * Download do PDF oficial da Factura (Agencia Tributaria).
     */
    public function downloadInvoice(
        Request $request,
        PackPurchase $purchase,
        InvoiceService $invoiceService
    ): \Symfony\Component\HttpFoundation\Response {
        $cliente = $request->user('cliente');
        abort_unless($purchase->cliente_id === $cliente->id, 403, 'Acesso negado a esta fatura.');
        abort_unless($purchase->isPaid(), 404, 'Factura no disponible para pedidos no abonados.');

        $pdf = $invoiceService->renderPdf($purchase);

        return $pdf->download("Factura-{$purchase->invoice_number}.pdf");
    }
}
