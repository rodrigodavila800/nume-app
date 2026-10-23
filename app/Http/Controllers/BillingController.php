<?php

namespace App\Http\Controllers;

use App\Models\StripeCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\StripeClient;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $request->user()->tenant;

        return view('billing.index', [
            'tenant' => $tenant,
            'stripeCustomer' => $tenant->stripeCustomer,
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if (!config('services.stripe.secret') || !config('services.stripe.price_id')) {
            return back()->with('error', 'O Stripe ainda não foi configurado na plataforma. Acesse Configurações > Gateways para cadastrar suas credenciais criptografadas.');
        }

        $stripe = new StripeClient(config('services.stripe.secret'));

        $customerId = $tenant->stripeCustomer?->stripe_customer_id;

        if (! $customerId) {
            $customer = $stripe->customers->create([
                'name' => $tenant->name,
                'email' => $tenant->primary_contact_email,
                'metadata' => ['tenant_id' => $tenant->id],
            ], [
                'idempotency_key' => "tenant-customer-{$tenant->id}",
            ]);
            $customerId = $customer->id;

            StripeCustomer::updateOrCreate(
                ['tenant_id' => $tenant->id],
                ['stripe_customer_id' => $customerId],
            );
        }

        $session = $stripe->checkout->sessions->create([
            'customer' => $customerId,
            'mode' => 'subscription',
            'line_items' => [[
                'price' => config('services.stripe.price_id'),
                'quantity' => 1,
            ]],
            'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing.cancel'),
            'metadata' => ['type' => 'tenant_subscription', 'tenant_id' => $tenant->id],
        ]);

        return redirect($session->url);
    }

    public function success(): View
    {
        return view('billing.success');
    }

    public function cancel(): View
    {
        return view('billing.cancel');
    }
}
