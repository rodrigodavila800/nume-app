<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesMcpTenant;
use App\Models\Lead;
use App\Models\Production;
use App\Models\Quote;
use App\Models\Roteiro;
use App\Models\SupportTicket;
use App\Models\VideoPublication;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('overview')]
#[Description('Visão geral operacional da produtora: aprovações pendentes, produções ativas, agenda social, solicitações de orçamento, leads e chamados abertos.')]
#[IsReadOnly]
class OverviewTool extends Tool
{
    use ResolvesMcpTenant;

    public function handle(): Response
    {
        $tenantId = $this->tenantId();

        $pendingRoteiros = Roteiro::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'submitted')
            ->count();

        $activeProductions = Production::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['in_production', 'approved'])
            ->count();

        $scheduledVideos = VideoPublication::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'agendado')
            ->count();

        $requestedQuotes = Quote::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'requested')
            ->count();

        $openTickets = SupportTicket::forTenant($tenantId)
            ->whereIn('status', SupportTicket::OPEN_STATUSES)
            ->count();

        $activeLeads = Lead::forTenant($tenantId)
            ->whereIn('stage', Lead::OPEN_STAGES)
            ->count();

        $upcomingDeadlines = Production::withoutGlobalScopes()
            ->with('cliente')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('target_publication_date')
            ->where('target_publication_date', '>=', now()->toDateString())
            ->orderBy('target_publication_date')
            ->limit(5)
            ->get()
            ->map(fn (Production $p) => [
                'uuid' => $p->uuid,
                'title' => $p->title,
                'status' => $p->status,
                'target_date' => $p->target_publication_date?->toDateString(),
                'cliente' => $p->cliente?->company_name ?: ($p->cliente?->name ?? 'Cliente'),
            ])
            ->values()
            ->all();

        return Response::json([
            'summary' => [
                'pending_approvals' => $pendingRoteiros,
                'active_productions' => $activeProductions,
                'scheduled_social_posts' => $scheduledVideos,
                'quote_requests' => $requestedQuotes,
                'open_support_tickets' => $openTickets,
                'active_leads' => $activeLeads,
            ],
            'upcoming_deadlines' => $upcomingDeadlines,
        ]);
    }
}
