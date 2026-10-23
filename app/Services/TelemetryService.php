<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\Roteiro;

class TelemetryService
{
    /**
     * Retorna o snapshot completo de métricas analíticas e de inteligência da base.
     */
    public function getSnapshot(?int $tenantId = null): array
    {
        // 1. Métricas Financeiras & Gateways
        $purchasesQuery = PackPurchase::withoutGlobalScope('tenant')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'paid');

        $paidPurchases = $purchasesQuery->get();
        $totalGrossRevenue = (float) $paidPurchases->sum('total_amount') ?: (float) $paidPurchases->sum('amount');
        $totalBaseRevenue = (float) $paidPurchases->sum('subtotal') ?: $totalGrossRevenue;
        $totalIva = (float) $paidPurchases->sum('tax_amount');
        $totalPlatformFee = (float) $paidPurchases->sum('application_fee_amount');
        $totalSalesCount = $paidPurchases->count();

        // Breakdown por canal de pagamento
        $gatewaysBreakdown = [
            'bizum' => [
                'count' => $paidPurchases->filter(fn ($p) => in_array($p->payment_gateway, ['bizum', 'redsys_bizum']))->count(),
                'amount' => (float) $paidPurchases->filter(fn ($p) => in_array($p->payment_gateway, ['bizum', 'redsys_bizum']))->sum('total_amount'),
            ],
            'redsys_card' => [
                'count' => $paidPurchases->where('payment_gateway', 'redsys_card')->count(),
                'amount' => (float) $paidPurchases->where('payment_gateway', 'redsys_card')->sum('total_amount'),
            ],
            'stripe' => [
                'count' => $paidPurchases->filter(fn ($p) => empty($p->payment_gateway) || $p->payment_gateway === 'stripe')->count(),
                'amount' => (float) $paidPurchases->filter(fn ($p) => empty($p->payment_gateway) || $p->payment_gateway === 'stripe')->sum('total_amount'),
            ],
        ];

        $averageTicket = $totalSalesCount > 0 
            ? $totalGrossRevenue / $totalSalesCount 
            : (float) (Pack::withoutGlobalScope('tenant')->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->avg('price') ?? 0);

        // 2. Clientes & Contratos
        $clientesQuery = Cliente::withoutGlobalScope('tenant')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        $totalClients = $clientesQuery->count();
        $activeClientsCount = (clone $clientesQuery)->has('productions')->count();

        // 3. Pipeline & Eficiência de Produção
        $productionsQuery = Production::withoutGlobalScope('tenant')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        $totalProductions = $productionsQuery->count();
        $inProductionCount = (clone $productionsQuery)->where('status', 'in_production')->count();
        $publishedCount = (clone $productionsQuery)->where('status', 'published')->count();

        $roteirosQuery = Roteiro::withoutGlobalScope('tenant')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        $totalRoteiros = $roteirosQuery->count();
        $approvedRoteiros = (clone $roteirosQuery)->where('status', 'approved')->count();
        $approvalRate = $totalRoteiros > 0 ? round(($approvedRoteiros / $totalRoteiros) * 100, 1) : 100;

        // 4. Performance por Pacote de Vídeo
        $packs = Pack::withoutGlobalScope('tenant')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get()
            ->map(function ($pack) use ($tenantId) {
                $pQuery = PackPurchase::withoutGlobalScope('tenant')
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->where('pack_id', $pack->id)
                    ->where('status', 'paid');

                $purchasesCount = $pQuery->count();
                $revenue = (float) $pQuery->sum('amount');
                return [
                    'id' => $pack->id,
                    'name' => $pack->name,
                    'price' => (float) $pack->price,
                    'video_quantity' => $pack->video_quantity,
                    'sales_count' => $purchasesCount,
                    'revenue' => $revenue,
                    'status' => $pack->status,
                ];
            });

        // 5. Top Clientes por Produções
        $topClients = Cliente::withoutGlobalScope('tenant')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->withCount('productions')
            ->orderByDesc('productions_count')
            ->take(5)
            ->get();

        // 6. Diagnósticos Preditivos da Sofi AI
        $sofiInsights = $this->generateSofiInsights(
            $totalGrossRevenue,
            $totalSalesCount,
            $totalClients,
            $inProductionCount,
            $approvalRate,
            $packs
        );

