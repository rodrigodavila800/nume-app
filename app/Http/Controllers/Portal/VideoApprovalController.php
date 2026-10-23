<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Services\Billing\InvoiceService;
use App\Services\Notification\SofiVideoNotificationService;
use App\Services\Payment\PackBalancePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VideoApprovalController extends Controller
{
    public function __construct(
        protected PackBalancePaymentService $balancePaymentService,
        protected InvoiceService $invoiceService,
        protected SofiVideoNotificationService $notificationService
    ) {}

    /**
     * Aprova o corte final e liquida o saldo de 50% pendente (caso aplicável).
     */
    public function approveAndRelease(Request $request, Video $video): RedirectResponse
    {
        $cliente = auth('cliente')->user();
        abort_unless($video->production?->cliente_id === $cliente->id, 403, __('Acesso não autorizado a este vídeo.'));

        $purchase = $video->production?->packPurchase;
        if (! $purchase) {
            $purchase = $cliente->packPurchases()
                ->where('status', 'paid')
                ->latest()
                ->first();
        }

        // Se o ativo estiver em custódia (modelo 50/50 com saldo pendente)
        if ($purchase && $purchase->isAssetLocked()) {
            $paymentResult = $this->balancePaymentService->chargeBalance(
                purchase: $purchase,
                newPaymentMethodId: $request->input('payment_method_id')
            );

            if (! $paymentResult['success']) {
                return redirect()->back()
                    ->with('error', $paymentResult['message']);
            }

            $video->markAsReady();

            // 1. Emissão da fatura oficial da liquidação com envio em PDF para o cliente
            try {
                $this->invoiceService->issueAndNotify($purchase);
            } catch (\Throwable $e) {
                Log::warning('[InvoiceService] Falha ao emitir fatura do saldo: '.get_class($e));
            }

            // 2. Notificação reversa para a equipe da produtora (editor e administradores do tenant)
            try {
                $this->notificationService->notifyProducerVideoApproved($video, $purchase);
            } catch (\Throwable $e) {
                Log::warning('[SofiNotification] Falha ao alertar produtora sobre aprovação: '.get_class($e));
            }

            return redirect()->back()
                ->with('success', __('Corte final aprovado e saldo de :amount :currency quitado com sucesso! Os arquivos em alta resolução e o disparo nas redes foram liberados, e sua fatura fiscal foi enviada por e-mail.', [
                    'amount' => number_format($purchase->balance_amount, 2, ',', '.'),
                    'currency' => strtoupper($purchase->pack?->currency ?? 'EUR'),
                ]));
        }

        // Caso padrão (100% upfront já quitado)
        $video->markAsReady();

        try {
            $this->notificationService->notifyProducerVideoApproved($video, $purchase);
        } catch (\Throwable $e) {
            Log::warning('[SofiNotification] Falha ao alertar produtora sobre aprovação: '.get_class($e));
        }

        return redirect()->back()
            ->with('success', __('Corte final aprovado com sucesso! O vídeo está liberado para download e publicação.'));
    }
}
