<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Category;
use App\Models\Pack;
use App\Models\ShowcaseVideo;
use App\Services\SofiCopywriterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    /**
     * Listagem dos Planos de Assinatura Recorrente da produtora (Retainers / Modo Criador).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Pack::class);

        $plans = Pack::recurringPlans()
            ->with(['category', 'addons', 'showcaseVideo'])
            ->orderBy('price')
            ->paginate(15);

        $activePlans = Pack::recurringPlans()->where('status', 'active')->get();
        $stats = [
            'total_plans' => $activePlans->count(),
            'avg_monthly_quota' => $activePlans->avg('video_quantity') ? round($activePlans->avg('video_quantity'), 1) : 0,
            'avg_ticket' => $activePlans->avg('price') ? (float) $activePlans->avg('price') : 0.00,
        ];

        return view('admin.plans.index', compact('plans', 'stats'));
    }

    /**
     * Formulário de criação de novo Plano de Assinatura Recorrente.
     */
    public function create(): View
    {
        $this->authorize('create', Pack::class);

        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $availableAddons = Addon::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $showcaseVideos = ShowcaseVideo::where('is_published', true)->orderBy('title')->get();

        return view('admin.plans.create', compact('categories', 'availableAddons', 'showcaseVideos'));
    }

    /**
     * Armazena um novo Plano de Assinatura Recorrente.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Pack::class);

        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'showcase_video_id' => 'nullable|exists:showcase_videos,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'video_quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'billing_interval' => 'required|in:month,quarter',
            'commitment_months' => 'required|integer|min:1|max:36',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'exists:addons,id',
            'status' => 'required|in:active,inactive',
        ]);

        $features = array_values(array_filter(
            $request->input('features', []),
            fn ($item) => is_string($item) && trim($item) !== ''
        ));

        $validated['features'] = $features;
        $validated['billing_type'] = 'subscription';
        $validated['currency'] = $validated['currency'] ?? 'eur';
        $validated['vat_rate'] = $validated['vat_rate'] ?? 21.00;

        $plan = Pack::create($validated);

        if ($request->has('addon_ids')) {
            $plan->addons()->sync($request->input('addon_ids', []));
        }

        return redirect()->route('admin.plans.index')
            ->with('success', __('Plano de assinatura criado com sucesso!'));
    }

    /**
     * Formulário de edição de um Plano de Assinatura Recorrente.
     */
    public function edit(Pack $plan): View
    {
        $this->authorize('update', $plan);

        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $availableAddons = Addon::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $showcaseVideos = ShowcaseVideo::where('is_published', true)->orderBy('title')->get();

        return view('admin.plans.edit', compact('plan', 'categories', 'availableAddons', 'showcaseVideos'));
    }

    /**
     * Atualiza um Plano de Assinatura Recorrente.
     */
    public function update(Request $request, Pack $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'showcase_video_id' => 'nullable|exists:showcase_videos,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'video_quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'billing_interval' => 'required|in:month,quarter',
            'commitment_months' => 'required|integer|min:1|max:36',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'exists:addons,id',
            'status' => 'required|in:active,inactive',
        ]);

        $features = array_values(array_filter(
            $request->input('features', []),
            fn ($item) => is_string($item) && trim($item) !== ''
        ));

        $validated['features'] = $features;
        $validated['billing_type'] = 'subscription';

        $plan->update($validated);

        if ($request->has('addon_ids')) {
            $plan->addons()->sync($request->input('addon_ids', []));
        }

        return redirect()->route('admin.plans.index')
            ->with('success', __('Plano de assinatura atualizado com sucesso!'));
    }

    /**
     * Remove ou desativa um Plano.
     */
    public function destroy(Pack $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()->route('admin.plans.index')
            ->with('success', __('Plano de assinatura removido com sucesso.'));
    }
}
