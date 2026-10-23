<?php

namespace App\Services\Payment;

use App\Models\PackPurchase;
use App\Services\Billing\InvoiceService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Throwable;

/**
 * Único ponto que marca uma PackPurchase como paga.
 *
 * Só deve ser chamado com dados confiáveis: evento de webhook com assinatura válida,
 * sessão consultada na API da Stripe ou parâmetros do Redsys com assinatura validada
 * pelo chamador. URL de retorno sem assinatura nunca chega aqui.
 *
 * A transição pending → paid acontece uma única vez (lockForUpdate), e só quem a fez
 * dispara os efeitos (fatura, produção inicial).
 */
class PackPaymentConfirmer
{
    public const PAID = 'paid';

    public const ALREADY_PROCESSED = 'already_processed';

    public const FAILED = 'failed';

    public function __construct(
        protected RedsysService $redsys,
    ) {}

    /**
     * Confirma uma Checkout Session da Stripe (webhook ou sessão consultada na API).
     * Vale para mode=payment e mode=subscription (primeira cobrança); renovações não passam aqui.
     */
    public function confirmStripeSession(PackPurchase $purchase, object $session): string
    {
        if ($reason = $this->stripeSessionMismatch($purchase, $session)) {
            return $this->reject($purchase, 'stripe', $reason);
        }

        if (($session->payment_status ?? null) !== 'paid') {
            // Pagamento assíncrono ainda em andamento: aguarda checkout.session.async_payment_*.
            return $this->reject($purchase, 'stripe', 'payment_status_'.($session->payment_status ?? 'ausente'));
        }

        if ((int) ($session->amount_total ?? -1) !== Money::cents($purchase->amount)) {
            return $this->reject($purchase, 'stripe', 'amount_mismatch');
        }

        if (! Money::sameCurrency($session->currency ?? null, $purchase->pack?->currency ?: 'eur')) {
            return $this->reject($purchase, 'stripe', 'currency_mismatch');
        }

        $paymentIntentId = (string) ($session->payment_intent ?? '');
        $transitioned = $this->transitionToPaid($purchase, function (PackPurchase $locked) use ($paymentIntentId, $session) {
            $locked->markAsPaid($paymentIntentId);

            if ($this->isSubscriptionPurchase($locked)) {
                $locked->update(array_filter([
                    'subscription_status' => 'active',
                    'stripe_subscription_id' => is_string($session->subscription ?? null) ? $session->subscription : null,
                ]));
            }
        });

        if (! $transitioned) {
            return self::ALREADY_PROCESSED;
        }

        if ($purchase->isSplit5050()) {
            $this->storeSplitDepositPaymentMethod($purchase, $paymentIntentId);
        }

        $this->issueInvoice($purchase, 'Stripe');

        return self::PAID;
    }

    /**
     * Pagamento assíncrono recusado (checkout.session.async_payment_failed): nada é liberado.
     */
    public function failStripeSession(PackPurchase $purchase, object $session): string
    {
        if ($reason = $this->stripeSessionMismatch($purchase, $session)) {
            return $this->reject($purchase, 'stripe', $reason);
        }

        return $this->transitionToFailed($purchase, ['status' => 'failed']) ? self::FAILED : self::ALREADY_PROCESSED;
    }

    /**
     * Confirma uma notificação do Redsys. A assinatura de $data JÁ deve ter sido validada pelo chamador.
     */
    public function confirmRedsys(PackPurchase $purchase, array $data): string
    {
        $order = (string) ($data['Ds_Order'] ?? $data['DS_MERCHANT_ORDER'] ?? '');
        if ($order === '' || ! hash_equals((string) $purchase->gateway_order_reference, $order)) {
            return $this->reject($purchase, 'redsys', 'order_mismatch');
        }

        $amount = (string) ($data['Ds_Amount'] ?? '');
        if (! ctype_digit($amount) || (int) $amount !== Money::cents($purchase->amount)) {
            return $this->reject($purchase, 'redsys', 'amount_mismatch');
        }

        if ((string) ($data['Ds_Currency'] ?? '') !== RedsysService::CURRENCY_EUR) {
            return $this->reject($purchase, 'redsys', 'currency_mismatch');
        }

        if (isset($data['Ds_MerchantCode']) && (string) $data['Ds_MerchantCode'] !== $this->redsys->merchantCode()) {
            return $this->reject($purchase, 'redsys', 'merchant_mismatch');
        }

        if (! $this->redsys->isResponseSuccessful($data['Ds_Response'] ?? null)) {
            $failed = $this->transitionToFailed($purchase, ['status' => 'failed', 'gateway_metadata' => $data]);
            Log::warning("PackPaymentConfirmer: Redsys recusou o pagamento da compra #{$purchase->id}.");

            return $failed ? self::FAILED : self::ALREADY_PROCESSED;
        }

        $gatewayName = (($data['Ds_PayMethod'] ?? 'T') === 'z') ? 'redsys_bizum' : 'redsys_card';
        $transitioned = $this->transitionToPaid($purchase, function (PackPurchase $locked) use ($gatewayName, $order, $data) {
            $locked->markAsPaidViaGateway($gatewayName, $order, $data);

            if ($this->isSubscriptionPurchase($locked)) {
                $locked->update(['subscription_status' => 'active']);
            }
        });

        if (! $transitioned) {
            return self::ALREADY_PROCESSED;
        }

        Log::info("PackPaymentConfirmer: pagamento Redsys confirmado ({$gatewayName}) para a compra #{$purchase->id}.");

        $this->issueInvoice($purchase, 'Redsys');
        $this->provisionFirstProduction($purchase);

        return self::PAID;
    }

