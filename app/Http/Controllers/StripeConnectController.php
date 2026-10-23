<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\StripeClient;

class StripeConnectController extends Controller
{
    public function index(Request $request): View
    {
        return view('stripe-connect.index', ['tenant' => $request->user()->tenant]);
    }

    public function connect(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $tenant = $request->user()->tenant;

        if (!config('services.stripe.secret')) {
            return back()->with('error', 'O Stripe ainda não foi configurado na plataforma. Acesse Configurações > Gateways para cadastrar suas credenciais criptografadas.');
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $email = $tenant->primary_contact_email ?: $request->user()->email;

            if (!$tenant->stripe_account_id) {
                try {
                    // Stripe Accounts v2 API (padrão oficial moderno para novas integrações Connect)
                    $account = @$stripe->v2->core->accounts->create([
                        'contact_email' => $email,
                        'identity' => [
                            'country' => 'ES',
                        ],
                        'configuration' => [
                            'recipient' => [
                                'capabilities' => [
                                    'stripe_balance' => [
                                        'stripe_transfers' => [
                                            'requested' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'dashboard' => 'express',
                        'defaults' => [
                            'responsibilities' => [
                                'fees_collector' => 'application',
                                'losses_collector' => 'application',
                            ],
                        ],
                        'metadata' => [
                            'tenant_id' => (string) $tenant->id,
                        ],
                    ]);
                } catch (\Throwable $v2Error) {
                    \Illuminate\Support\Facades\Log::info('Fallback para Stripe Accounts v1: ' . $v2Error->getMessage());
                    // Fallback para Accounts v1 caso a conta da plataforma utilize v1 legado
                    $account = @$stripe->accounts->create([
                        'type' => 'express',
                        'email' => $email,
                        'business_type' => 'company',
                        'metadata' => ['tenant_id' => (string) $tenant->id],
                    ]);
                }

                $tenant->update([
                    'stripe_account_id' => $account->id,
                    'stripe_account_status' => 'onboarding',
                ]);
            }

            $accountLink = @$stripe->accountLinks->create([
                'account' => $tenant->stripe_account_id,
                'refresh_url' => route('stripe-connect.connect'),
                'return_url' => route('stripe-connect.return'),
                'type' => 'account_onboarding',
            ]);

            return redirect($accountLink->url);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao conectar conta Stripe Connect: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('stripe-connect.index')
                ->with('error', 'Não foi possível iniciar a conexão com o Stripe: ' . $e->getMessage());
        }
    }

    public function return(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        try {
            if ($tenant->stripe_account_id && config('services.stripe.secret')) {
                $stripe = new StripeClient(config('services.stripe.secret'));
                $account = @$stripe->accounts->retrieve($tenant->stripe_account_id);

                $tenant->update([
                    'charges_enabled' => $account->charges_enabled,
                    'payouts_enabled' => $account->payouts_enabled,
                    'details_submitted' => $account->details_submitted,
                    'stripe_account_status' => $account->charges_enabled ? 'active' : 'onboarding',
                ]);
            }

            return redirect()->route('stripe-connect.index')
                ->with('success', 'Status da conta Stripe atualizado!');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao atualizar status da conta Stripe: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
            ]);

            return redirect()->route('stripe-connect.index')
                ->with('error', 'Não foi possível verificar o status no Stripe: ' . $e->getMessage());
        }
    }
}
