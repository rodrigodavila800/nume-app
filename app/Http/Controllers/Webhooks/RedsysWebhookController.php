<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PackPurchase;
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
    public function handle(Request $request): Response
    {
        $parameters = $request->input('Ds_MerchantParameters');
        $signature = $request->input('Ds_Signature');

        if (empty($parameters) || empty($signature)) {
            Log::warning('Redsys Webhook: Requisição recebida sem parâmetros ou assinatura válidos.');
            return response('Parâmetros ausentes', 400);
        }

        // Validação criptográfica da assinatura HMAC SHA-256 (Proteção contra adulteração)
        if (! $this->redsysService->verifySignature($parameters, $signature)) {
            Log::error('Redsys Webhook: Assinatura inválida detectada! Possível tentativa de fraude/tampering.', [
                'parameters' => $parameters,
                'signature' => $signature,
            ]);
            return response('Assinatura inválida', 400);
        }

        $data = $this->redsysService->decodeParameters($parameters);
        $orderNumber = $data['Ds_Order'] ?? $data['DS_MERCHANT_ORDER'] ?? null;
        $responseCode = $data['Ds_Response'] ?? null;
        $payMethod = $data['Ds_PayMethod'] ?? 'T';

        if (empty($orderNumber)) {
            Log::warning('Redsys Webhook: Número do pedido ausente nos parâmetros decodificados.', $data);
            return response('Número do pedido ausente', 400);
        }

        $purchase = PackPurchase::withoutGlobalScope('tenant')
            ->where('gateway_order_reference', $orderNumber)
            ->first();

        if (! $purchase) {
            Log::warning("Redsys Webhook: PackPurchase não encontrado para o pedido: {$orderNumber}");
            return response('Pedido não encontrado', 200); // Responde 200 para evitar retries desnecessários
        }

        // Idempotência: Se já foi pago, evita processar novamente
        if ($purchase->isPaid()) {
            return response('OK', 200);
        }

        $isSuccess = $this->redsysService->isResponseSuccessful($responseCode);

        if ($isSuccess) {
            $gatewayName = ($payMethod === 'z') ? 'redsys_bizum' : 'redsys_card';
            $purchase->markAsPaidViaGateway($gatewayName, (string) $orderNumber, $data);

            Log::info("Redsys Webhook: Pagamento aprovado via {$gatewayName} para o pedido {$orderNumber} (Purchase #{$purchase->id}).");

            // Emissão automática da Factura legal (Agencia Tributaria) e notificação por e-mail
            try {
                app(\App\Services\Billing\InvoiceService::class)->issueAndNotify($purchase);
            } catch (\Throwable $e) {
                Log::error("Redsys Webhook: Erro ao gerar fatura PDF ou enviar e-mail: " . $e->getMessage());
            }

            // Provisionamento inicial de produção se houver usuário da produtora disponível
            $adminUser = $purchase->tenant->users()->first();
            if ($adminUser && $purchase->productions()->count() === 0) {
                $purchase->productions()->create([
                    'tenant_id' => $purchase->tenant_id,
                    'created_by' => $adminUser->id,
                    'cliente_id' => $purchase->cliente_id,
                    'title' => 'Produção #1 - ' . ($purchase->pack->name ?? 'Pacote de Vídeos'),
                    'status' => 'draft',
                ]);
            }
        } else {
            $purchase->update([
                'status' => 'failed',
                'gateway_metadata' => $data,
            ]);

            Log::warning("Redsys Webhook: Pagamento recusado pelo banco. Código: {$responseCode} para o pedido {$orderNumber}.");
        }

        return response('OK', 200);
    }
}
