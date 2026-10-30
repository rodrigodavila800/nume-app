<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected MetaWhatsAppService $whatsAppService
    ) {}

    /**
     * Verificação de assinatura do Webhook pelo Meta Cloud API (GET).
     */
    public function verify(Request $request): Response|JsonResponse
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        $verifiedChallenge = $this->whatsAppService->verifyWebhook($mode, $token, $challenge);

        if ($verifiedChallenge !== null) {
            return response($verifiedChallenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['error' => 'Verification failed'], 403);
    }

    /**
     * Recebimento de mensagens e eventos do Meta Cloud API (POST).
     */
    public function handle(Request $request): JsonResponse
    {
        // Só a Meta assina com o App Secret: sem assinatura válida, o "from" do corpo não é confiável
        // (qualquer um poderia se passar pelo telefone de um cliente e consultar as produções dele).
        if (! $this->whatsAppService->hasValidSignature($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            Log::warning('[MetaWhatsApp] POST no webhook com assinatura ausente ou inválida.');

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $this->whatsAppService->processWebhookPayload($request->json()->all());

        // A Meta exige retorno imediato 200 OK com EVENT_RECEIVED para evitar retries desnecessários
        return response()->json(['status' => 'EVENT_RECEIVED'], 200);
    }
}
