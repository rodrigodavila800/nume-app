<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\ShowcaseVideo;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Stripe\StripeClient;
use Exception;

class StorefrontController extends Controller
{
    /**
     * Exibe a vitrine oficial. Se chamada via /vitrine/nume, redireciona canonicamente para /.
     */
    public function show(Tenant $tenant, Request $request): View|RedirectResponse
    {
        abort_unless($tenant->isActive(), 404);

        // Redirecionamento canônico: se acessar /vitrine/nume, redireciona para a raiz /
        if ($tenant->slug === 'nume' && $request->routeIs('storefront.show') && $request->path() !== '/') {
            return redirect()->route('marketplace.index');
        }

        // Vitrine pública: tudo filtrado explicitamente pelo tenant da vitrine. Com um usuário
        // logado de outra produtora, o scope BelongsToTenant filtraria pelo tenant DELE.
        return view('storefront.show', [
            'tenant' => $tenant,
            'categories' => Category::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'videos' => ShowcaseVideo::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->with(['category' => fn ($q) => $q->withoutGlobalScope('tenant')->where('categories.tenant_id', $tenant->id)])
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'packs' => $this->storefrontPacks($tenant)->get(),
            'recurringPlans' => $this->storefrontPacks($tenant)
                ->where('billing_type', 'subscription')
                ->get(),
            'oneOffPacks' => $this->storefrontPacks($tenant)
                ->where(function ($q) {
                    $q->where('billing_type', '!=', 'subscription')
                      ->orWhereNull('billing_type');
                })
                ->get(),
            'currentCliente' => auth('cliente')->user(),
            'activePaymentMethods' => \App\Models\Setting::getActivePaymentMethods($tenant),
            'fiscalSettings' => [
                'regime' => \App\Models\Setting::get('fiscal_regime', 'autonomo'),
                'irpf_rate' => (\App\Models\Setting::get('fiscal_regime', 'autonomo') === 'autonomo') ? (float) \App\Models\Setting::get('fiscal_irpf_rate', 15.00) : 0.00,
                'iva_rate' => (float) \App\Models\Setting::get('tax_default_iva_rate', 21.00),
            ],
        ]);
    }

    /**
     * Pacotes ativos da vitrine com categoria, adicionais e vídeo de destaque, todos pelo tenant da vitrine.
     */
    protected function storefrontPacks(Tenant $tenant): \Illuminate\Database\Eloquent\Builder
    {
        return Pack::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->with([
                'category' => fn ($q) => $q->withoutGlobalScope('tenant')->where('categories.tenant_id', $tenant->id),
                'addons' => fn ($q) => $q->withoutGlobalScope('tenant')->where('addons.tenant_id', $tenant->id),
                'showcaseVideo' => fn ($q) => $q->withoutGlobalScope('tenant')->where('showcase_videos.tenant_id', $tenant->id),
            ])
            ->orderBy('price');
    }

