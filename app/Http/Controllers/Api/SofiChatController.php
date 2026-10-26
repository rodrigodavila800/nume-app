<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Production;
use App\Models\SofiConversation;
use App\Services\SofiCopilotChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chat da Sofi no portal do cliente. Só para o cliente logado (rota dentro de auth:cliente, com
 * CSRF e limite de uso): o cliente vem sempre da sessão, nunca do corpo da requisição, e conversa
 * e produção precisam ser dele.
 */
class SofiChatController extends Controller
{
    public function __construct(
        protected SofiCopilotChatService $chatService
    ) {}

    /**
     * Envia mensagem e recebe a resposta da Sofi.
     *
     * POST /portal/sofi/chat
     */
    public function chat(Request $request): JsonResponse
    {
        $cliente = $request->user('cliente');

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer',
            'production_id' => 'nullable|integer',
        ]);

        // Produção só do próprio cliente: título e roteiro entram no prompt da Sofi.
        $productionId = null;
        if (! empty($validated['production_id'])) {
            $productionId = Production::withoutGlobalScope('tenant')
                ->where('tenant_id', $cliente->tenant_id)
                ->where('cliente_id', $cliente->id)
                ->findOrFail($validated['production_id'])
                ->id;
        }

        if (! empty($validated['conversation_id'])) {
            $conversation = $this->ownConversation($cliente, (int) $validated['conversation_id']);
        } else {
            $conversation = SofiConversation::firstOrCreate(
                [
                    'tenant_id' => $cliente->tenant_id,
                    'cliente_id' => $cliente->id,
                    'production_id' => $productionId,
                    'status' => 'active',
                ],
                [
                    'title' => 'Atendimento Sofi Copilot',
                ]
            );
        }

        $sofiMessage = $this->chatService->reply($conversation, $validated['message']);

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'reply' => [
                'id' => $sofiMessage->id,
                'sender' => $sofiMessage->sender,
                'content' => $sofiMessage->content,
                'metadata' => $sofiMessage->metadata,
                'created_at' => $sofiMessage->created_at->format('H:i'),
            ],
        ]);
    }

    /**
     * Lista mensagens de uma conversa do próprio cliente.
     *
     * GET /portal/sofi/conversations/{conversation}/messages
     */
    public function messages(Request $request, int $conversation): JsonResponse
    {
        $conversation = $this->ownConversation($request->user('cliente'), $conversation);

        $messages = $conversation->messages()->orderBy('id', 'asc')->get()->map(function ($msg) {
            return [
                'id' => $msg->id,
                'sender' => $msg->sender,
                'content' => $msg->content,
                'metadata' => $msg->metadata,
                'created_at' => $msg->created_at->format('H:i'),
            ];
        });

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'messages' => $messages,
        ]);
    }

    /**
     * Conversa de outro cliente (do mesmo tenant ou não) responde 404, sem revelar que existe.
     */
    private function ownConversation($cliente, int $id): SofiConversation
    {
        return SofiConversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->findOrFail($id);
    }
}
