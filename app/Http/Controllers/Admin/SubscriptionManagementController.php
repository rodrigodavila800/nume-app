<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackPurchase;
use App\Models\Production;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionManagementController extends Controller
{
    /**
     * Listagem geral de Assinaturas e Contratos Recorrentes dos Clientes da produtora.
     */
    public function index(Request $request): View
    {
        $tenant = $request->user()->tenant;

        $query = PackPurchase::where('tenant_id', $tenant->id)
            ->where(function ($q) {
                $q->where('billing_type', 'subscription')
                  ->orWhereNotNull('subscription_status');
            })
            ->with(['cliente', 'pack']);

        // Filtro por Status da Assinatura
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('subscription_status', $request->input('status'));
        }

        // Filtro por Busca de Cliente ou Plano
        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($sub) use ($search) {
                $sub->whereHas('cliente', function ($c) use ($search) {
                    $c->where('name', 'like', $search)
                      ->orWhere('email', 'like', $search)
                      ->orWhere('company_name', 'like', $search);
                })->orWhereHas('pack', function ($p) use ($search) {
                    $p->where('name', 'like', $search);
                });
            });
        }

        // Métricas e KPIs de Assinatura (MRR, Clientes Ativos, Cotas de Vídeo)
        $allSubscriptions = PackPurchase::where('tenant_id', $tenant->id)
            ->where(function ($q) {
                $q->where('billing_type', 'subscription')
                  ->orWhereNotNull('subscription_status');
            })
            ->get();

        $activeSubscriptions = $allSubscriptions->where('subscription_status', 'active');

        // Cálculo de MRR (convertendo se houver intervalo trimestral)
        $estimatedMrr = $activeSubscriptions->sum(function ($sub) {
            $interval = $sub->subscription_interval ?: 'month';
            $amount = (float) ($sub->total_amount ?: $sub->amount);
            return ($interval === 'quarter') ? ($amount / 3) : $amount;
        });

        $totalActiveQuota = $activeSubscriptions->sum('video_quantity');
        $totalVideosUsed = $activeSubscriptions->sum('videos_used');

        $kpis = [
            'mrr' => (float) $estimatedMrr,
            'active_count' => $activeSubscriptions->count(),
            'total_contracts' => $allSubscriptions->count(),
            'total_quota' => (int) $totalActiveQuota,
            'videos_used' => (int) $totalVideosUsed,
            'videos_remaining' => max(0, (int) ($totalActiveQuota - $totalVideosUsed)),
        ];

        $subscriptions = $query->latest('id')->paginate(15)->withQueryString();

        return view('admin.subscriptions.index', compact('subscriptions', 'kpis'));
    }

    /**
     * Detalhes de uma Assinatura / Contrato de Cliente.
     */
    public function show(Request $request, PackPurchase $subscription): View
    {
        $tenant = $request->user()->tenant;
        abort_if($subscription->tenant_id !== $tenant->id, 403);

        $subscription->load(['cliente', 'pack', 'pack.addons']);

        // Produções audiovisuais criadas sob este contrato
        $productions = Production::where('tenant_id', $tenant->id)
            ->where('pack_purchase_id', $subscription->id)
            ->with(['videos', 'roteiros'])
            ->latest('id')
            ->get();

        return view('admin.subscriptions.show', compact('subscription', 'productions'));
    }

    /**
     * Cancelar a assinatura do cliente a pedido ou por rescisão.
     */
    public function cancel(Request $request, PackPurchase $subscription): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        abort_if($subscription->tenant_id !== $tenant->id, 403);

        $subscription->cancelSubscription();

        return redirect()->route('admin.subscriptions.show', $subscription)
            ->with('success', __('Assinatura do cliente cancelada com sucesso.'));
    }

    /**
     * Avança manualmente para o próximo ciclo de cobrança/produção ou renova cota.
     */
    public function renew(Request $request, PackPurchase $subscription): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        abort_if($subscription->tenant_id !== $tenant->id, 403);

        $subscription->renewSubscriptionCycle();

        return redirect()->route('admin.subscriptions.show', $subscription)
            ->with('success', __('Ciclo da assinatura renovado e nova cota de vídeos liberada!'));
    }
}
