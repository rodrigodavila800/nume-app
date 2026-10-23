<?php

namespace App\Http\Controllers;

use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(
        protected TelemetryService $telemetryService
    ) {}

    /**
     * Dashboard Executivo de Vendas & BI (AkaDash Standard)
     */
    public function index(Request $request): View
    {
        $telemetry = $this->telemetryService->getSnapshot($request->user()->tenant_id);

        return view('analytics.index', $telemetry);
    }

    /**
     * Endpoint M2M protegido de telemetria para o cérebro da Sofi AI
     */
    public function sofiDataFeed(Request $request): JsonResponse
    {
        $telemetry = $this->telemetryService->getSnapshot($request->user()->tenant_id);

        return response()->json([
            'status' => 'success',
            'agent' => 'Sofi AI Copilot Engine',
            'generated_at' => now()->toIso8601String(),
            'tenant' => [
                'id' => auth()->user()->tenant_id,
                'name' => auth()->user()->tenant->name ?? 'NUME Creative',
            ],
            'kpis' => $telemetry['kpis'],
            'gateways_breakdown' => $telemetry['gatewaysBreakdown'],
            'pipeline' => $telemetry['pipeline'],
            'sofi_actionable_insights' => $telemetry['sofiInsights'],
            'top_clients' => $telemetry['topClients'],
            'pack_performance' => $telemetry['packPerformance'],
        ]);
    }
}
