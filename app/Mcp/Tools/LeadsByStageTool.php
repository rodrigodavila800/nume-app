<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesMcpTenant;
use App\Models\Lead;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('leads_by_stage')]
#[Description('Consulta o funil de leads da produtora por etapa e sinaliza leads sem contato há mais de 7 dias.')]
#[IsReadOnly]
class LeadsByStageTool extends Tool
{
    use ResolvesMcpTenant;

    public function schema(JsonSchema $schema): array
    {
        return [
            'stage' => $schema->string()
                ->enum(Lead::STAGES)
                ->description('Filtra apenas uma etapa específica do funil (opcional).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $stage = $request->get('stage');

        $counts = Lead::forTenant($tenantId)
            ->selectRaw('stage, COUNT(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->all();

        $query = Lead::forTenant($tenantId)->orderByDesc('updated_at');
        if (is_string($stage) && in_array($stage, Lead::STAGES, true)) {
            $query->where('stage', $stage);
        }

        $leads = $query->limit(25)->get()->map(fn (Lead $lead) => [
            'id' => $lead->id,
            'name' => $lead->name,
            'company' => $lead->company,
            'stage' => $lead->stage,
            'source' => $lead->source,
            'estimated_value' => $lead->estimated_value !== null ? (float) $lead->estimated_value : null,
            'stale' => $lead->isStale(),
            'last_contact_at' => $lead->last_contact_at?->toIso8601String(),
        ])->values()->all();

        return Response::json([
            'counts_by_stage' => $counts,
            'leads' => $leads,
        ]);
    }
}
