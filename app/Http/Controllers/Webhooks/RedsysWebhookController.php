<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PackPurchase;
use App\Services\Payment\PackPaymentConfirmer;
use App\Services\Payment\RedsysService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class RedsysWebhookController extends Controller
{
    protected RedsysService $redsysService;

    public function __construct(RedsysService $redsysService)
    {
        $this->redsysService = $redsysService;
    }

    /**
     * Processa a notificação HTTP assíncrona enviada pelo BBVA / Redsys.
     */
    public function handle(Request $request, PackPaymentConfirmer $confirmer): Response
    {
        $parameters = $request->input('Ds_MerchantParameters');
        $signature = $request->input('Ds_Signature');

        if (empty($parameters) || empty($signature)) {
            Log::warning('Redsys Webhook: Requisição recebida sem parâmetros ou assinatura válidos.');

            return response('Parâmetros ausentes', 400);
        }

        // Validação criptográfica da assinatura HMAC SHA-256 (Proteção contra adulteração)
        if (! $this->redsysService->verifySignature($parameters, $signature)) {
            Log::error('Redsys Webhook: Assinatura inválida detectada! Possível tentativa de fraude/tampering.');

            return response('Assinatura inválida', 400);
        }

        $data = $this->redsysService->decodeParameters($parameters);
        $orderNumber = $data['Ds_Order'] ?? $data['DS_MERCHANT_ORDER'] ?? null;

        if (empty($orderNumber)) {
            Log::warning('Redsys Webhook: Número do pedido ausente nos parâmetros decodificados.');

            return response('Número do pedido ausente', 400);
        }

        $purchase = PackPurchase::withoutGlobalScope('tenant')
            ->where('gateway_order_reference', $orderNumber)
            ->first();

        if (! $purchase) {
            Log::warning("Redsys Webhook: PackPurchase não encontrado para o pedido: {$orderNumber}");

            return response('Pedido não encontrado', 200); // Responde 200 para evitar retries desnecessários
        }

        // Valores, moeda, comerciante, código de resposta e idempotência: PackPaymentConfirmer.
        $confirmer->confirmRedsys($purchase, $data);

        return response('OK', 200);
    }
}
