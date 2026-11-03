<?php

namespace App\Services\WhatsApp;

use App\Mail\WhatsAppHandoffMail;
use App\Models\Lead;
use App\Models\SofiConversation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\LeadService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Passa uma conversa do WhatsApp da Sofi para a equipe: marca a conversa, registra no CRM e avisa
 * os administradores da produtora por e-mail. Uma vez por episódio: com a conversa já marcada não
 * avisa de novo (a Sofi pode repetir a chamada sem encher a caixa de entrada).
 */
class HandoffService
{
    public const STATUS_NEEDS_HUMAN = 'needs_human';

    /**
     * @var array<string, string>
     */
    public const REASONS = [
        'discount' => 'Desconto ou condição especial',
        'custom_project' => 'Projeto fora do catálogo',
        'complaint' => 'Reclamação ou problema',
        'fiscal_contract' => 'Questão fiscal ou contratual',
        'human_requested' => 'Cliente pediu uma pessoa',
        'ai_unavailable' => 'IA indisponível no momento',
        'other' => 'Outro assunto',
    ];

    public function __construct(protected LeadService $leads) {}

    /**
     * Devolve false quando a equipe já estava avisada (nada foi feito de novo).
     */
    public function request(SofiConversation $conversation, ?Lead $lead, string $reason, string $summary = ''): bool
    {
        if ($conversation->status === self::STATUS_NEEDS_HUMAN) {
            return false;
        }

        $reason = array_key_exists($reason, self::REASONS) ? $reason : 'other';
        $summary = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($summary))), 300);

        $conversation->forceFill(['status' => self::STATUS_NEEDS_HUMAN])->save();

        if ($lead) {
            $this->leads->log(
                $lead,
                'whatsapp',
                'Sofi chamou a equipe: '.self::REASONS[$reason].($summary !== '' ? ". {$summary}" : '.'),
                null,
                ['conversation_id' => $conversation->id, 'channel' => 'whatsapp', 'handoff' => $reason],
            );
        }

        Log::info('[MetaWhatsApp] Equipe chamada: '.$reason.'.');
        $this->notifyAdmins($conversation, $lead, $reason, $summary);

        return true;
    }

    private function notifyAdmins(SofiConversation $conversation, ?Lead $lead, string $reason, string $summary): void
    {
        $tenantName = (string) (Tenant::find($conversation->tenant_id)?->name ?? 'NUME');
        $mail = new WhatsAppHandoffMail(
            $tenantName,
            (string) ($conversation->cliente?->name ?? $lead?->name ?? __('Contato novo')),
            (string) preg_replace('/\D/', '', (string) $conversation->contact_phone),
            self::REASONS[$reason],
            $summary !== '' ? $summary : __('Sem resumo.'),
            route('inbox.index', ['conversation_id' => $conversation->id]),
        );

        $emails = User::forTenant((int) $conversation->tenant_id)
            ->where('role', 'admin')
            ->whereNotNull('email')
            ->pluck('email');

        foreach ($emails as $email) {
            try {
                Mail::to($email)->queue($mail);
            } catch (Throwable $e) {
                // Só a classe: o aviso por e-mail nunca derruba o atendimento nem vaza dado no log.
                Log::warning('[MetaWhatsApp] Falha ao avisar a equipe por e-mail: '.get_class($e));
            }
        }
    }
}
