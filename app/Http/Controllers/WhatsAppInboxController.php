<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Production;
use App\Models\SofiConversation;
use App\Models\SofiMessage;
use App\Services\Crm\LeadService;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppInboxController extends Controller
{
    public function __construct(
        protected MetaWhatsAppService $whatsAppService,
        protected LeadService $leadService,
    ) {}

    /**
     * Central de Mensagens do WhatsApp e canais da Sofi no painel da produtora.
     */
    public function index(Request $request): View
    {
        $tenantId = (int) auth()->user()->tenant_id;

        $conversations = SofiConversation::forTenant($tenantId)
            ->where('channel', 'whatsapp')
            ->with(['cliente', 'latestMessage'])
            ->latest('updated_at')
            ->get();

        // Se uma conversa específica foi solicitada ou seleciona a primeira por padrão
        $activeConversationId = $request->query('conversation_id');
        $activeConversation = null;

        if ($activeConversationId) {
            $activeConversation = SofiConversation::forTenant($tenantId)
                ->where('channel', 'whatsapp')
                ->with(['cliente', 'messages'])
                ->find($activeConversationId);
        }

        if (! $activeConversation && $conversations->isNotEmpty()) {
            $activeConversation = SofiConversation::forTenant($tenantId)
                ->where('channel', 'whatsapp')
                ->with(['cliente', 'messages'])
                ->find($conversations->first()->id);
        }

        // Dados contextuais do contato (Lead do CRM e Produções)
        $associatedLead = null;
        $associatedProductions = collect();

        if ($activeConversation) {
            $phone = (string) $activeConversation->contact_phone;
            $cleanPhone = preg_replace('/\D/', '', $phone);
            $phoneSuffix = strlen($cleanPhone) >= 8 ? substr($cleanPhone, -8) : $cleanPhone;

            // Busca lead associado no CRM
            $associatedLead = Lead::forTenant($tenantId)
                ->whereNull('anonymized_at')
                ->where(function ($q) use ($activeConversation, $cleanPhone, $phoneSuffix) {
                    $q->when($activeConversation->cliente_id, fn ($q) => $q->orWhere('cliente_id', $activeConversation->cliente_id))
                        ->when($cleanPhone, fn ($q) => $q->orWhere('phone', 'like', "%{$cleanPhone}%"))
                        ->when($phoneSuffix, fn ($q) => $q->orWhere('phone', 'like', "%{$phoneSuffix}%"));
                })
                ->latest('id')
                ->first();

            // Produções ativas se houver cliente vinculado
            if ($activeConversation->cliente_id) {
                $associatedProductions = Production::forTenant($tenantId)
                    ->where('cliente_id', $activeConversation->cliente_id)
                    ->latest('id')
                    ->take(5)
                    ->get();
            }
        }

        return view('inbox.index', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'associatedLead' => $associatedLead,
            'associatedProductions' => $associatedProductions,
        ]);
    }

    /**
     * Envia mensagem de resposta manual da equipe pelo WhatsApp via Meta Cloud API.
     */
    public function reply(Request $request, SofiConversation $conversation): RedirectResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;

        if ($conversation->tenant_id !== $tenantId) {
            abort(403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $text = trim($validated['message']);
        $recipientPhone = (string) $conversation->contact_phone;

        // 1. Salva a mensagem no histórico (sender 'sofi' com metadata is_human para compatibilidade com enum)
        $messageRecord = SofiMessage::create([
            'sofi_conversation_id' => $conversation->id,
            'sender' => 'sofi',
            'content' => $text,
            'metadata' => [
                'is_human' => true,
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
            ],
        ]);

        $conversation->touch();

        // 2. Dispara a mensagem para o WhatsApp do contato via Cloud API
        $sent = $this->whatsAppService->sendWhatsAppMessage($recipientPhone, $text);

        // 3. Registra no CRM se houver lead correspondente
        $cleanPhone = preg_replace('/\D/', '', $recipientPhone);
        $phoneSuffix = strlen($cleanPhone) >= 8 ? substr($cleanPhone, -8) : $cleanPhone;
        $lead = Lead::forTenant($tenantId)
            ->whereNull('anonymized_at')
            ->where(function ($q) use ($conversation, $cleanPhone, $phoneSuffix) {
                $q->when($conversation->cliente_id, fn ($q) => $q->orWhere('cliente_id', $conversation->cliente_id))
                    ->when($cleanPhone, fn ($q) => $q->orWhere('phone', 'like', "%{$cleanPhone}%"))
                    ->when($phoneSuffix, fn ($q) => $q->orWhere('phone', 'like', "%{$phoneSuffix}%"));
            })
            ->latest('id')
            ->first();

        if ($lead) {
            $this->leadService->log(
                $lead,
                'whatsapp',
                'Mensagem manual enviada pela equipe ('.auth()->user()->name."):\n\"{$text}\"",
                auth()->user(),
                ['conversation_id' => $conversation->id, 'channel' => 'whatsapp']
            );
        }

        if (! $sent) {
            return back()->with('warning', __('Mensagem salva no painel, mas houve uma oscilação na API da Meta. Verifique o token no servidor.'));
        }

        return back()->with('success', __('Mensagem enviada com sucesso para o WhatsApp!'));
    }
}
