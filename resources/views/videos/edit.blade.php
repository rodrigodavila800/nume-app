@extends('layouts.app')

@section('title', __('NUME | Editar Vídeo'))
@section('breadcrumb', __('Editar Vídeo'))

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Editar Vídeo') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Produção:') }} <strong class="text-orange-400">{{ $production->title }}</strong></p>
        </div>
        <a href="{{ route('videos.show', ['production' => $production, 'video' => $video]) }}" class="text-xs font-bold text-zinc-400 hover:text-white transition flex items-center gap-1.5">
            ← {{ __('Voltar') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('videos.update', ['production' => $production, 'video' => $video]) }}" method="POST" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-5">
        @csrf
        @method('PATCH')

        <div class="space-y-1.5">
            <label for="title" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Título do Vídeo') }} <span class="text-orange-500">*</span>
            </label>
            <input type="text" name="title" id="title" required
                   class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('title') border-rose-500 @enderror"
                   value="{{ old('title', $video->title) }}">
            @error('title') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1.5">
            <label for="description" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Descrição & Notas') }}
            </label>
            <textarea name="description" id="description" rows="3"
                      class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('description') border-rose-500 @enderror">{{ old('description', $video->description) }}</textarea>
            @error('description') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1.5">
            <label for="video_url" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('URL do Vídeo (YouTube, Shorts, Vimeo ou MP4)') }} <span class="text-orange-500">*</span>
            </label>
            <input type="url" name="video_url" id="video_url" required
                   placeholder="https://www.youtube.com/shorts/... ou https://youtu.be/..."
                   class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('video_url') border-rose-500 @enderror"
                   value="{{ old('video_url', $video->video_url) }}">
            @error('video_url') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <label for="thumbnail_url" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('URL da Thumbnail') }}
                </label>
                <input type="url" name="thumbnail_url" id="thumbnail_url"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('thumbnail_url') border-rose-500 @enderror"
                       value="{{ old('thumbnail_url', $video->thumbnail_url) }}">
            </div>

            <div class="space-y-1.5">
                <label for="duration_seconds" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Duração (em segundos)') }}
                </label>
                <input type="number" name="duration_seconds" id="duration_seconds" min="1"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('duration_seconds') border-rose-500 @enderror"
                       value="{{ old('duration_seconds', $video->duration_seconds) }}">
            </div>
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-zinc-800">
            <button type="submit"
                    class="flex-1 sm:flex-initial bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-lg shadow-orange-900/20 transition-all">
                {{ __('Salvar Alterações') }}
            </button>
            <a href="{{ route('videos.show', ['production' => $production, 'video' => $video]) }}"
               class="flex-1 sm:flex-initial inline-flex items-center justify-center bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm px-6 py-2.5 rounded-xl transition">
                {{ __('Cancelar') }}
            </a>
        </div>
    </form>
</div>
@endsection
