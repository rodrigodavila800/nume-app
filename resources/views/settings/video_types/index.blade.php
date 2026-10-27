@extends('layouts.app')

@section('title', __('Tipos de vídeo') . ' | NUME')
@section('breadcrumb', __('Tipos de Vídeo'))

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">

    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-white">{{ __('Tipos de vídeo') }}</h1>
            <p class="text-xs text-zinc-400 mt-1 max-w-2xl">
                {{ __('Cada tipo define a estrutura do roteiro em blocos e a duração. A Sofi segue esses blocos ao gerar roteiros, e o cliente escolhe o tipo do vídeo.') }}
            </p>
        </div>
        @can('create', \App\Models\VideoType::class)
            <a href="{{ route('video_types.create') }}"
                class="inline-flex justify-center items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-extrabold text-white bg-orange-600 hover:bg-orange-500 shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                + {{ __('Novo tipo de vídeo') }}
            </a>
        @endcan
    </div>

    <x-settings-tabs active="video_types" />

    <div class="space-y-3">
        @foreach($types as $type)
            <div class="bg-zinc-900 rounded-2xl border border-zinc-800 p-5 space-y-3 {{ $type->is_active ? '' : 'opacity-60' }}">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-base font-bold text-white">{{ $type->name }}</h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-zinc-800 text-zinc-300 border border-zinc-700">{{ $type->duration_seconds }} s</span>
                            @if(! $type->is_active)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">{{ __('Inativo') }}</span>
                            @endif
                        </div>
                        @if($type->description)
                            <p class="text-xs text-zinc-400 mt-1">{{ $type->description }}</p>
                        @endif
                    </div>

                    @can('update', $type)
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('video_types.edit', $type) }}" class="px-3 py-1.5 rounded-lg text-[11px] font-bold text-zinc-200 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 transition">{{ __('Editar') }}</a>
                            <form action="{{ route('video_types.toggle', $type) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-3 py-1.5 rounded-lg text-[11px] font-bold text-zinc-200 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 transition cursor-pointer">
                                    {{ $type->is_active ? __('Desativar') : __('Ativar') }}
                                </button>
                            </form>
                            <form action="{{ route('video_types.destroy', $type) }}" method="POST"
                                onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Excluir este tipo de vídeo? Roteiros já escritos mantêm a estrutura usada.')) }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 rounded-lg text-[11px] font-bold text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 transition cursor-pointer">{{ __('Excluir') }}</button>
                            </form>
                        </div>
                    @endcan
                </div>

                <ol class="flex flex-wrap gap-2">
                    @foreach($type->blocks as $block)
                        <li class="px-2.5 py-1 rounded-lg text-[11px] bg-orange-500/10 text-orange-300 border border-orange-500/25" title="{{ $block['purpose'] }}">
                            <span class="font-bold text-white">{{ $block['name'] }}</span>
                            <span class="text-orange-400 ml-1">{{ $block['start'] }}–{{ $block['end'] }} s</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
</div>
@endsection
