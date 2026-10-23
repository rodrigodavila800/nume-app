<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class AddonController extends Controller
{
    public function index(): View
    {
        $addons = Addon::withCount('packs')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => $addons->count(),
            'active' => $addons->where('is_active', true)->count(),
            'avg_price' => $addons->avg('default_price') ?? 0,
        ];

        return view('addons.index', compact('addons', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['currency'] = $validated['currency'] ?? 'eur';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['slug'] = Str::slug($validated['name']);

        Addon::create($validated);

        return redirect()->route('addons.index')->with('success', __('Serviço Add-on cadastrado com sucesso!'));
    }

    public function update(Request $request, Addon $addon): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['currency'] = $validated['currency'] ?? 'eur';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active');
        $validated['slug'] = Str::slug($validated['name']);

        $addon->update($validated);

        return redirect()->route('addons.index')->with('success', __('Serviço Add-on atualizado com sucesso!'));
    }

    public function destroy(Addon $addon): RedirectResponse
    {
        $packsCount = $addon->packs()->count();

        if ($packsCount > 0) {
            return back()->with('error', __('Não é possível excluir este Add-on pois ele está associado a :count pacote(s). Desative-o ou desvincule-o primeiro.', ['count' => $packsCount]));
        }

        $addon->delete();

        return redirect()->route('addons.index')->with('success', __('Serviço Add-on removido com sucesso!'));
    }

    public function toggleActive(Addon $addon): RedirectResponse
    {
        $addon->update([
            'is_active' => ! $addon->is_active,
        ]);

        $status = $addon->is_active ? __('ativado') : __('desativado');
        return back()->with('success', __("Add-on :name foi :status com sucesso!", ['name' => $addon->name, 'status' => $status]));
    }
}
