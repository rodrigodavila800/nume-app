<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\PackPurchase;
use App\Models\Quote;
use App\Services\Payment\PackCheckoutDispatcher;
use App\Services\Payment\PaymentMethodAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuoteApprovalController extends Controller
{
    /**
     * Exibe a proposta pública para aprovação do cliente via token.
     */
    public function show(Request $request, string $token): View
    {
        $quote = Quote::withoutGlobalScope('tenant')
            ->where('approval_token', $token)
            ->firstOrFail();

        $tenant = $quote->tenant;

        if ($quote->status === 'accepted') {
            return view('quotes.public.accepted', compact('quote', 'tenant'));
        }

        if ($quote->status === 'rejected') {
            return view('quotes.public.rejected', compact('quote', 'tenant'));
        }

        if ($quote->isExpired()) {
            return view('quotes.public.expired', compact('quote', 'tenant'));
        }

        $availability = PaymentMethodAvailability::forTenant($tenant);
        $activeMethods = $availability->getActiveMethods();

        return view('quotes.public.show', compact('quote', 'tenant', 'activeMethods'));
    }

    /**
     * Cliente recusa expressamente a proposta através do link público.
     */
    public function reject(Request $request, string $token): RedirectResponse
    {
        $quote = Quote::withoutGlobalScope('tenant')
            ->where('approval_token', $token)
            ->firstOrFail();

        if ($quote->status === 'accepted') {
            return redirect()->route('quotes.public.show', $token)
                ->with('error', __('Este orçamento já foi aprovado anteriormente.'));
        }

        $quote->update(['status' => 'rejected']);

        return redirect()->route('quotes.public.show', $token)
            ->with('info', __('Proposta recusada com sucesso.'));
    }

    /**
     * Cliente aprova o orçamento e inicia o pagamento do sinal pelo gateway escolhido.
     */
    public function approve(Request $request, string $token, PackCheckoutDispatcher $dispatcher): mixed
    {
        $quote = Quote::withoutGlobalScope('tenant')
            ->where('approval_token', $token)
            ->firstOrFail();

        if ($quote->status === 'accepted') {
            return redirect()->route('quotes.public.show', $token)
                ->with('info', __('Este orçamento já foi aprovado e confirmado anteriormente.'));
        }

        if ($quote->isExpired()) {
            return redirect()->route('quotes.public.show', $token)
                ->with('error', __('Este orçamento expirou e não pode mais ser aprovado.'));
        }

        $validated = $request->validate([
            'payment_method' => 'required|string|in:stripe,redsys_card,bizum,bank_transfer',
        ]);

        $method = $validated['payment_method'];
        $tenant = $quote->tenant;
        $availability = PaymentMethodAvailability::forTenant($tenant);

        if (! $availability->isMethodActive($method)) {
            return back()->with('error', __('O método de pagamento selecionado não está disponível no momento.'));
        }

        $clientEmail = trim($quote->client_email ?: '');
        if (empty($clientEmail)) {
            return back()->with('error', __('O orçamento não possui e-mail de contato cadastrado para envio das informações de cobrança.'));
        }

        // Localiza ou provisiona a conta do cliente para o tenant da produtora
        $cliente = Cliente::withoutGlobalScope('tenant')
            ->where('tenant_id', $quote->tenant_id)
            ->where('email', $clientEmail)
            ->first();

        if (! $cliente) {
            $cliente = Cliente::withoutGlobalScope('tenant')->create([
                'tenant_id' => $quote->tenant_id,
                'name' => $quote->client_name ?: 'Cliente',
                'email' => $clientEmail,
                'password' => bcrypt(Str::random(16)),
            ]);
        }

        $amountToPay = (float) ($quote->deposit_amount > 0 ? $quote->deposit_amount : $quote->total_amount);
        $billingMode = ($quote->balance_amount > 0) ? 'split_50_50' : 'full_upfront';
        $depositAmount = $quote->deposit_amount > 0 ? $quote->deposit_amount : $quote->total_amount;
        $balanceAmount = $quote->balance_amount > 0 ? $quote->balance_amount : 0.00;
        $applicationFee = round(((float) $quote->subtotal) * 0.10, 2);
        $platformFeeStatus = in_array($method, ['redsys_card', 'bizum', 'bank_transfer'], true) ? 'pending_billing' : 'not_applicable';

        $purchase = PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $quote->tenant_id,
            'cliente_id' => $cliente->id,
            'pack_id' => null,
            'quote_id' => $quote->id,
            'amount' => $amountToPay,
            'subtotal' => $quote->subtotal,
            'tax_rate' => $quote->tax_rate,
            'tax_amount' => $quote->tax_amount,
            'total_amount' => $quote->total_amount,
            'deposit_amount' => $depositAmount,
            'balance_amount' => $balanceAmount,
            'billing_mode' => $billingMode,
            'status' => 'pending',
            'payment_gateway' => $method,
            'application_fee_amount' => $applicationFee,
            'platform_fee_status' => $platformFeeStatus,
            // Quantidade de vídeos do próprio orçamento: vira a cota do cliente e aparece na fatura.
            // Orçamento de anúncios é serviço (criativos e gestão): não gera cota de vídeos.
            'video_quantity' => $quote->isAds() ? 0 : max(1, (int) ($quote->inputs['videos'] ?? 1)),
        ]);

        return match ($method) {
            'stripe' => $dispatcher->dispatchStripe(
                $purchase,
                route('quotes.public.show', ['token' => $token, 'success' => 1]),
                route('quotes.public.show', ['token' => $token, 'canceled' => 1]),
                $clientEmail
            ),
            'redsys_card', 'bizum' => $dispatcher->dispatchRedsys($purchase, $method),
            'bank_transfer' => $dispatcher->dispatchBankTransfer($purchase),
            default => back()->with('error', __('Método inválido.')),
        };
    }
}
