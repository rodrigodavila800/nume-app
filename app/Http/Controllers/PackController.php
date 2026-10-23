<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Pack;
use App\Models\ShowcaseVideo;
use App\Services\SofiCopywriterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Pack::class);

        $packs = Pack::with(['category', 'addons'])->orderBy('price')->paginate(15);

        return view('packs.index', compact('packs'));
    }

    public function create(): View
    {
        $this->authorize('create', Pack::class);
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $availableAddons = Addon::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $showcaseVideos = ShowcaseVideo::where('is_published', true)->orderBy('title')->get();

        return view('packs.create', compact('categories', 'availableAddons', 'showcaseVideos'));
    }

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
            'currency' => 'nullable|string|in:eur,brl,EUR,BRL',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'billing_type' => 'nullable|in:one_time,subscription',
            'billing_interval' => 'nullable|in:month,quarter',
            'commitment_months' => 'nullable|integer|min:1|max:36',
            'billing_mode' => 'nullable|in:full_upfront,split_50_50,pilot_entry',
            'deposit_percentage' => 'nullable|integer|min:10|max:90',
            'pilot_discount_on_subscription' => 'nullable|numeric|min:0',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'exists:addons,id',
            'addon_prices' => 'nullable|array',
            'addon_prices.*' => 'nullable|numeric|min:0',
            // Suporte para criação direta via array/Sofi
            'addons' => 'nullable|array',
            'addons.*.name' => 'nullable|string|max:255',
            'addons.*.price' => 'nullable|numeric|min:0',
        ]);

        // Sanitização de features
        $features = array_values(array_filter(
            $request->input('features', []),
            fn ($item) => is_string($item) && trim($item) !== ''
        ));

        $validated['features'] = $features;
        $validated['currency'] = strtolower($validated['currency'] ?? 'eur');
        $validated['vat_rate'] = $validated['vat_rate'] ?? 21.00;
        $validated['billing_type'] = $validated['billing_type'] ?? 'one_time';
        $validated['billing_interval'] = $validated['billing_interval'] ?? 'month';
        $validated['commitment_months'] = (int) ($validated['commitment_months'] ?? 1);
        $validated['billing_mode'] = $validated['billing_mode'] ?? 'full_upfront';
        $validated['deposit_percentage'] = (int) ($validated['deposit_percentage'] ?? 50);
        $validated['pilot_discount_on_subscription'] = (float) ($validated['pilot_discount_on_subscription'] ?? 0);
        $validated['status'] = $request->input('status', 'active');

        $pack = Pack::create($validated);

        $this->syncPackAddons($pack, $request);

        return redirect()->route('packs.index')->with('success', __('Pacote criado com sucesso!'));
    }

    public function edit(Pack $pack): View
    {
        $this->authorize('update', $pack);
        $pack->load(['category', 'addons', 'showcaseVideo']);
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $availableAddons = Addon::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $showcaseVideos = ShowcaseVideo::where('is_published', true)->orderBy('title')->get();

        return view('packs.edit', compact('pack', 'categories', 'availableAddons', 'showcaseVideos'));
    }

    public function update(Request $request, Pack $pack): RedirectResponse
    {
        $this->authorize('update', $pack);

        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'showcase_video_id' => 'nullable|exists:showcase_videos,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'video_quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|in:eur,brl,EUR,BRL',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'billing_type' => 'nullable|in:one_time,subscription',
            'billing_interval' => 'nullable|in:month,quarter',
            'commitment_months' => 'nullable|integer|min:1|max:36',
            'billing_mode' => 'nullable|in:full_upfront,split_50_50,pilot_entry',
            'deposit_percentage' => 'nullable|integer|min:10|max:90',
            'pilot_discount_on_subscription' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'exists:addons,id',
            'addon_prices' => 'nullable|array',
            'addon_prices.*' => 'nullable|numeric|min:0',
            'addons' => 'nullable|array',
            'addons.*.name' => 'nullable|string|max:255',
            'addons.*.price' => 'nullable|numeric|min:0',
        ]);

        // Sanitização de features
        $features = array_values(array_filter(
            $request->input('features', []),
            fn ($item) => is_string($item) && trim($item) !== ''
        ));

        $validated['features'] = $features;
        $validated['currency'] = strtolower($validated['currency'] ?? 'eur');
        $validated['vat_rate'] = $validated['vat_rate'] ?? 21.00;
        $validated['billing_type'] = $validated['billing_type'] ?? 'one_time';
        $validated['billing_interval'] = $validated['billing_interval'] ?? 'month';
        $validated['commitment_months'] = (int) ($validated['commitment_months'] ?? 1);
        $validated['billing_mode'] = $validated['billing_mode'] ?? 'full_upfront';
        $validated['deposit_percentage'] = (int) ($validated['deposit_percentage'] ?? 50);
        $validated['pilot_discount_on_subscription'] = (float) ($validated['pilot_discount_on_subscription'] ?? 0);

        $pack->update($validated);

        $this->syncPackAddons($pack, $request);

        return redirect()->route('packs.index')->with('success', __('Pacote atualizado com sucesso!'));
    }

    public function destroy(Pack $pack): RedirectResponse
    {
        $this->authorize('delete', $pack);

        $pack->delete();

        return redirect()->route('packs.index')->with('success', __('Pacote removido com sucesso!'));
    }

    /**
     * Sincroniza os Add-ons relacionais no pack_addon e mantém o campo legado
     */
    private function syncPackAddons(Pack $pack, Request $request): void
    {
        $syncData = [];
        $structuredLegacy = [];

        // 1. Sincronização via seleção relacional (addon_ids)
        if ($request->has('addon_ids')) {
            $addonIds = (array) $request->input('addon_ids', []);
            $addonPrices = (array) $request->input('addon_prices', []);

            $addons = Addon::whereIn('id', $addonIds)->get();
            foreach ($addons as $index => $addon) {
                $customPrice = isset($addonPrices[$addon->id]) && $addonPrices[$addon->id] !== ''
                    ? (float) $addonPrices[$addon->id]
                    : null;

                $finalPrice = $customPrice !== null ? $customPrice : (float) $addon->default_price;

                $syncData[$addon->id] = [
                    'custom_price' => $customPrice,
                    'is_default_selected' => false,
                    'sort_order' => $index + 1,
                ];

                $structuredLegacy[] = [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'price' => $finalPrice,
                ];
            }
        } elseif ($request->has('addons')) {
            // 2. Fallback / Sofi IA: Array de [{name, price}]
            $rawAddons = (array) $request->input('addons', []);
            foreach ($rawAddons as $index => $raw) {
                if (empty($raw['name'])) {
                    continue;
                }
                $addon = Addon::firstOrCreate(
                    [
                        'tenant_id' => $pack->tenant_id,
                        'name' => trim($raw['name']),
                    ],
                    [
                        'default_price' => (float) ($raw['price'] ?? 0),
                        'currency' => $pack->currency ?? 'eur',
                        'is_active' => true,
                        'sort_order' => $index + 1,
                    ]
                );

                $customPrice = isset($raw['price']) ? (float) $raw['price'] : null;

                $syncData[$addon->id] = [
                    'custom_price' => $customPrice,
                    'is_default_selected' => false,
                    'sort_order' => $index + 1,
                ];

                $structuredLegacy[] = [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'price' => $customPrice ?? (float) $addon->default_price,
                ];
            }
        }

        if (! empty($syncData)) {
            $pack->addons()->sync($syncData);
            $pack->update(['addons' => $structuredLegacy]);
        }
    }

    /**
     * Endpoint AJAX para geração assistida de copy, entregáveis e Add-ons com a Sofi IA
     */
    public function sofiGenerate(Request $request, SofiCopywriterService $sofi): JsonResponse
    {
        $this->authorize('create', Pack::class);

        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'video_quantity' => 'nullable|integer|min:1',
            'language' => 'nullable|string|in:es,pt_BR,en',
        ]);

        $categoryName = '';
        if (! empty($validated['category_id'])) {
            $category = Category::find($validated['category_id']);
            $categoryName = $category?->name ?? '';
        }

        $generated = $sofi->generatePackContent(
            categoryName: $categoryName,
            packName: $validated['name'] ?? '',
            price: (float) ($validated['price'] ?? 0),
            videoQuantity: (int) ($validated['video_quantity'] ?? 1),
            language: $validated['language'] ?? 'es'
        );

        return response()->json([
            'success' => true,
            'data' => $generated,
        ]);
    }
}
