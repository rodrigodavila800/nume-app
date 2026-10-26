<?php

namespace App\Http\Controllers;

use App\Models\VideoType;
use App\Services\VideoTypes\VideoTypeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configurações > Tipos de vídeo: o admin da produtora define os tipos e a estrutura em blocos
 * que a Sofi segue ao gerar roteiros.
 */
class VideoTypeController extends Controller
{
    public function __construct(private readonly VideoTypeCatalog $catalog) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VideoType::class);

        return view('settings.video_types.index', [
            'types' => $this->catalog->all($request->user()->tenant_id),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', VideoType::class);

        return view('settings.video_types.form', [
            'type' => new VideoType(['duration_seconds' => 30, 'blocks' => VideoType::salesBlocks(), 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', VideoType::class);

        $tenantId = $request->user()->tenant_id;
        $data = $this->validated($request);

        VideoType::create(array_merge($data, [
            'tenant_id' => $tenantId,
            'slug' => $this->catalog->uniqueSlug($tenantId, $data['name']),
        ]));

        return redirect()->route('video_types.index')->with('success', __('Tipo de vídeo criado.'));
    }

    public function edit(VideoType $videoType): View
    {
        $this->authorize('update', $videoType);

        return view('settings.video_types.form', ['type' => $videoType]);
    }

    public function update(Request $request, VideoType $videoType): RedirectResponse
    {
        $this->authorize('update', $videoType);

        $data = $this->validated($request);
        $videoType->update(array_merge($data, [
            'slug' => $this->catalog->uniqueSlug($videoType->tenant_id, $data['name'], $videoType->id),
        ]));

        return redirect()->route('video_types.index')->with('success', __('Tipo de vídeo atualizado.'));
    }

    public function toggle(VideoType $videoType): RedirectResponse
    {
        $this->authorize('update', $videoType);

        $videoType->update(['is_active' => ! $videoType->is_active]);

        return redirect()->route('video_types.index')->with('success', $videoType->is_active
            ? __('Tipo de vídeo ativado.')
            : __('Tipo de vídeo desativado. Roteiros já escritos com ele não mudam.'));
    }

    public function destroy(VideoType $videoType): RedirectResponse
    {
        $this->authorize('delete', $videoType);

        // Roteiros e produções ficam sem tipo, mas os roteiros mantêm a cópia da estrutura usada.
        $videoType->delete();

        return redirect()->route('video_types.index')->with('success', __('Tipo de vídeo excluído. Roteiros já escritos mantêm a estrutura usada.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'sofi_guidance' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
            'blocks' => ['required', 'array', 'max:'.VideoType::MAX_BLOCKS],
            'blocks.*.key' => ['nullable', 'string', 'max:40'],
            'blocks.*.name' => ['nullable', 'string', 'max:60'],
            'blocks.*.start' => ['nullable', 'integer', 'min:0'],
            'blocks.*.end' => ['nullable', 'integer', 'min:0'],
            'blocks.*.purpose' => ['nullable', 'string', 'max:300'],
        ]);

        return [
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'duration_seconds' => (int) $validated['duration_seconds'],
            'sofi_guidance' => $validated['sofi_guidance'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'blocks' => $this->catalog->normalizeBlocks($validated['blocks'], (int) $validated['duration_seconds']),
        ];
    }
}
