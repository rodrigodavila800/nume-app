<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\VideoPublication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistantApiController extends Controller
{
    /**
     * Resumo operacional completo para ChatGPT e Claude (Sofi Ops).
     */
    public function overview(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant?->id ?? $request->attributes->get('tenant_id');

        $pendingRoteirosCount = Roteiro::where('tenant_id', $tenantId)
            ->where('status', 'submitted')
            ->count();

        $activeProductionsCount = Production::where('tenant_id', $tenantId)
            ->whereIn('status', ['in_production', 'approved'])
            ->count();

        $scheduledVideosCount = VideoPublication::where('tenant_id', $tenantId)
            ->where('status', 'agendado')
            ->count();

        $upcomingProductions = Production::with('cliente')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('target_publication_date')
            ->where('target_publication_date', '>=', now()->toDateString())
            ->orderBy('target_publication_date')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'status' => $p->status,
                'target_date' => $p->target_publication_date?->toDateString(),
                'cliente' => $p->cliente?->company_name ?: ($p->cliente?->name ?? 'Cliente'),
            ]);

        return response()->json([
            'operational_status' => 'active',
            'summary' => [
                'pending_approvals' => $pendingRoteirosCount,
                'active_productions' => $activeProductionsCount,
                'scheduled_social_posts' => $scheduledVideosCount,
            ],
            'upcoming_deadlines' => $upcomingProductions,
        ]);
    }

    /**
     * Lista itens pendentes de aprovação com Magic Links prontos para envio.
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant?->id ?? $request->attributes->get('tenant_id');

        $roteiros = Roteiro::with(['production.cliente'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'submitted')
            ->orderBy('submitted_at')
            ->get()
            ->map(function ($r) {
                return [
                    'roteiro_id' => $r->id,
                    'production_id' => $r->production_id,
                    'production_title' => $r->production?->title,
                    'cliente_name' => $r->production?->cliente?->name,
                    'cliente_company' => $r->production?->cliente?->company_name,
                    'submitted_at' => $r->submitted_at?->toIso8601String(),
                    'waiting_hours' => $r->submitted_at ? (int) $r->submitted_at->diffInHours(now()) : 0,
                    'reminder_count' => $r->reminder_count,
                    'magic_approval_url' => $r->getMagicApprovalUrl(14),
                    'whatsapp_share_url' => $r->getWhatsAppShareUrl(14),
                ];
            });

        return response()->json([
            'total_pending' => $roteiros->count(),
            'items' => $roteiros,
        ]);
    }

    /**
     * Lista os vídeos agendados para os próximos 30 dias.
     */
    public function schedule(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant?->id ?? $request->attributes->get('tenant_id');

        $publications = VideoPublication::with(['cliente', 'video.production'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'agendado')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get()
            ->map(fn ($pub) => [
                'id' => $pub->id,
                'title' => $pub->titulo,
                'scheduled_at' => $pub->scheduled_at?->toIso8601String(),
                'platforms' => $pub->plataformas ?? [],
                'cliente' => $pub->cliente?->company_name ?: ($pub->cliente?->name ?? 'Cliente'),
                'production_title' => $pub->video?->production?->title,
            ]);

        return response()->json([
            'total_scheduled' => $publications->count(),
            'publications' => $publications,
        ]);
    }

    /**
     * Retorna a especificação OpenAPI JSON 3.1 para configuração do Custom GPT "Sofi Ops".
     */
    public function openApiSchema(): JsonResponse
    {
        $baseUrl = url('/api/v1/assistant');

        return response()->json([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'NUME Sofi Ops Assistant API',
                'description' => 'Conector oficial de IA para ChatGPT e Claude da plataforma audiovisual NUME.',
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => $baseUrl],
            ],
            'paths' => [
                '/overview' => [
                    'get' => [
                        'summary' => 'Resumo operacional da produtora',
                        'operationId' => 'getOperationalOverview',
                        'description' => 'Retorna o número de aprovações pendentes, vídeos agendados e prazos da semana.',
                        'responses' => [
                            '200' => ['description' => 'Dados operacionais retornados com sucesso.'],
                        ],
                    ],
                ],
                '/pending-approvals' => [
                    'get' => [
                        'summary' => 'Lista roteiros aguardando aprovação do cliente',
                        'operationId' => 'getPendingApprovals',
                        'description' => 'Retorna roteiros pendentes com tempo de espera e links mágicos de WhatsApp.',
                        'responses' => [
                            '200' => ['description' => 'Lista de aprovações pendentes.'],
                        ],
                    ],
                ],
                '/schedule' => [
                    'get' => [
                        'summary' => 'Agenda de publicações sociais',
                        'operationId' => 'getScheduledPosts',
                        'description' => 'Retorna próximas postagens agendadas para redes sociais.',
                        'responses' => [
                            '200' => ['description' => 'Agenda de publicações.'],
                        ],
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                        'description' => 'Token de API da produtora (nume_live_...) gerado no painel da NUME.',
                    ],
                ],
            ],
            'security' => [
                ['ApiKeyAuth' => []],
            ],
        ]);
    }

    /**
     * Retorna o Prompt de Instruções recomendado para o Custom GPT.
     */
    public function promptInstructions(): JsonResponse
    {
        $prompt = "Você é o Sofi Ops, o Copiloto Executivo de Operações da produtora de vídeos na plataforma NUME.\n"
            . "Sua função é manter o dono da produtora informado sobre gravações da semana, prazos, roteiros pendentes e publicações agendadas.\n"
            . "Sempre que o usuário perguntar o que precisa ser aprovado ou cobrar um cliente, consulte a ferramenta getPendingApprovals e forneça a mensagem pronta com o link mágico do WhatsApp para ele apenas copiar e colar no celular.";

        return response()->json([
            'assistant_name' => 'Sofi Ops Copilot',
            'recommended_system_prompt' => $prompt,
            'openapi_schema_url' => route('api.assistant.openapi'),
        ]);
    }
}
