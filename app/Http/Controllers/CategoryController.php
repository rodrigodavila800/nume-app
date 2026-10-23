<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::withCount(['showcaseVideos', 'packs'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:general,showcase,pack'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['type'] = $validated['type'] ?? 'general';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['slug'] = Str::slug($validated['name']);

        Category::create($validated);

        return redirect()->route('categories.index')->with('success', __('Categoria criada com sucesso!'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:general,showcase,pack'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['type'] = $validated['type'] ?? 'general';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active');
        $validated['slug'] = Str::slug($validated['name']);

        $category->update($validated);

        return redirect()->route('categories.index')->with('success', __('Categoria atualizada com sucesso!'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $videosCount = $category->showcaseVideos()->count();
        $packsCount = $category->packs()->count();

        if ($videosCount > 0 || $packsCount > 0) {
            return back()->with('error', __('Não é possível excluir esta categoria pois ela possui vídeos ou pacotes vinculados. Desative-a em vez disso.'));
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', __('Categoria removida com sucesso!'));
    }
}
