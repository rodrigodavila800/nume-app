<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $cliente = $request->user('cliente');

        $productions = $cliente->productions()->latest()->paginate(10);

        $purchases = $cliente->packPurchases()
            ->with('pack')
            ->where(function ($q) {
                $q->where('status', 'paid')
                    ->orWhere('subscription_status', 'active');
            })
            ->latest()
            ->paginate(10, ['*'], 'purchases_page');

        // Contratos e Assinaturas ativas do cliente
        $activeSubscriptions = $cliente->packPurchases()
            ->with('pack')
            ->whereNotNull('subscription_status')
            ->whereIn('subscription_status', ['active', 'past_due'])
            ->latest()
            ->get();

        // Propostas e orçamentos recentes do cliente
        $recentQuotes = $cliente->allQuotes()
            ->whereIn('status', ['sent', 'accepted', 'rejected'])
            ->latest()
            ->take(4)
            ->get();

        return view('portal.dashboard', compact('productions', 'purchases', 'activeSubscriptions', 'recentQuotes'));
    }
}