    protected function stripeSessionMismatch(PackPurchase $purchase, object $session): ?string
    {
        $sessionId = (string) ($session->id ?? '');
        if ($sessionId === '' || ! hash_equals((string) $purchase->stripe_checkout_session_id, $sessionId)) {
            return 'session_mismatch';
        }

        $metadata = $session->metadata ?? null;
        if ((string) ($metadata->pack_purchase_id ?? '') !== (string) $purchase->id) {
            return 'purchase_mismatch';
        }

        if (isset($metadata->tenant_id) && (string) $metadata->tenant_id !== (string) $purchase->tenant_id) {
            return 'tenant_mismatch';
        }

        return null;
    }

    /**
     * Executa $apply só se a compra ainda estiver pending (linha travada). Devolve se houve transição.
     */
    protected function transitionToPaid(PackPurchase $purchase, callable $apply): bool
    {
        $transitioned = DB::transaction(function () use ($purchase, $apply): bool {
            $locked = PackPurchase::withoutGlobalScope('tenant')->lockForUpdate()->find($purchase->id);

            if (! $locked || $locked->status !== 'pending') {
                return false;
            }

            $apply($locked);

            return true;
        });

        $purchase->refresh();

        return $transitioned;
    }

    protected function transitionToFailed(PackPurchase $purchase, array $attributes): bool
    {
        $transitioned = DB::transaction(function () use ($purchase, $attributes): bool {
            $locked = PackPurchase::withoutGlobalScope('tenant')->lockForUpdate()->find($purchase->id);

            if (! $locked || $locked->status !== 'pending') {
                return false;
            }

            $locked->update($attributes);

            return true;
        });

        $purchase->refresh();

        return $transitioned;
    }

    protected function isSubscriptionPurchase(PackPurchase $purchase): bool
    {
        return $purchase->billing_mode === 'subscription' || $purchase->pack?->billing_type === 'subscription';
    }

    protected function reject(PackPurchase $purchase, string $gateway, string $reason): string
    {
        Log::warning("PackPaymentConfirmer: confirmação {$gateway} recusada para a compra #{$purchase->id} ({$reason}).");

        return 'rejected:'.$reason;
    }

    protected function storeSplitDepositPaymentMethod(PackPurchase $purchase, string $paymentIntentId): void
    {
        $updateData = ['deposit_paid_at' => now()];

        if (config('services.stripe.secret') && $paymentIntentId !== '') {
            try {
                $paymentIntent = (new StripeClient(config('services.stripe.secret')))->paymentIntents->retrieve($paymentIntentId);
                if (! empty($paymentIntent->payment_method)) {
                    $updateData['stripe_payment_method_id'] = is_string($paymentIntent->payment_method)
                        ? $paymentIntent->payment_method
                        : $paymentIntent->payment_method->id;
                }
            } catch (Throwable $e) {
                Log::warning('PackPaymentConfirmer: não foi possível recuperar o payment_method do sinal 50/50: '.get_class($e));
            }
        }

        $purchase->update($updateData);
    }

    protected function issueInvoice(PackPurchase $purchase, string $gateway): void
    {
        try {
            app(InvoiceService::class)->issueAndNotify($purchase);
        } catch (Throwable $e) {
            Log::error("PackPaymentConfirmer ({$gateway}): erro ao emitir fatura da compra #{$purchase->id}: ".get_class($e));
        }
    }

    protected function provisionFirstProduction(PackPurchase $purchase): void
    {
        $adminUser = $purchase->tenant?->users()->first();

        if ($adminUser && $purchase->productions()->count() === 0) {
            $purchase->productions()->create([
                'tenant_id' => $purchase->tenant_id,
                'created_by' => $adminUser->id,
                'cliente_id' => $purchase->cliente_id,
                'title' => 'Produção #1 - '.($purchase->pack->name ?? 'Pacote de Vídeos'),
                'status' => 'draft',
            ]);
        }
    }
}
