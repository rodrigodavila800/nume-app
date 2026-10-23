@extends('layouts.app')

@section('title', __('NUME | Novo Vídeo do Portfólio'))
@section('breadcrumb', __('Novo Portfólio'))

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Adicionar ao Portfólio') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Cadastre vídeos de case para atrair potenciais clientes na vitrine pública.') }}</p>
        </div>
        <a href="{{ route('showcase-videos.index') }}" class="text-xs font-bold text-zinc-400 hover:text-white transition flex items-center gap-1.5">
            ← {{ __('Voltar') }}
        </a>
    </div>

    <form method="POST" action="{{ route('showcase-videos.store') }}" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-5">
        @csrf
        @include('showcase-videos._form')

        <div class="flex items-center gap-3 pt-4 border-t border-zinc-800">
            <button type="submit"
                    class="flex-1 sm:flex-initial bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-lg shadow-orange-900/20 transition-all">
                {{ __('Salvar no Portfólio') }}
            </button>
            <a href="{{ route('showcase-videos.index') }}"
               class="flex-1 sm:flex-initial inline-flex items-center justify-center bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm px-6 py-2.5 rounded-xl transition">
                {{ __('Cancelar') }}
            </a>
        </div>
    </form>
</div>
@endsection
