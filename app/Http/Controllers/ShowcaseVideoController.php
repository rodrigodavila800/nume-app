<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ShowcaseVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowcaseVideoController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ShowcaseVideo::class);

        $format = $request->query('format'); // 'vertical', 'horizontal', or null/all
        $verticalCount = ShowcaseVideo::where('is_vertical', true)->count();
        $horizontalCount = ShowcaseVideo::where('is_vertical', false)->count();
        $totalCount = ShowcaseVideo::count();

        $query = ShowcaseVideo::with('category')->orderBy('sort_order')->orderByDesc('id');

        if ($format === 'vertical') {
            $query->where('is_vertical', true);
        } elseif ($format === 'horizontal') {
            $query->where('is_vertical', false);
        }

        return view('showcase-videos.index', [
            'videos' => $query->paginate(24)->appends($request->query()),
            'currentFormat' => $format,
            'verticalCount' => $verticalCount,
            'horizontalCount' => $horizontalCount,
            'totalCount' => $totalCount,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ShowcaseVideo::class);
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();

        return view('showcase-videos.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ShowcaseVideo::class);
        ShowcaseVideo::create($this->validated($request));

        return redirect()->route('showcase-videos.index')->with('success', 'Video de portfolio publicado com sucesso.');
    }

    public function edit(ShowcaseVideo $showcaseVideo): View
    {
        $this->authorize('update', $showcaseVideo);
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();

        return view('showcase-videos.edit', compact('showcaseVideo', 'categories'));
    }

    public function update(Request $request, ShowcaseVideo $showcaseVideo): RedirectResponse
    {
        $this->authorize('update', $showcaseVideo);
        $showcaseVideo->update($this->validated($request));

        return redirect()->route('showcase-videos.index')->with('success', 'Video de portfolio atualizado.');
    }

    public function destroy(ShowcaseVideo $showcaseVideo): RedirectResponse
    {
        $this->authorize('delete', $showcaseVideo);
        $showcaseVideo->delete();

        return redirect()->route('showcase-videos.index')->with('success', 'Video de portfolio removido.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'video_url' => ['required', 'url', 'max:255'],
            'thumbnail_url' => ['nullable', 'url', 'max:255'],
            'is_vertical' => ['nullable', 'boolean'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'category' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $url = strtolower($request->input('video_url', ''));
        $inferredVertical = str_contains($url, '/shorts/') || str_contains($url, '/reel/') || str_contains($url, 'tiktok.com');

        return $data + [
            'is_vertical' => $request->has('is_vertical') ? $request->boolean('is_vertical') : $inferredVertical,
            'is_featured' => $request->boolean('is_featured'),
            'is_published' => $request->boolean('is_published'),
        ];
    }
}
