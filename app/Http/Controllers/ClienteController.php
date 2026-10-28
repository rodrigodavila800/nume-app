<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\MarketingPlanItem;
use App\Models\Production;
use App\Models\Quote;
use App\Models\Roteiro;
use App\Models\SupportTicket;
use App\Services\Crm\LeadAutomation;
use App\Services\MarketingPlanGeneratorService;
use App\Services\SocialPublishing\PublishingLimits;
use App\Services\SofiCopywriterService;
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

    /**
     * Produtora gera a Produção + Roteiro a partir de um item aprovado do Plano de Marketing do cliente.
     */
    public function convertMarketingPlanItem(
        Request $request,
        Cliente $cliente,
        MarketingPlanItem $item,
        SofiCopywriterService $copywriter,
    ): RedirectResponse {
        $this->authorize('view', $cliente);
        $this->authorize('create', Production::class);
        $this->authorize('create', Roteiro::class);

        $plan = MarketingPlan::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->find($item->marketing_plan_id);

        if (! $plan) {
            abort(404);
        }

        if ($item->production_id && $item->production) {
            return redirect()->route('productions.show', $item->production)
                ->with('success', __('Este item do plano já possui produção vinculada.'));
        }

        if (! $plan->isApproved()) {
            return redirect()->route('clientes.show', $cliente)
                ->with('error', __('O plano de marketing precisa ser aprovado pelo cliente antes de converter itens em produção.'));
        }

        // Validação de cobertura comercial: pacote ativo OU orçamento aceito (ou liberação explícita da equipe)
        $hasActivePack = $cliente->packPurchases()
            ->whereIn('status', ['paid', 'active'])
            ->whereColumn('videos_used', '<', 'video_quantity')
            ->exists();

        $hasAcceptedQuote = Quote::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->where('marketing_plan_id', $plan->id)
            ->where('status', 'accepted')
            ->exists();

        if (! $hasActivePack && ! $hasAcceptedQuote && ! $request->boolean('force_without_payment')) {
            return redirect()->route('clientes.show', $cliente)
                ->with('error', __('Este cliente ainda não possui orçamento aceito nem pacote ativo. Envie e aprove a proposta em Orçamentos antes de iniciar a produção.'));
        }

        $videoType = $item->videoType()->where('tenant_id', $cliente->tenant_id)->first();
        $keywords = is_array($item->hashtags) ? implode(', ', $item->hashtags) : null;
        $description = "Vídeo planejado na Grade Editorial (Semana {$item->week_number} - Funil ".strtoupper((string) $item->funnel_stage).'). '.($item->seo_caption ?? '');

        $production = Production::create([
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'pack_purchase_id' => $plan->pack_purchase_id,
            'video_type_id' => $videoType?->id,
            'created_by' => $request->user()->id,
            'title' => $item->topic,
            'description' => $description,
            'status' => 'draft',
            'seo_keywords' => $keywords,
        ]);

        $generated = $copywriter->generate(
            title: $production->title,
            description: $production->description,
            keywords: $keywords,
            framework: 'pattern_interrupt',
            tone: 'persuasive',
            language: MarketingPlanGeneratorService::isSpanishLocale($cliente) ? 'es' : session('locale', 'pt_BR'),
            cliente: $cliente,
            videoType: $videoType
        );

        $roteiro = Roteiro::create([
            'tenant_id' => $cliente->tenant_id,
            'production_id' => $production->id,
            'video_type_id' => $videoType?->id,
            'structure' => $videoType?->snapshot(),
            'created_by' => $request->user()->id,
            'status' => 'draft',
            'content' => $generated['full_script'] ?? $description,
        ]);

        $item->update([
            'production_id' => $production->id,
            'status' => 'in_production',
        ]);

        return redirect()->route('roteiros.show', [$production, $roteiro])
            ->with('success', __('Produção e roteiro gerados pela Sofi! Revise (ou edite se desejar) e submeta para aprovação do cliente.'));
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
