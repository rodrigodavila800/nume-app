<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesMcpTenant;
use App\Models\SupportTicket;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('open_support_tickets')]
#[Description('Lista os chamados de suporte em aberto na produtora.')]
#[IsReadOnly]
class OpenSupportTicketsTool extends Tool
{
    use ResolvesMcpTenant;

    public function handle(): Response
    {
        $tenantId = $this->tenantId();

        $tickets = SupportTicket::forTenant($tenantId)
            ->with('cliente')
            ->whereIn('status', SupportTicket::OPEN_STATUSES)
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get()
            ->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'category' => $t->category,
                'priority' => $t->priority,
                'status' => $t->status,
                'cliente_name' => $t->cliente?->name,
                'cliente_company' => $t->cliente?->company_name,
                'created_at' => $t->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Response::json([
            'total_open' => count($tickets),
            'tickets' => $tickets,
        ]);
    }
}
