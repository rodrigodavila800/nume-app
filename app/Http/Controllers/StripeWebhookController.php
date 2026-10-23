<?php

namespace App\Http\Controllers;

use App\Models\StripeCustomer;
use App\Models\Tenant;
use App\Models\PackPurchase;
use App\Services\Payment\PackPaymentConfirmer;
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

            // O checkout não fixa payment_method_types: valem os métodos habilitados no painel
            // da Stripe, que podem ser assíncronos (ex.: SEPA). Por isso os eventos async_payment_*.
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
                'checkout.session.async_payment_succeeded' => $this->handlePackPurchaseCompleted($event->data->object),
                'checkout.session.async_payment_failed' => $this->handlePackPurchaseAsyncFailed($event->data->object),
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
        if ($purchase = $this->packPurchaseFor($session)) {
            app(PackPaymentConfirmer::class)->confirmStripeSession($purchase, $session);
        }
    }

    private function handlePackPurchaseAsyncFailed(object $session): void
    {
        if ($purchase = $this->packPurchaseFor($session)) {
            app(PackPaymentConfirmer::class)->failStripeSession($purchase, $session);
        }
    }

    /**
     * Compra apontada pelo metadata; o PackPaymentConfirmer confere sessão, tenant e valores.
     */
    private function packPurchaseFor(object $session): ?PackPurchase
    {
        if (($session->metadata->type ?? null) !== 'pack_purchase') {
            return null;
        }

        return PackPurchase::withoutGlobalScope('tenant')
            ->find($session->metadata->pack_purchase_id ?? null);
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
