<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Roteiro;
use App\Services\SofiCopywriterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BatchContentPlannerController extends Controller
{
    /**
     * Exibe a interface do Sofi Batch Content Planner.
     */
    public function index(Request $request): View
    {
        $tenantId = auth()->user()->tenant_id;

        $clientes = Cliente::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $selectedClienteId = $request->input('cliente_id') ?: old('cliente_id');
        $selectedCliente = $selectedClienteId
            ? $clientes->firstWhere('id', $selectedClienteId)
            : null;

        $generatedPlan = session('generated_plan', []);
        $planMeta = session('plan_meta', []);

        return view('batch_planner.index', compact(
            'clientes',
            'selectedCliente',
            'selectedClienteId',
            'generatedPlan',
            'planMeta'
        ));
    }

    /**
     * Gera o lote de 4, 8 ou 12 ideias de roteiros usando a Sofi IA.
     */
    public function generate(Request $request, SofiCopywriterService $sofiService): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'theme' => 'required|string|min:3|max:255',
            'quantity' => 'required|in:4,8,12',
            'language' => 'required|in:pt_BR,es',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cliente = Cliente::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($validated['cliente_id']);

        $plan = $sofiService->generateBatchEditorialPlan(
            cliente: $cliente,
            theme: $validated['theme'],
            quantity: (int) $validated['quantity'],
            language: $validated['language'],
            notes: $validated['notes'] ?? null
        );

        return redirect()->route('batch_planner.index', ['cliente_id' => $cliente->id])
            ->with('generated_plan', $plan)
            ->with('plan_meta', [
                'cliente_id' => $cliente->id,
                'cliente_name' => $cliente->company_name ?: $cliente->name,
                'theme' => $validated['theme'],
                'quantity' => (int) $validated['quantity'],
                'language' => $validated['language'],
            ])
            ->with('success', __('Plano gerado com sucesso pela Sofi! Revise os roteiros abaixo e confirme a criação do lote no calendário.'));
    }

    /**
     * Grava em lote as Produções e Roteiros gerados no banco de dados.
     */
    public function commit(Request $request): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|max:255',
            'items.*.description' => 'nullable|string|max:1000',
            'items.*.script_content' => 'required|string',
            'items.*.suggested_date' => 'required|date',
        ]);

        $cliente = Cliente::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($validated['cliente_id']);

        $createdCount = 0;

        DB::transaction(function () use ($validated, $tenantId, $userId, $cliente, &$createdCount) {
            foreach ($validated['items'] as $itemData) {
                // 1. Cria a Production
                $production = Production::create([
                    'tenant_id' => $tenantId,
                    'cliente_id' => $cliente->id,
                    'created_by' => $userId,
                    'title' => $itemData['title'],
                    'description' => $itemData['description'] ?? null,
                    'status' => 'in_production',
                    'target_publication_date' => $itemData['suggested_date'],
                ]);

                // 2. Cria o Roteiro associado pronto para aprovação
                Roteiro::create([
                    'tenant_id' => $tenantId,
                    'production_id' => $production->id,
                    'created_by' => $userId,
                    'content' => $itemData['script_content'],
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]);

                $createdCount++;
            }
        });

        return redirect()->route('calendar.index', ['cliente_id' => $cliente->id])
            ->with('success', __(':count produções e roteiros criados com sucesso pelo Sofi Batch Planner e agendados no Calendário!', [
                'count' => $createdCount,
            ]));
    }
}
