@extends('layouts.portal')

@section('title', __('Publicar no Pinterest') . ' | NUME')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Publicar no Pinterest') }}</h1>
        <p class="text-xs sm:text-sm text-zinc-600 mt-1">
            {{ __('O pin será criado na conta') }}
            <span class="font-bold text-zinc-900">{{ $connection->handle ?? $connection->display_name }}</span>
        </p>
    </div>

    <form action="{{ route('portal.videos.pinterest.store', $video) }}" method="POST" class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        @csrf

        <div class="lg:col-span-2">
            <div class="bg-zinc-950 rounded-2xl overflow-hidden border border-zinc-200 shadow-sm aspect-[9/16] max-h-[70vh] mx-auto">
                <video src="{{ $video->playbackUrl() }}" controls playsinline controlsList="nodownload" class="w-full h-full object-contain"></video>
            </div>
            <p class="text-[11px] text-zinc-500 mt-2 text-center">{{ __('A capa do pin é um quadro do início do vídeo.') }}</p>
        </div>

        <div class="lg:col-span-3 space-y-5">
            <div>
                <label for="board_id" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Pasta') }}</label>
                <select id="board_id" name="board_id" required
                    class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 bg-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors">
                    <option value="" disabled @selected(! old('board_id'))>{{ __('Escolha a pasta do pin') }}</option>
                    @foreach($boards as $board)
                        <option value="{{ $board['id'] }}" @selected(old('board_id') === $board['id'])>{{ $board['name'] }}</option>
                    @endforeach
                </select>
                @error('board_id')<p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="title" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Título') }}</label>
                <input id="title" name="title" type="text" maxlength="100" value="{{ old('title', $title) }}"
                    class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors">
                @error('title')<p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="description" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Descrição') }}</label>
                <textarea id="description" name="description" rows="5" maxlength="800"
                    class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors">{{ old('description', $description) }}</textarea>
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Sugerida pela Sofi. Você pode editar.') }}</p>
                @error('description')<p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="link" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Link de destino (opcional)') }}</label>
                <input id="link" name="link" type="url" maxlength="2048" placeholder="https://" value="{{ old('link', $link) }}"
                    class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors">
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Para onde o pin leva quem clicar (site, agendamento, loja).') }}</p>
                @error('link')<p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-zinc-100">
                <a href="{{ route('portal.productions.show', $video->production->uuid) }}"
                    class="inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-700 bg-white border border-zinc-200 hover:bg-zinc-50 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-zinc-400">
                    {{ __('Cancelar') }}
                </a>
                <button type="submit"
                    class="inline-flex justify-center items-center px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-gradient-to-r from-orange-600 to-amber-500 hover:from-orange-500 hover:to-amber-400 shadow-sm transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                    {{ __('Publicar no Pinterest') }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
