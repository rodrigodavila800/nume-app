<?php

namespace App\Services\Payment;

use App\Models\PackPurchase;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use Exception;

/**
 * Gerencia a liquidação do saldo remanescente (50%) de pacotes com modelo 50/50
 * ao atingir o momento da aprovação do corte final pelo cliente.
 */
class PackBalancePaymentService
{
    /**
     * Cobra o saldo de 50% pendente do pacote.
     *
     * @param PackPurchase $purchase
     * @param string|null $newPaymentMethodId Opcional: novo cartão informado pelo cliente
     * @return array ['success' => bool, 'message' => string, 'payment_intent_id' => string|null]
     */
    public function chargeBalance(PackPurchase $purchase, ?string $newPaymentMethodId = null): array
    {
        if (! $purchase->isSplit5050()) {
            return [
                'success' => true,
                'message' => __('Este pacote não possui saldo pendente de divisão 50/50.'),
                'payment_intent_id' => null,
            ];
        }

        if ($purchase->isBalancePaid()) {
            return [
                'success' => true,
                'message' => __('O saldo deste pacote já foi quitado anteriormente.'),
                'payment_intent_id' => $purchase->stripe_balance_payment_intent_id,
            ];
        }

        $balanceAmount = (float) $purchase->balance_amount;
        if ($balanceAmount <= 0) {
            $purchase->update([
                'balance_status' => 'paid',
                'balance_paid_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => __('Saldo liquidado com sucesso.'),
                'payment_intent_id' => null,
            ];
        }

        $paymentMethodId = $newPaymentMethodId ?: $purchase->stripe_payment_method_id;
        $stripeSecret = config('services.stripe.secret');

        // Modo simulado SÓ na suíte de testes. Fora dela, sem chave válida, nada é cobrado nem
        // marcado como pago (antes, chave vazia ou "mock" quitava o saldo sem cobrança).
        if (! app()->environment('testing') && ! $this->isUsableStripeSecret($stripeSecret)) {
            Log::error("PackBalancePaymentService: cobrança do saldo da compra #{$purchase->id} bloqueada: chave da Stripe ausente ou inválida (STRIPE_SECRET).");

            return [
                'success' => false,
                'message' => __('O pagamento do saldo está temporariamente indisponível. A produtora já foi avisada; tente novamente mais tarde.'),
                'payment_intent_id' => null,
            ];
        }

        if (app()->environment('testing')) {
            $mockPiId = 'pi_balance_mock_' . Str::random(16);
            $purchase->markBalancePaid($mockPiId);

            return [
                'success' => true,
                'message' => __('Saldo de :amount :currency quitado com sucesso (Modo Simulado)!', [
                    'amount' => number_format($balanceAmount, 2, ',', '.'),
                    'currency' => strtoupper($purchase->pack?->currency ?? 'EUR'),
                ]),
                'payment_intent_id' => $mockPiId,
            ];
        }

        try {
            $stripe = new StripeClient($stripeSecret);
            $currency = strtolower($purchase->pack?->currency ?? 'eur');

            $params = [
                'amount' => (int) round($balanceAmount * 100),
                'currency' => $currency,
                'confirm' => true,
                'off_session' => true,
                'metadata' => [
                    'type' => 'pack_balance_payment',
                    'pack_purchase_id' => (string) $purchase->id,
                    'tenant_id' => (string) $purchase->tenant_id,
                    'cliente_id' => (string) $purchase->cliente_id,
                ],
            ];

            if ($paymentMethodId) {
                $params['payment_method'] = $paymentMethodId;
            }

            // Chave de idempotência para garantir que clique duplo nunca duplique o débito
            $idempotencyKey = 'pack_balance_' . $purchase->id . '_' . ((int) round($balanceAmount * 100));

            $paymentIntent = $stripe->paymentIntents->create($params, [
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($paymentIntent->status === 'succeeded') {
                $purchase->markBalancePaid($paymentIntent->id);

                return [
                    'success' => true,
                    'message' => __('Saldo quitado com sucesso! O corte final foi liberado sem restrições.'),
                    'payment_intent_id' => $paymentIntent->id,
                ];
            }

            $purchase->update(['balance_status' => 'failed']);

            return [
                'success' => false,
                'message' => __('O pagamento do saldo não foi aprovado pelo gateway. Status: :status', [
                    'status' => $paymentIntent->status,
                ]),
                'payment_intent_id' => $paymentIntent->id,
            ];
        } catch (Exception $e) {
            Log::error('Falha ao debitar saldo do pacote 50/50: ' . $e->getMessage(), [
                'pack_purchase_id' => $purchase->id,
            ]);

            $purchase->update(['balance_status' => 'failed']);

            return [
                'success' => false,
                'message' => __('Erro ao processar cobrança do saldo: :error', [
                    'error' => $e->getMessage(),
                ]),
                'payment_intent_id' => null,
            ];
        }
    }

    /**
     * Chave secreta ou restrita da Stripe (sk_/rk_), sem marcadores de simulação.
     */
    protected function isUsableStripeSecret(?string $secret): bool
    {
        return is_string($secret)
            && preg_match('/^(sk|rk)_(live|test)_[A-Za-z0-9]+$/', $secret) === 1
            && ! str_contains(strtolower($secret), 'mock');
    }
}
