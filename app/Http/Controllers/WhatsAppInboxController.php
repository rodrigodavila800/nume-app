<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Production;
use App\Models\SofiConversation;
use App\Models\SofiMessage;
use App\Services\Crm\LeadService;
use App\Services\WhatsApp\HandoffService;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Http\JsonResponse;
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

        $conversations = $this->conversationsFor($tenantId);

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
     * Atualização automática do painel (o navegador consulta a cada poucos segundos): balões novos da
     * conversa aberta, depois do último id que a tela já tem, e a lista lateral só quando algo mudou
     * (assinatura = quantidade de conversas, última mensagem e status). Tudo filtrado pelo tenant.
     */
    public function poll(Request $request): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'after' => ['nullable', 'integer', 'min:0'],
            'signature' => ['nullable', 'string', 'max:100'],
        ]);

        $conversations = $this->conversationsFor($tenantId);
        $signature = implode(':', [
            $conversations->count(),
            (int) SofiMessage::whereIn('sofi_conversation_id', $conversations->pluck('id'))->max('id'),
            md5($conversations->pluck('status', 'id')->toJson()),
        ]);

        $active = filled($validated['conversation_id'] ?? null)
            ? $conversations->firstWhere('id', (int) $validated['conversation_id'])
            : null;

        $lastId = (int) ($validated['after'] ?? 0);
        $messagesHtml = '';

        if ($active) {
            $newMessages = $active->messages()->where('id', '>', $lastId)->get();
            foreach ($newMessages as $message) {
                $messagesHtml .= view('inbox._message', ['m' => $message, 'activeConversation' => $active])->render();
            }
            $lastId = (int) ($newMessages->last()?->id ?? $lastId);
        }

        return response()->json([
            'messages_html' => $messagesHtml,
            'last_id' => $lastId,
            'status' => $active?->status,
            'signature' => $signature,
            'list_html' => ($validated['signature'] ?? null) === $signature
                ? null
                : view('inbox._conversation_list', ['conversations' => $conversations, 'activeConversation' => $active])->render(),
        ]);
    }

    /**
     * Conversas do WhatsApp da produtora: as que precisam de uma pessoa primeiro, depois as mais recentes.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SofiConversation>
     */
    private function conversationsFor(int $tenantId)
    {
        return SofiConversation::forTenant($tenantId)
            ->where('channel', 'whatsapp')
            ->with(['cliente', 'latestMessage'])
            ->orderByRaw('status = ? desc', [HandoffService::STATUS_NEEDS_HUMAN])
            ->latest('updated_at')
            ->get();
    }

    /**
     * A equipe devolve a conversa para a Sofi antes do fim da pausa (ou depois de resolver o ponto
     * que a Sofi passou para uma pessoa). A próxima mensagem do cliente já é respondida pela Sofi.
     */
    public function handBack(SofiConversation $conversation): RedirectResponse
    {
        if ($conversation->tenant_id !== (int) auth()->user()->tenant_id) {
            abort(403);
        }

        $conversation->forceFill(['status' => 'active'])->save();

        $lead = $this->leadFor($conversation, (int) $conversation->tenant_id);
        if ($lead) {
            $this->leadService->log(
                $lead,
                'whatsapp',
                'Conversa devolvida para a Sofi por '.auth()->user()->name.'.',
                auth()->user(),
                ['conversation_id' => $conversation->id, 'channel' => 'whatsapp']
            );
        }

        return back()->with('success', __('A Sofi voltou a atender esta conversa.'));
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

        // A equipe assumiu: a Sofi fica calada por algumas horas (config sofi.whatsapp_human_pause_hours).
        $conversation->forceFill(['status' => HandoffService::STATUS_HUMAN])->save();
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

    private function leadFor(SofiConversation $conversation, int $tenantId): ?Lead
    {
        $cleanPhone = preg_replace('/\D/', '', (string) $conversation->contact_phone);
        $phoneSuffix = strlen($cleanPhone) >= 8 ? substr($cleanPhone, -8) : $cleanPhone;

        return Lead::forTenant($tenantId)
            ->whereNull('anonymized_at')
            ->where(function ($q) use ($conversation, $cleanPhone, $phoneSuffix) {
                $q->when($conversation->cliente_id, fn ($q) => $q->orWhere('cliente_id', $conversation->cliente_id))
                    ->when($cleanPhone, fn ($q) => $q->orWhere('phone', 'like', "%{$cleanPhone}%"))
                    ->when($phoneSuffix, fn ($q) => $q->orWhere('phone', 'like', "%{$phoneSuffix}%"));
            })
            ->latest('id')
            ->first();
    }
}
