<?php

namespace App\Http\Controllers;

use App\Ai\AiBudget;
use App\Models\AiUsageLog;
use App\Models\Cliente;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configurações > Uso de IA: acompanha tokens por mês, função e cliente e define limites mensais.
 */
class AiUsageController extends Controller
{
    private const AGENT_LABELS = [
        'sofi_script' => 'Roteiro (clássico)',
        'sofi_script_blocks' => 'Roteiro (por blocos)',
        'sofi_brand_analyst' => 'DNA da marca',
        'sofi_marketing_plan' => 'Plano de marketing',
        'sofi_support_chat' => 'Chat de suporte (portal)',
        'sofi_sales_consultant' => 'Chat comercial (vitrine)',
        'sofi_quote_estimator' => 'Estimativa de orçamento',
        'sofi_translator' => 'Tradutor PT/ES',
    ];

    public function __construct(private readonly AiBudget $budget) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AiUsageLog::class);

        $tenantId = (int) $request->user()->tenant_id;
        $requestedMonth = (string) $request->query('month', now()->format('Y-m'));
        $month = preg_match('/^\d{4}-\d{2}$/', $requestedMonth) ? $requestedMonth : now()->format('Y-m');

        $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->endOfMonth();

        $budgetSummary = $this->budget->summary($tenantId);

        $byAgent = AiUsageLog::forTenant($tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('agent, COUNT(*) as calls, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens, SUM(input_tokens + output_tokens) as total_tokens')
            ->groupBy('agent')
            ->orderByDesc('total_tokens')
            ->get()
            ->map(function ($row) {
                $row->label = __(self::AGENT_LABELS[$row->agent] ?? $row->agent);

                return $row;
            });

        $byClienteRaw = AiUsageLog::forTenant($tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('cliente_id')
            ->selectRaw('cliente_id, COUNT(*) as calls, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens, SUM(input_tokens + output_tokens) as total_tokens')
            ->groupBy('cliente_id')
            ->orderByDesc('total_tokens')
            ->get();

        $clienteNames = $byClienteRaw->isEmpty()
            ? collect()
            : Cliente::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $byClienteRaw->pluck('cliente_id'))
                ->pluck('name', 'id');

        $byCliente = $byClienteRaw->map(function ($row) use ($clienteNames, $budgetSummary) {
            $row->cliente_name = $clienteNames->get($row->cliente_id, '#'.$row->cliente_id);
            $row->exhausted = $budgetSummary['cliente_limit'] !== null
                && (int) $row->total_tokens >= $budgetSummary['cliente_limit'];

            return $row;
        });

        $historyStart = now()->subMonths(5)->startOfMonth();
        $monthlyHistory = AiUsageLog::forTenant($tenantId)
            ->where('created_at', '>=', $historyStart)
            ->get(['created_at', 'input_tokens', 'output_tokens'])
            ->groupBy(fn (AiUsageLog $log) => $log->created_at->format('Y-m'))
            ->map(fn ($logs, $ym) => [
                'month' => $ym,
                'calls' => $logs->count(),
                'input_tokens' => (int) $logs->sum('input_tokens'),
                'output_tokens' => (int) $logs->sum('output_tokens'),
                'total_tokens' => (int) $logs->sum(fn (AiUsageLog $l) => $l->input_tokens + $l->output_tokens),
            ])
            ->sortKeysDesc()
            ->values();

        $monthTotals = [
            'calls' => (int) $byAgent->sum('calls'),
            'input_tokens' => (int) $byAgent->sum('input_tokens'),
            'output_tokens' => (int) $byAgent->sum('output_tokens'),
            'total_tokens' => (int) $byAgent->sum('total_tokens'),
        ];

        return view('settings.ai_usage.index', [
            'month' => $month,
            'budget' => $budgetSummary,
            'monthTotals' => $monthTotals,
            'byAgent' => $byAgent,
            'byCliente' => $byCliente,
            'monthlyHistory' => $monthlyHistory,
        ]);
    }

    public function updateLimits(Request $request): RedirectResponse
    {
        $this->authorize('updateLimits', AiUsageLog::class);

        $validated = $request->validate([
            'monthly_token_limit' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'monthly_cliente_token_limit' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $this->budget->setLimits(
            tenantId: (int) $request->user()->tenant_id,
            tenantLimit: isset($validated['monthly_token_limit']) ? (int) $validated['monthly_token_limit'] : null,
            clienteLimit: isset($validated['monthly_cliente_token_limit']) ? (int) $validated['monthly_cliente_token_limit'] : null,
        );

        return redirect()
            ->route('ai_usage.index')
            ->with('success', __('Limites mensais de tokens atualizados.'));
    }
}
