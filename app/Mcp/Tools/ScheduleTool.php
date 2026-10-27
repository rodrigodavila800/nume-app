<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesMcpTenant;
use App\Models\VideoPublication;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('schedule')]
#[Description('Lista os vídeos agendados para publicação nas redes sociais da produtora.')]
#[IsReadOnly]
class ScheduleTool extends Tool
{
    use ResolvesMcpTenant;

    public function handle(): Response
    {
        $tenantId = $this->tenantId();

        $publications = VideoPublication::withoutGlobalScopes()
            ->with(['cliente', 'video.production'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'agendado')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->limit(25)
            ->get()
            ->map(fn (VideoPublication $pub) => [
                'id' => $pub->id,
                'title' => $pub->titulo,
                'scheduled_at' => $pub->scheduled_at?->toIso8601String(),
                'platforms' => $pub->plataformas ?? [],
                'cliente' => $pub->cliente?->company_name ?: ($pub->cliente?->name ?? 'Cliente'),
                'production_title' => $pub->video?->production?->title,
            ])
            ->values()
            ->all();

        return Response::json([
            'total_scheduled' => count($publications),
            'publications' => $publications,
        ]);
    }
}
