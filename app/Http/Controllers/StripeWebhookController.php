<?php

namespace App\Http\Controllers;

use App\Models\StripeCustomer;
use App\Models\Tenant;
use App\Models\PackPurchase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                $secret
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response('Assinatura ou payload inválido', 400);
        }

        $wasInserted = DB::transaction(function () use ($event): bool {
            $wasInserted = DB::table('stripe_webhook_events')->insertOrIgnore([
                'stripe_event_id' => $event->id,
                'type' => $event->type,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! $wasInserted) {
                return false;
            }

            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                'invoice.payment_failed' => $this->handlePaymentFailed($event->data->object),
                default => null,
            };

            return true;
        });

        return response($wasInserted ? 'Webhook processado' : 'Webhook jÃ¡ processado', 200);
    }

    private function handleCheckoutCompleted(object $session): void
    {
        $type = $session->metadata->type ?? 'tenant_subscription';

        if ($type === 'pack_purchase') {
            $this->handlePackPurchaseCompleted($session);

            return;
        }

        $tenant = Tenant::find($session->metadata->tenant_id ?? null);

        if (!$tenant) {
            return;
        }

        $subscription = null;

        if ($session->subscription && config('services.stripe.secret')) {
            $subscription = (new StripeClient(config('services.stripe.secret')))
                ->subscriptions
                ->retrieve($session->subscription);
        }

        $periodEnd = $subscription?->current_period_end
            ? Carbon::createFromTimestamp($subscription->current_period_end)
            : null;

        StripeCustomer::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'stripe_customer_id' => $session->customer,
                'stripe_subscription_id' => $session->subscription,
                'subscription_status' => $subscription?->status ?? 'active',
                'current_period_start' => $subscription?->current_period_start
                    ? Carbon::createFromTimestamp($subscription->current_period_start)
                    : null,
                'current_period_end' => $periodEnd,
            ]
        );

        $tenant->update(array_filter([
            'subscribed_at' => $tenant->subscribed_at ?? now(),
            'subscription_expires_at' => $periodEnd,
        ], static fn (mixed $value): bool => $value !== null));
    }

    private function handlePackPurchaseCompleted(object $session): void
    {
        $purchase = PackPurchase::withoutGlobalScope('tenant')
            ->find($session->metadata->pack_purchase_id ?? null);

        if (!$purchase || $purchase->isPaid()) {
            return;
        }

        $paymentIntentId = (string) $session->payment_intent;
        $purchase->markAsPaid($paymentIntentId);

        if ($purchase->isSplit5050()) {
            $updateData = ['deposit_paid_at' => now()];

            if (config('services.stripe.secret')) {
                try {
                    $stripe = new StripeClient(config('services.stripe.secret'));
                    $pi = $stripe->paymentIntents->retrieve($paymentIntentId);
                    if (!empty($pi->payment_method)) {
                        $updateData['stripe_payment_method_id'] = is_string($pi->payment_method) ? $pi->payment_method : $pi->payment_method->id;
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Stripe Webhook: Não foi possível recuperar payment_method para 50/50: " . $e->getMessage());
                }
            }

            $purchase->update($updateData);
        }

        try {
            app(\App\Services\Billing\InvoiceService::class)->issueAndNotify($purchase);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Stripe Webhook: Erro ao gerar fatura PDF ou enviar e-mail: " . $e->getMessage());
        }
    }

    private function handleSubscriptionUpdated(object $subscription): void
    {
        $stripeCustomer = StripeCustomer::where('stripe_subscription_id', $subscription->id)->first();

        if (!$stripeCustomer) {
            return;
        }

        $periodEnd = Carbon::createFromTimestamp($subscription->current_period_end);

        $stripeCustomer->update([
            'subscription_status' => $subscription->status,
            'current_period_start' => Carbon::createFromTimestamp($subscription->current_period_start),
            'current_period_end' => $periodEnd,
        ]);

        if ($subscription->status === 'active') {
            $stripeCustomer->tenant->update(['subscription_expires_at' => $periodEnd]);
        }
    }

    private function handleSubscriptionDeleted(object $subscription): void
    {
        $stripeCustomer = StripeCustomer::where('stripe_subscription_id', $subscription->id)->first();

        if (! $stripeCustomer) {
            return;
        }

        $periodEnd = $subscription->current_period_end
            ? Carbon::createFromTimestamp($subscription->current_period_end)
            : $stripeCustomer->current_period_end;

        $stripeCustomer->update([
            'subscription_status' => 'canceled',
            'canceled_at' => now(),
            'current_period_end' => $periodEnd,
        ]);

        if ($periodEnd) {
            $stripeCustomer->tenant->update(['subscription_expires_at' => $periodEnd]);
        }
    }

    private function handlePaymentFailed(object $invoice): void
    {
        if (!empty($invoice->subscription)) {
            StripeCustomer::where('stripe_subscription_id', $invoice->subscription)
                ->update(['subscription_status' => 'past_due']);
        }
    }
}
