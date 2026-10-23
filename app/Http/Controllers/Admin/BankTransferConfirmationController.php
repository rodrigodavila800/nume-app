<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackPurchase;
use App\Models\User;
use App\Services\Payment\PackPaymentConfirmer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BankTransferConfirmationController extends Controller
{
    /**
     * Confirma manualmente o recebimento de uma transferência bancária.
     * Restrito a admin do mesmo tenant da compra.
     */
    public function confirm(
        Request $request,
        PackPurchase $purchase,
        PackPaymentConfirmer $confirmer
    ): RedirectResponse {
        /** @var User|null $user */
        $user = $request->user();

        // 1. Autorização estrita: admin do MESMO tenant
        if (! $user || ! $user->isAdmin() || (int) $user->tenant_id !== (int) $purchase->tenant_id) {
            abort(403, 'Apenas administradores da produtora podem confirmar transferências bancárias.');
        }

        // 2. Validações de estado da compra
        if (! $purchase->isBankTransfer()) {
            return back()->with('error', 'Esta compra não é do tipo transferência bancária.');
        }

        if ($purchase->isPaid()) {
            return back()->with('info', 'Esta transferência já foi confirmada anteriormente.');
        }

        if ($purchase->isExpired()) {
            return back()->with('error', 'Esta solicitação de transferência já expirou.');
        }

        try {
            $confirmer->confirmBankTransfer($purchase, $user);

            return back()->with('success', "Transferência bancária #{$purchase->id} confirmada com sucesso! Fatura emitida e produção inicial criada.");
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Falha ao confirmar transferência bancária: '.$e->getMessage());
        }
    }
}
