<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesMcpTenant;
use App\Models\Roteiro;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pending_approvals')]
#[Description('Lista os roteiros enviados que aguardam aprovação do cliente.')]
#[IsReadOnly]
class PendingApprovalsTool extends Tool
{
    use ResolvesMcpTenant;

    public function handle(): Response
    {
        $tenantId = $this->tenantId();

        $items = Roteiro::withoutGlobalScopes()
            ->with(['production.cliente'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'submitted')
            ->orderBy('submitted_at')
            ->limit(25)
            ->get()
            ->map(fn (Roteiro $r) => [
                'uuid' => $r->uuid,
                'production_uuid' => $r->production?->uuid,
                'production_title' => $r->production?->title,
                'cliente_name' => $r->production?->cliente?->name,
                'cliente_company' => $r->production?->cliente?->company_name,
                'submitted_at' => $r->submitted_at?->toIso8601String(),
                'waiting_hours' => $r->submitted_at ? (int) $r->submitted_at->diffInHours(now()) : 0,
            ])
            ->values()
            ->all();

        return Response::json([
            'total_pending' => count($items),
            'items' => $items,
        ]);
    }
}
