<?php

namespace App\Services\Crm;

use App\Models\Cliente;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;

/**
 * Abertura, respostas e mudança de status dos chamados. Tenant sempre explícito.
 */
class SupportService
{
    public function open(Cliente $cliente, string $subject, string $body, string $category = 'duvida', string $source = 'portal'): SupportTicket
    {
        $ticket = new SupportTicket([
            'cliente_id' => $cliente->id,
            'subject' => mb_substr(trim($subject), 0, 200),
            'category' => in_array($category, SupportTicket::CATEGORIES, true) ? $category : 'outro',
            'source' => $source,
        ]);
        $ticket->forceFill(['tenant_id' => $cliente->tenant_id, 'last_reply_at' => now()])->save();

        $this->addMessage($ticket, 'cliente', $cliente->id, $body);

        return $ticket;
    }

    /**
     * Resposta do cliente: reabre chamado resolvido e tira de "aguardando cliente".
     */
    public function clienteReply(SupportTicket $ticket, Cliente $cliente, string $body): void
    {
        $this->addMessage($ticket, 'cliente', $cliente->id, $body);

        if ($ticket->status !== 'fechado') {
            $ticket->forceFill(['status' => 'aberto', 'resolved_at' => null, 'last_reply_at' => now()])->save();
        }
    }

    /**
     * Resposta da equipe. Nota interna não muda o status nem aparece para o cliente.
     */
    public function teamReply(SupportTicket $ticket, User $user, string $body, bool $internal = false): void
    {
        $this->addMessage($ticket, 'user', $user->id, $body, $internal);

        if (! $internal) {
            $ticket->forceFill([
                'status' => $ticket->isOpen() ? 'aguardando_cliente' : $ticket->status,
                'last_reply_at' => now(),
                'assigned_to' => $ticket->assigned_to ?? $user->id,
            ])->save();
        }
    }

    public function changeStatus(SupportTicket $ticket, string $status, ?User $user = null): void
    {
        if (! in_array($status, SupportTicket::STATUSES, true) || $ticket->status === $status) {
            return;
        }

        $ticket->forceFill([
            'status' => $status,
            'resolved_at' => in_array($status, ['resolvido', 'fechado'], true) ? now() : null,
        ])->save();

        $this->addMessage($ticket, 'system', null, __('Status alterado para ":status".', ['status' => SupportTicket::statusLabels()[$status]]).($user ? ' ('.$user->name.')' : ''));
    }

    private function addMessage(SupportTicket $ticket, string $authorType, ?int $authorId, string $body, bool $internal = false): SupportTicketMessage
    {
        $message = new SupportTicketMessage([
            'support_ticket_id' => $ticket->id,
            'author_type' => $authorType,
            'author_id' => $authorId,
            'body' => mb_substr($body, 0, 10000),
            'internal' => $internal,
        ]);
        $message->forceFill(['tenant_id' => $ticket->tenant_id])->save();

        return $message;
    }
}
