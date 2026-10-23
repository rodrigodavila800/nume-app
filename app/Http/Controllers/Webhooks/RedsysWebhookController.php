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
    /**
     * Processa a notificação HTTP assíncrona enviada pelo BBVA / Redsys.
     * Fluxo estrito de segurança multi-tenant:
     * 1. Extração segura de Ds_Order dos parâmetros (sem validar ainda).
     * 2. Localização de PackPurchase no banco (sem global scope).
     * 3. Obtenção do Tenant da compra e instanciação de RedsysService::forTenant($tenant).
     * 4. Validação HMAC SHA-256 usando estritamente a chave daquele tenant.
     * 5. Se o pedido não existir no banco mas a assinatura era válida, retorna 200 (evita retries).
     * 6. Validação de Ds_MerchantCode contra o FUC cadastrado para aquele tenant.
     * 7. Só então chama PackPaymentConfirmer.
     */
    public function handle(Request $request, PackPaymentConfirmer $confirmer): Response
    {
        $parameters = (string) $request->input('Ds_MerchantParameters', '');
        $signature = (string) $request->input('Ds_Signature', '');

        if (empty($parameters) || empty($signature)) {
            Log::warning('Redsys Webhook: Requisição recebida sem parâmetros ou assinatura válidos.');

            return response('Parâmetros ausentes', 400);
        }

        // 1. Extrai o número do pedido sem confiar em nenhum dado não autenticado
        $orderNumber = RedsysService::extractOrderFromParameters($parameters);

        if (empty($orderNumber)) {
            Log::warning('Redsys Webhook: Número do pedido ausente ou inválido nos parâmetros.');

            return response('Número do pedido ausente', 400);
        }

        // 2. Localiza a compra no banco para identificar o tenant dono da transação
        $purchase = PackPurchase::withoutGlobalScope('tenant')
            ->where('gateway_order_reference', $orderNumber)
            ->first();

        $tenant = $purchase?->tenant;
        $redsysService = RedsysService::forTenant($tenant);

        // 3. Validação criptográfica da assinatura HMAC SHA-256 com a chave DAQUELE tenant
        if (! $redsysService->verifySignature($parameters, $signature)) {
            Log::error("Redsys Webhook: Assinatura inválida detectada para o tenant [{$tenant?->id}] no pedido [{$orderNumber}]! Possível tentativa de fraude/tampering.");

            return response('Assinatura inválida', 400);
        }

        // 4. Se a assinatura for válida mas a compra não existir no banco, responde 200 para evitar retries
        if (! $purchase) {
            Log::warning("Redsys Webhook: PackPurchase não encontrado para o pedido: {$orderNumber}");

            return response('Pedido não encontrado', 200);
        }

        // 5. Decodificação completa dos parâmetros autenticados
        $data = $redsysService->decodeParameters($parameters);

        // 6. Validação do código do comerciante (FUC) daquele tenant
        $receivedMerchantCode = trim((string) ($data['Ds_MerchantCode'] ?? $data['DS_MERCHANT_MERCHANTCODE'] ?? ''));
        if ($receivedMerchantCode !== '' && $receivedMerchantCode !== $redsysService->merchantCode()) {
            Log::error("Redsys Webhook: Ds_MerchantCode divergente para o tenant [{$tenant?->id}]. Esperado: [{$redsysService->merchantCode()}], Recebido: [{$receivedMerchantCode}].");

            return response('Comerciante inválido', 200); // 200 para a Redsys não reenviar em loop uma notificação recusada
        }

        // 7. Confirmação segura no PackPaymentConfirmer (valores, moeda, resposta e idempotência)
        $confirmer->confirmRedsys($purchase, $data);

        return response('OK', 200);
    }
}
