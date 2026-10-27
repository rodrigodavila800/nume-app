<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesMcpTenant;
use App\Models\Quote;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('quote_requests')]
#[Description('Lista as solicitações de orçamento enviadas pelos clientes no portal aguardando cálculo da equipe.')]
#[IsReadOnly]
class QuoteRequestsTool extends Tool
{
    use ResolvesMcpTenant;

    public function handle(): Response
    {
        $tenantId = $this->tenantId();

        $requests = Quote::withoutGlobalScopes()
            ->with('cliente')
            ->where('tenant_id', $tenantId)
            ->where('status', Quote::STATUS_REQUESTED)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn (Quote $q) => [
                'uuid' => $q->uuid,
                'number' => $q->number,
                'kind' => $q->kind ?? Quote::KIND_PRODUCTION,
                'project_title' => $q->project_title,
                'cliente_name' => $q->cliente?->name ?: $q->client_name,
                'cliente_company' => $q->cliente?->company_name,
                'video_quantity' => $q->inputs['video_quantity'] ?? $q->request_details['video_quantity'] ?? null,
                'request_text' => $q->request_text,
                'created_at' => $q->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Response::json([
            'total_requests' => count($requests),
            'requests' => $requests,
        ]);
    }
}