    /**
     * Checkout de pacotes com verificação estrita de cadastro prévio, motor fiscal (IVA 21%) e múltiplos gateways.
     */
    public function checkout(
        Request $request,
        Tenant $tenant,
        Pack $pack,
        \App\Services\Billing\TaxCalculationService $taxService,
        \App\Services\Payment\RedsysService $redsysService
    ): \Illuminate\Http\Response|RedirectResponse|View {
        abort_unless($tenant->isActive() && $pack->tenant_id === $tenant->id && $pack->isActive(), 404);

        $paymentMethod = $request->input('payment_method', 'bizum'); // 'bizum', 'redsys_card', 'stripe'
        if (! in_array($paymentMethod, ['bizum', 'redsys_card', 'stripe'], true)) {
            $paymentMethod = 'bizum';
        }

        // Valida se o método escolhido está ativo no tenant / sistema
        if (! \App\Models\Setting::isPaymentMethodActive($paymentMethod, $tenant)) {
            return back()->withInput()->with('error', __('O método de pagamento selecionado não está disponível no momento.'));
        }

        $email = Str::lower($request->input('email', ''));

        // Se o cliente já estiver autenticado na sessão atual
        if (auth('cliente')->check()) {
            $cliente = auth('cliente')->user();
        } else {
            // Validação de credenciais do cliente
            $validated = $request->validate([
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:6'],
                'name' => ['nullable', 'string', 'max:255'],
                'company_name' => ['nullable', 'string', 'max:255'],
            ]);

            // 1. Verifica se o cliente JÁ TEM CADASTRO no banco de dados
            $cliente = Cliente::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->first();

            if ($cliente) {
                // Cliente existente: valida credencial de segurança (senha) para evitar personificação
                if (! Hash::check($validated['password'], $cliente->password)) {
                    return back()->withInput()->with('error', __('Este e-mail já possui cadastro. Informe a senha correta da sua conta de cliente para prosseguir com a compra.'));
                }

                // Atualiza nome/empresa se informados
                if (! empty($validated['name'])) {
                    $cliente->update([
                        'name' => $validated['name'],
                        'company_name' => $validated['company_name'] ?? $cliente->company_name,
                    ]);
                }
            } else {
                // 2. Cliente NÃO TEM CADASTRO: exige nome para criar nova conta
                if (empty($validated['name'])) {
                    return back()->withInput()->with('error', __('Informe seu nome completo para criar sua conta de cliente.'));
                }

                $cliente = Cliente::withoutGlobalScope('tenant')->create([
                    'tenant_id' => $tenant->id,
                    'name' => $validated['name'],
                    'email' => $email,
                    'company_name' => $validated['company_name'] ?? null,
                    'password' => Hash::make($validated['password']),
                ]);
            }

            // Autentica o cliente na sessão com segurança
            auth('cliente')->login($cliente);
        }

        // 3. Dados Fiscais de Faturamento (IVA / Hacienda)
        $billingType = $request->input('billing_type', 'particular');
        $billingTaxId = trim($request->input('billing_tax_id', ''));
        $billingLegalName = trim($request->input('billing_legal_name', $cliente->company_name ?? $cliente->name));
        $billingAddress = trim($request->input('billing_address', ''));
        $billingPostalCode = trim($request->input('billing_postal_code', ''));
        $billingCity = trim($request->input('billing_city', ''));
        $billingCountry = strtoupper(trim($request->input('billing_country', 'ES'))) ?: 'ES';

        // 4. Cálculo da Base Imponible com Add-ons
        $selectedAddons = $request->input('selected_addons', []);
        if (! is_array($selectedAddons)) {
            $selectedAddons = [];
        }

        $effectiveAddons = collect($pack->effective_addons);
        $addonsTotal = 0;
        $selectedAddonNames = [];

        foreach ($selectedAddons as $selected) {
            $matched = $effectiveAddons->first(function ($addon) use ($selected) {
                return (isset($addon['id']) && (string) $addon['id'] === (string) $selected)
                    || (isset($addon['name']) && (string) $addon['name'] === (string) $selected)
                    || (isset($addon['slug']) && (string) $addon['slug'] === (string) $selected);
            });

            if ($matched) {
                $addonsTotal += (float) ($matched['price'] ?? 0);
                $selectedAddonNames[] = $matched['name'];
            } else {
                $selectedAddonNames[] = (string) $selected;
            }
        }

        $subtotal = round((float) $pack->price + $addonsTotal, 2);

        $fiscalRegime = \App\Models\Setting::get('fiscal_regime', 'autonomo');
        $irpfRate = ($fiscalRegime === 'autonomo') ? (float) \App\Models\Setting::get('fiscal_irpf_rate', 15.00) : 0.00;

        // 5. Execução do Motor Fiscal (IVA 21%, IRPF e Isenções)
        $taxData = $taxService->calculate(
            subtotal: $subtotal,
            countryCode: $billingCountry,
            postalCode: $billingPostalCode,
            taxId: $billingTaxId,
            isCompany: ($billingType === 'empresa_autonomo'),
            irpfRate: $irpfRate
        );

        $feePercent = (float) config('services.stripe.platform_fee_percent', 10);
        $feeAmount = round($taxData['subtotal'] * ($feePercent / 100), 2);

        // 6. Registro Seguro do PackPurchase no Banco de Dados
        $isSubscription = ($pack->billing_type === 'subscription');
        $isSplit5050 = (! $isSubscription && $pack->isSplit5050());
        $billingMode = $isSubscription ? 'subscription' : ($pack->billing_mode ?: 'full_upfront');

        $totalAmount = (float) $taxData['total_amount'];
        $depositAmount = $isSplit5050 ? $pack->calculateDepositAmount($totalAmount) : $totalAmount;
        $balanceAmount = $isSplit5050 ? $pack->calculateBalanceAmount($totalAmount) : 0.00;
        $balanceStatus = $isSplit5050 ? 'pending' : 'not_applicable';
        $initialChargedAmount = $isSplit5050 ? $depositAmount : $totalAmount;

        $commitmentMonths = $isSubscription ? max(1, (int) ($pack->commitment_months ?: 3)) : 1;
        $billingInterval = $isSubscription ? ($pack->billing_interval ?: 'month') : 'month';
        $intervalMonths = ($billingInterval === 'quarter') ? 3 : 1;
        $cyclesTotal = ($billingInterval === 'quarter') ? (int) ceil($commitmentMonths / 3) : $commitmentMonths;

        $purchase = PackPurchase::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'pack_id' => $pack->id,
            'payment_gateway' => $paymentMethod,
            'subtotal' => $taxData['subtotal'],
            'tax_rate' => $taxData['tax_rate'],
            'tax_amount' => $taxData['tax_amount'],
            'irpf_rate' => $taxData['irpf_rate'],
            'irpf_amount' => $taxData['irpf_amount'],
            'total_amount' => $taxData['total_amount'],
            'amount' => $initialChargedAmount, // Valor líquido cobrado no ato
            'billing_mode' => $billingMode,
            'deposit_amount' => $depositAmount,
            'balance_amount' => $balanceAmount,
            'balance_status' => $balanceStatus,
            'billing_type' => $billingType,
            'billing_tax_id' => $billingTaxId ?: null,
            'billing_legal_name' => $billingLegalName ?: null,
            'billing_address' => $billingAddress ?: null,
            'billing_postal_code' => $billingPostalCode ?: null,
            'billing_city' => $billingCity ?: null,
            'billing_country' => $billingCountry,
            'application_fee_amount' => $feeAmount,
            'video_quantity' => $pack->video_quantity,
            'addons_purchased' => $selectedAddonNames,
            'status' => 'pending',
            'subscription_status' => null, // vira 'active' só no PackPaymentConfirmer, após o pagamento
            'subscription_interval' => $billingInterval,
            'commitment_months' => $commitmentMonths,
            'subscription_cycle_current' => 1,
            'subscription_cycles_total' => $cyclesTotal,
            'subscription_renews_at' => $isSubscription ? now()->addMonths($intervalMonths) : null,
            'subscription_ends_at' => $isSubscription ? now()->addMonths($commitmentMonths) : null,
        ]);

        // 7. Roteamento de Gateway
        // A) BBVA / Redsys (Bizum ou Cartão de Crédito/Débito)
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
                urlOk: route('redsys.success', ['order' => $orderNumber]),
                urlKo: route('redsys.cancel', ['order' => $orderNumber]),
                notificationUrl: url('/webhooks/redsys'),
                payMethod: $payMethodCode,
                productDescription: $productDesc,
                titular: $cliente->name
            );

            return view('storefront.redsys_redirect', compact('payload', 'purchase', 'pack', 'tenant'));
        }

        // B) Stripe Checkout
        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $currency = strtolower($pack->currency ?: 'eur');
            $addonsSummary = ! empty($selectedAddonNames) ? (' + Opcionais: ' . implode(', ', $selectedAddonNames)) : '';

            $recurringConfig = $isSubscription ? [
                'interval' => 'month',
                'interval_count' => ($billingInterval === 'quarter' ? 3 : 1),
            ] : null;

            if ($isSplit5050) {
                $depPct = $pack->getEffectiveDepositPercentage();
                $lineItems = [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'product_data' => [
                                'name' => "{$pack->name} — Sinal ({$depPct}%)",
                                'description' => "Entrada de {$depPct}%. Saldo de " . number_format($balanceAmount, 2, ',', '.') . " {$pack->currency} a pagar na aprovação do corte final.",
                            ],
                            'unit_amount' => Money::cents($depositAmount),
                        ],
                        'quantity' => 1,
                    ],
                ];
            } else {
                $mainPriceData = [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => $pack->name . ($isSubscription ? " (Contrato {$commitmentMonths} Meses)" : ''),
                        'description' => "Pacote de {$pack->video_quantity} vídeos/ciclo — {$tenant->name}{$addonsSummary}",
                    ],
                    'unit_amount' => Money::cents($pack->price),
                ];
                if ($recurringConfig) {
                    $mainPriceData['recurring'] = $recurringConfig;
                }

                $lineItems = [
                    [
                        'price_data' => $mainPriceData,
                        'quantity' => 1,
                    ],
                ];

                foreach ($selectedAddons as $selected) {
                    $matched = $effectiveAddons->first(function ($addon) use ($selected) {
                        return (isset($addon['id']) && (string) $addon['id'] === (string) $selected)
                            || (isset($addon['name']) && (string) $addon['name'] === (string) $selected)
                            || (isset($addon['slug']) && (string) $addon['slug'] === (string) $selected);
                    });

                    if ($matched && (float) ($matched['price'] ?? 0) > 0) {
                        $addonPriceData = [
                            'currency' => $currency,
                            'product_data' => [
                                'name' => '+ ' . $matched['name'],
                                'description' => "Add-on opcional para {$pack->name}",
                            ],
                            'unit_amount' => Money::cents($matched['price']),
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

                // Item fiscal de IVA se aplicável
                if ($taxData['tax_amount'] > 0) {
                    $taxPriceData = [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => "IVA ({$taxData['tax_rate']}%)",
                            'description' => "Cota tributária legal (Agencia Tributaria)",
                        ],
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
            }

            // O PackPaymentConfirmer confere amount_total == Money::cents($purchase->amount):
            // a sessão só é criada se os line items (menos o cupom de IRPF) baterem com esse valor.
            $irpfDiscountCents = (! $isSplit5050 && ($taxData['irpf_amount'] ?? 0) > 0) ? Money::cents($taxData['irpf_amount']) : 0;
            if (Money::lineItemsTotal($lineItems) - $irpfDiscountCents !== Money::cents($purchase->amount)) {
                throw new \RuntimeException('checkout_amount_mismatch');
            }

            $sessionPayload = [
                'mode' => $isSubscription ? 'subscription' : 'payment',
                'customer_email' => $cliente->email,
                'line_items' => $lineItems,
                // A compra é localizada pela sessão + tenant da vitrine, nunca por um id na URL.
                'success_url' => route('storefront.success', $tenant).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url('/#pacotes'),
                'metadata' => [
                    'type' => 'pack_purchase',
                    'tenant_id' => $tenant->id,
                    'pack_purchase_id' => $purchase->id,
                    'cliente_id' => $cliente->id,
                    'is_subscription' => $isSubscription ? '1' : '0',
                    'is_split_50_50' => $isSplit5050 ? '1' : '0',
                    'commitment_months' => (string) $commitmentMonths,
                    'addons' => json_encode($selectedAddonNames),
                ],
            ];

            // Dedução Oficial de IRPF (Autônomo B2B na Espanha)
            if (! $isSplit5050 && ($taxData['irpf_amount'] ?? 0) > 0) {
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
                $paymentIntentData = [
                    'application_fee_amount' => Money::cents($feeAmount * ($isSplit5050 ? ($pack->getEffectiveDepositPercentage() / 100) : 1)),
                    'transfer_data' => ['destination' => $tenant->stripe_account_id],
                ];

                if ($isSplit5050) {
                    $paymentIntentData['setup_future_usage'] = 'off_session';
                }

                $sessionPayload['payment_intent_data'] = $paymentIntentData;
            }

            $session = $stripe->checkout->sessions->create($sessionPayload);

            $purchase->update(['stripe_checkout_session_id' => $session->id]);

            return redirect($session->url);
        } catch (Exception $e) {
            // Detalhe só no log (tipo e código da Stripe); o visitante recebe mensagem genérica.
            $stripeCode = $e instanceof \Stripe\Exception\ApiErrorException ? ' ['.($e->getStripeCode() ?? 'sem_codigo').']' : '';
            \Illuminate\Support\Facades\Log::warning("Checkout da vitrine: falha ao criar sessão da Stripe para a compra #{$purchase->id}: ".get_class($e).$stripeCode);

            return back()->withInput()->with('error', __('Não foi possível iniciar o pagamento. Tente novamente em instantes ou escolha outro meio de pagamento.'));
        }
    }

    /**
     * Retorno de sucesso após pagamento no BBVA Redsys (Bizum/Cartão).
     */
    public function redsysSuccess(
        Request $request,
        \App\Services\Payment\RedsysService $redsysService,
        \App\Services\Payment\PackPaymentConfirmer $confirmer
    ): View {
        // A URL OK é aberta pelo navegador e pode ser forjada: ela nunca marca a compra como paga
        // sozinha. Só confirma se trouxer os parâmetros do Redsys com assinatura válida.
        $parameters = (string) $request->query('Ds_MerchantParameters', '');
        $signature = (string) $request->query('Ds_Signature', '');
        $purchase = null;

        if ($parameters !== '' && $signature !== '' && $redsysService->verifySignature($parameters, $signature)) {
            $data = $redsysService->decodeParameters($parameters);
            $purchase = $this->purchaseByRedsysOrder($data['Ds_Order'] ?? $data['DS_MERCHANT_ORDER'] ?? null);

            if ($purchase) {
                $confirmer->confirmRedsys($purchase, $data);
            }
        } else {
            // Sem assinatura válida: só leitura do status gravado (pelo webhook).
            $purchase = $this->purchaseByRedsysOrder($request->query('order'));
        }

        $tenant = $purchase?->tenant ?? Tenant::where('slug', 'nume')->first();

        // Detalhes da compra só para o cliente dono dela; os demais veem só o estado.
        $ownsPurchase = $purchase && auth('cliente')->id() === $purchase->cliente_id;

        return view('storefront.success', [
            'tenant' => $tenant,
            'purchase' => $ownsPurchase ? $purchase : null,
            'paymentState' => $this->paymentState($purchase),
        ]);
    }

    protected function purchaseByRedsysOrder(mixed $order): ?PackPurchase
    {
        if (! is_string($order) || ! preg_match('/^[0-9A-Za-z]{4,12}$/', $order)) {
            return null;
        }

        return PackPurchase::withoutGlobalScope('tenant')
            ->where('gateway_order_reference', $order)
            ->first();
    }

    /**
     * 'paid' (pago confirmado), 'processing' (aguardando o gateway) ou 'unknown' (compra não localizada).
     */
    protected function paymentState(?PackPurchase $purchase): string
    {
        return match (true) {
            $purchase === null => 'unknown',
            $purchase->isPaid() => 'paid',
            default => 'processing',
        };
    }

    /**
     * Retorno de cancelamento do BBVA Redsys.
     */
    public function redsysCancel(Request $request): RedirectResponse
    {
        return redirect('/#pacotes')->with(
            'warning',
            __('O pagamento via BBVA / Redsys não foi finalizado. Você pode tentar novamente ou escolher outro meio.')
        );
    }

    public function success(Request $request, Tenant $tenant, \App\Services\Payment\PackPaymentConfirmer $confirmer): View
    {
        abort_unless($tenant->isActive(), 404);

        // Compra localizada pela sessão da Stripe E pelo tenant da vitrine (o purchase_id da URL é ignorado).
        $sessionId = (string) $request->query('session_id', '');
        $purchase = null;

        if (preg_match('/^cs_[A-Za-z0-9_]{8,255}$/', $sessionId)) {
            $purchase = PackPurchase::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('stripe_checkout_session_id', $sessionId)
                ->first();
        }

        // Ainda pendente (webhook não chegou): consulta a sessão na API. O checkout usa destination
        // charges, então a sessão está na conta da plataforma (sem cabeçalho Stripe-Account).
        if ($purchase && $purchase->status === 'pending' && config('services.stripe.secret')) {
            try {
                $session = (new StripeClient(config('services.stripe.secret')))->checkout->sessions->retrieve($sessionId);
                $confirmer->confirmStripeSession($purchase, $session);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Retorno da Stripe: não foi possível consultar a sessão da compra #{$purchase->id}: ".get_class($e));
            }
        }

        return view('storefront.success', [
            'tenant' => $tenant,
            'purchase' => $purchase,
            'paymentState' => $this->paymentState($purchase),
        ]);
    }

    /**
     * Atendimento comercial e consultoria da Sofi na vitrine pública.
     */
    public function sofiChat(
        Request $request,
        Tenant $tenant,
        \App\Services\SofiSalesConsultantService $salesService
    ): \Illuminate\Http\JsonResponse {
        abort_unless($tenant->isActive(), 404);

        $validated = $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $message = trim($validated['message']);
        $sessionKey = 'sofi_sales_history_' . $tenant->id;
        $history = session()->get($sessionKey, []);
        $locale = session('locale', app()->getLocale() ?: 'pt_BR');

        $result = $salesService->consult($tenant, $message, $history, $locale);

        if (! empty($result['is_reset'])) {
            session()->forget($sessionKey);
        } else {
            // Atualiza histórico em sessão (mantém últimas 6 mensagens)
            $history[] = ['role' => 'user', 'text' => $message];
            $history[] = ['role' => 'model', 'text' => $result['reply']];
            if (count($history) > 6) {
                $history = array_slice($history, -6);
            }
            session()->put($sessionKey, $history);
        }

        return response()->json([
            'status' => 'success',
            'agent' => 'Sofi Sales Copilot',
            'reply' => $result['reply'],
            'call_to_action' => $result['call_to_action'] ?? null,
            'recommended_pack' => $result['recommended_pack'] ?? null,
            'dna_register_url' => $result['dna_register_url'] ?? route('portal.register'),
            'quick_replies' => $result['quick_replies'] ?? [],
        ]);
    }

    /**
     * Reinicia o histórico de atendimento no chat da Sofi para permitir novo diagnóstico ou nicho.
     */
    public function resetSofiChat(Tenant $tenant): \Illuminate\Http\JsonResponse
    {
        abort_unless($tenant->isActive(), 404);

        $sessionKey = 'sofi_sales_history_' . $tenant->id;
        session()->forget($sessionKey);

        return response()->json([
            'status' => 'success',
            'message' => __('Atendimento reiniciado com sucesso!'),
        ]);
    }
}
