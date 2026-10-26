<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Services\Crm\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRM da produtora: funil, ficha do lead e histórico.
 */
class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lead::class);

        $search = trim((string) $request->input('search', ''));
        $leads = Lead::query()
            ->whereNull('anonymized_at')
            ->with('owner')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($request->input('owner') === 'me', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get()
            ->groupBy('stage');

        return view('crm.index', [
            'columns' => Lead::stageLabels(),
            'leads' => $leads,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Lead::class);

        return view('crm.form', ['lead' => new Lead, 'owners' => $this->owners()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $validated = $this->validated($request);
        $lead = new Lead($validated);
        $lead->forceFill(['tenant_id' => $request->user()->tenant_id, 'stage_changed_at' => now()])->save();
        $this->leads->log($lead, 'system', __('Lead criado por :name.', ['name' => $request->user()->name]), $request->user());

        return redirect()->route('crm.leads.show', $lead)->with('success', __('Lead criado.'));
    }

    public function show(Lead $lead): View
    {
        $this->authorize('view', $lead);

        $lead->load(['activities.user', 'owner', 'cliente']);

        return view('crm.show', ['lead' => $lead, 'owners' => $this->owners()]);
    }

    public function edit(Lead $lead): View
    {
        $this->authorize('update', $lead);

        return view('crm.form', ['lead' => $lead, 'owners' => $this->owners()]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $lead->update($this->validated($request));

        return redirect()->route('crm.leads.show', $lead)->with('success', __('Lead atualizado.'));
    }

    public function moveStage(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'stage' => ['required', Rule::in(Lead::STAGES)],
            'lost_reason' => ['nullable', 'required_if:stage,perdido', 'string', 'max:255'],
        ]);

        $this->leads->moveTo($lead, $validated['stage'], $request->user(), $validated['lost_reason'] ?? null);

        return back()->with('success', __('Etapa atualizada.'));
    }

    public function addActivity(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['note', 'call', 'email', 'meeting', 'whatsapp'])],
            'body' => ['required', 'string', 'max:5000'],
            'next_action_at' => ['nullable', 'date'],
        ]);

        $this->leads->log($lead, $validated['type'], $validated['body'], $request->user());
        if (array_key_exists('next_action_at', $validated)) {
            $lead->update(['next_action_at' => $validated['next_action_at']]);
        }

        return back()->with('success', __('Registro adicionado ao histórico.'));
    }

    /**
     * Exporta os dados do lead em JSON (pedido do titular). Só admin.
     */
    public function export(Lead $lead): Response
    {
        $this->authorize('managePersonalData', $lead);

        return response()->json($this->leads->export($lead), 200, [
            'Content-Disposition' => 'attachment; filename="lead-'.$lead->id.'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Anonimiza o lead (direito ao apagamento). Só admin e irreversível.
     */
    public function anonymize(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('managePersonalData', $lead);

        $request->validate(['confirm' => ['accepted']]);
        $this->leads->anonymize($lead, $request->user());

        return redirect()->route('crm.leads.show', $lead)->with('success', __('Dados pessoais anonimizados.'));
    }

    private function validated(Request $request): array
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'source' => ['required', Rule::in(Lead::SOURCES)],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'legal_basis' => ['required', Rule::in(Lead::LEGAL_BASES)],
            'consent_given' => ['nullable', 'boolean'],
            'next_action_at' => ['nullable', 'date'],
        ]);

        // Base "consentimento" exige registrar quando ele foi dado.
        $validated['consent_at'] = $validated['legal_basis'] === 'consent' && $request->boolean('consent_given') ? now() : null;
        unset($validated['consent_given']);
        $validated['email'] = isset($validated['email']) ? mb_strtolower($validated['email']) : null;

        return $validated;
    }

    private function owners()
    {
        return User::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->whereIn('role', ['admin', 'editor'])
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
