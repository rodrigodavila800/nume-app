<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SofiConversation;
use App\Services\SofiCopilotChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SofiChatController extends Controller
{
    public function __construct(
        protected SofiCopilotChatService $chatService
    ) {}

    /**
     * Envia mensagem e recebe resposta em tempo real da Sofi Copilot.
     *
     * POST /api/v1/sofi/chat
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|exists:sofi_conversations,id',
            'production_id' => 'nullable|exists:productions,id',
            'cliente_id' => 'nullable|exists:clientes,id',
        ]);

        // Determina o cliente: via auth('cliente') ou payload
        $cliente = auth('cliente')->user();
        if (!$cliente && !empty($validated['cliente_id'])) {
            $cliente = Cliente::find($validated['cliente_id']);
        }

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não autenticado ou identificado.',
            ], 401);
        }

        // Recupera ou cria conversa
        if (!empty($validated['conversation_id'])) {
            $conversation = SofiConversation::where('tenant_id', $cliente->tenant_id)
                ->where('cliente_id', $cliente->id)
                ->findOrFail($validated['conversation_id']);
        } else {
            $conversation = SofiConversation::firstOrCreate(
                [
                    'tenant_id' => $cliente->tenant_id,
                    'cliente_id' => $cliente->id,
                    'production_id' => $validated['production_id'] ?? null,
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
     * Lista mensagens de uma conversa existente.
     *
     * GET /api/v1/sofi/conversations/{conversation}/messages
     */
    public function messages(Request $request, SofiConversation $conversation): JsonResponse
    {
        $cliente = auth('cliente')->user();
        if ($cliente && $conversation->cliente_id !== $cliente->id) {
            return response()->json(['success' => false, 'message' => 'Não autorizado.'], 403);
        }

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
}