        return [
            'kpis' => [
                'grossRevenue' => $totalGrossRevenue,
                'baseRevenue' => $totalBaseRevenue,
                'totalIva' => $totalIva,
                'totalSalesCount' => $totalSalesCount,
                'averageTicket' => $averageTicket,
                'totalClients' => $totalClients,
                'activeClientsCount' => $activeClientsCount,
                'approvalRate' => $approvalRate,
            ],
            'pipeline' => [
                'totalProductions' => $totalProductions,
                'inProductionCount' => $inProductionCount,
                'publishedCount' => $publishedCount,
                'totalRoteiros' => $totalRoteiros,
                'approvedRoteiros' => $approvedRoteiros,
            ],
            'gatewaysBreakdown' => $gatewaysBreakdown,
            'packPerformance' => $packs,
            'topClients' => $topClients,
            'sofiInsights' => $sofiInsights,
        ];
    }

    /**
     * Motor de Inteligência Preditiva da Sofi
     */
    protected function generateSofiInsights(
        float $revenue,
        int $salesCount,
        int $clientsCount,
        int $inProductionCount,
        float $approvalRate,
        $packs
    ): array {
        $insights = [];

        // Insight 1: Estratégia de Prospecção & Catálogo
        $mostPopularPack = $packs->sortByDesc('sales_count')->first();
        if ($mostPopularPack && $mostPopularPack['sales_count'] > 0) {
            $insights[] = [
                'type' => 'prospecting',
                'category' => 'Prospecção & Marketing',
                'urgency' => 'alta',
                'title' => 'Campanha Direcionada: ' . $mostPopularPack['name'],
                'description' => "O pacote '{$mostPopularPack['name']}' lidera as conversões da sua vitrine. Recomendamos criar anúncios e prospecção ativa B2B enfatizando este formato com cases reais.",
                'action_label' => 'Ver Pacote no Catálogo',
                'action_url' => route('packs.index'),
            ];
        } else {
            $insights[] = [
                'type' => 'prospecting',
                'category' => 'Prospecção & Marketing',
                'urgency' => 'media',
                'title' => 'Ativação do Catálogo de Pacotes',
                'description' => 'Sua vitrine pública está pronta. Compartilhe o link da vitrine com marcas e empresas para gerar as primeiras transações de checkout direto.',
                'action_label' => 'Visualizar Vitrine Pública',
                'action_url' => route('storefront.show', auth()->user()->tenant->slug ?? 'nume'),
            ];
        }

        // Insight 2: Operação & Velocidade de Aprovação
        if ($approvalRate < 70 && $inProductionCount > 0) {
            $insights[] = [
                'type' => 'operations',
                'category' => 'Eficiência Operacional',
                'urgency' => 'alta',
                'title' => 'Gargalo de Revisão de Roteiros Identificado',
                'description' => "A taxa de aprovação atual é de {$approvalRate}%. Clientes estão solicitando alterações acima da média. Sugerimos usar o comando /copywriter da Sofi para estruturar briefings mais alinhados antes da escrita.",
                'action_label' => 'Revisar Produções',
                'action_url' => route('productions.index'),
            ];
        } else {
            $insights[] = [
                'type' => 'operations',
                'category' => 'Eficiência Operacional',
                'urgency' => 'baixa',
                'title' => 'Ritmo Narrativo Saudável',
                'description' => "A taxa de aprovação de roteiros está excelente em {$approvalRate}%. O fluxo entre estúdio e clientes está validado e escalável.",
                'action_label' => 'Gerenciar Pipeline',
                'action_url' => route('productions.index'),
            ];
        }

        // Insight 3: Oportunidade de Upsell & Retenção (LTV)
        if ($clientsCount > 0) {
            $insights[] = [
                'type' => 'upsell',
                'category' => 'Expansão de LTV',
                'urgency' => 'media',
                'title' => 'Oportunidade de Recorrência (Plano Mensal)',
                'description' => 'Identificamos clientes com produções finalizadas. Dispare uma proposta de contrato mensal para garantir recorrência (MRR) na produção de vídeos contínuos.',
                'action_label' => 'Ver Base de Clientes',
                'action_url' => route('clientes.index'),
            ];
        }

        return $insights;
    }
}
