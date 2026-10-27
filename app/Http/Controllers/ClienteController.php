<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\SupportTicket;
use App\Services\Crm\LeadAutomation;
use App\Services\MarketingPlanGeneratorService;
use App\Services\SocialPublishing\PublishingLimits;
use App\Support\Anamnesis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Cliente::class);

        $clientes = Cliente::withCount('productions')
            ->with(['marketingPlans' => fn ($q) => $q->latest('id')->limit(1), 'socialConnections'])
            ->orderBy('name')
            ->paginate(15);

        return view('clientes.index', compact('clientes'));
    }

    public function show(Cliente $cliente): View
    {
        $this->authorize('view', $cliente);

        $cliente->load([
            'productions' => fn ($q) => $q->withCount(['roteiros', 'videos'])->latest(),
            'marketingPlans' => fn ($q) => $q->with(['items.videoType', 'items.production'])->orderBy('month_year', 'desc'),
            'socialConnections',
            'packPurchases.pack',
        ]);

        $latestPlan = $cliente->marketingPlans->first();
        $quotes = $cliente->allQuotes()->latest('id')->take(10)->get();
        $tickets = SupportTicket::where('tenant_id', $cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->latest('id')
            ->take(5)
            ->get();
        $limits = app(PublishingLimits::class)->summary($cliente);
        $anamnesisLabels = [
            'objectives' => Anamnesis::objectiveLabels(),
            'tones' => Anamnesis::toneLabels(),
            'frequencies' => Anamnesis::frequencyLabels(),
        ];

        return view('clientes.show', compact(
            'cliente',
            'latestPlan',
            'quotes',
            'tickets',
            'limits',
            'anamnesisLabels',
        ));
    }

    public function regenerateMarketingPlan(
        Request $request,
        Cliente $cliente,
        MarketingPlanGeneratorService $planGenerator,
    ): RedirectResponse {
        $this->authorize('view', $cliente);

        $validated = $request->validate([
            'adjustment_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $currentMonth = now()->format('Y-m');
        $existingPlan = MarketingPlan::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->where('month_year', $currentMonth)
            ->first();

        $latestPurchase = $cliente->packPurchases()->latest('id')->first();
        $videoQty = MarketingPlanGeneratorService::videoQuantityFor($cliente, $latestPurchase);

        if (! $existingPlan) {
            $planGenerator->generate($cliente, $videoQty, $latestPurchase, $currentMonth);

            return redirect()->route('clientes.show', $cliente)
                ->with('success', __('Plano de marketing gerado pela Sofi IA e enviado para aprovação do cliente no portal.'));
        }

        $notes = trim((string) ($validated['adjustment_notes'] ?? '')) ?: $existingPlan->adjustment_request;
        $planGenerator->regeneratePlan($existingPlan, $cliente, $videoQty, $notes);

        return redirect()->route('clientes.show', $cliente)
            ->with('success', __('Grade recalculada pela Sofi IA considerando os ajustes e devolvida para aprovação do cliente!'));
    }

    public function create(): View
    {
        $this->authorize('create', Cliente::class);

        return view('clientes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Cliente::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $cliente = Cliente::create([
            ...$validated,
            'password' => Hash::make(Str::random(32)),
        ]);

        Password::broker('clientes')->sendResetLink(['email' => $cliente->email, 'tenant_id' => $cliente->tenant_id]);

        app(LeadAutomation::class)->clienteRegistered($cliente, 'manual');

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente cadastrado! Um link de definição de senha foi enviado por e-mail.');
    }

    public function edit(Cliente $cliente): View
    {
        $this->authorize('update', $cliente);

        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->authorize('update', $cliente);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $cliente->update($validated);

        // Limites de publicação: só o admin da produtora ajusta (vazio = segue o contratado).
        if ($request->user()->isAdmin()) {
            $limits = $request->validate([
                'social_networks_limit_override' => 'nullable|integer|min:1|max:8',
                'monthly_post_limit_override' => 'nullable|integer|min:1|max:1000',
            ]);
            $cliente->forceFill([
                'social_networks_limit_override' => $limits['social_networks_limit_override'] ?? null,
                'monthly_post_limit_override' => $limits['monthly_post_limit_override'] ?? null,
            ])->save();
        }

        return redirect()->route('clientes.index')->with('success', 'Cliente atualizado com sucesso!');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $this->authorize('delete', $cliente);

        $cliente->delete();

        return redirect()->route('clientes.index')->with('success', 'Cliente removido com sucesso!');
    }
}
