@extends('layouts.portal')

@section('title', __('Escolha a Página do Facebook') . ' | NUME')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">
            {{ __('Qual Página é da sua marca?') }}
        </h1>
        <p class="text-xs sm:text-sm text-zinc-600 mt-1">
            {{ __('Você administra mais de uma Página. Escolha a que deve receber os vídeos. O Instagram profissional ligado a ela é conectado junto.') }}
        </p>
    </div>

    <form action="{{ route('portal.social.meta.pages.store') }}" method="POST" class="space-y-3">
        @csrf

        @foreach($pages as $page)
            <label class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-zinc-200 shadow-xs cursor-pointer transition-all duration-200 hover:border-orange-300 hover:shadow-md has-[:checked]:border-orange-500 has-[:checked]:ring-2 has-[:checked]:ring-orange-500/20">
                <input type="radio" name="page_id" value="{{ $page['id'] }}" class="w-4 h-4 text-orange-600 focus:ring-orange-500" @checked($loop->first) required>
                @if($page['picture'])
                    <img src="{{ $page['picture'] }}" alt="" class="w-10 h-10 rounded-full border border-zinc-200 object-cover">
                @else
                    <span class="w-10 h-10 rounded-full bg-zinc-100 border border-zinc-200"></span>
                @endif
                <span class="flex-1 min-w-0">
                    <span class="block text-sm font-bold text-zinc-900 truncate">{{ $page['name'] ?? __('Página sem nome') }}</span>
                    <span class="block text-[11px] text-zinc-500 mt-0.5">
                        @if($page['instagram'])
                            {{ __('Instagram ligado:') }} <span class="font-semibold text-zinc-700">{{ '@'.ltrim($page['instagram'], '@') }}</span>
                        @else
                            {{ __('Sem Instagram profissional ligado') }}
                        @endif
                    </span>
                </span>
            </label>
        @endforeach

        @error('page_id')
            <p class="text-xs text-rose-600 font-medium">{{ $message }}</p>
        @enderror

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
            <a href="{{ route('portal.social.index') }}"
                class="inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-700 bg-white border border-zinc-200 hover:bg-zinc-50 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-zinc-400">
                {{ __('Cancelar') }}
            </a>
            <button type="submit"
                class="inline-flex justify-center items-center px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-gradient-to-r from-orange-600 to-amber-500 hover:from-orange-500 hover:to-amber-400 shadow-sm transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                {{ __('Conectar esta Página') }}
            </button>
        </div>
    </form>
</div>
@endsection
