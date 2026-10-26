@extends('layouts.app')

@section('title', __('Tipos de vídeo') . ' | NUME')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2 shadow-xs">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-zinc-900">{{ __('Tipos de vídeo') }}</h1>
            <p class="text-xs text-zinc-500 mt-1 max-w-2xl">
                {{ __('Cada tipo define a estrutura do roteiro em blocos e a duração. A Sofi segue esses blocos ao gerar roteiros, e o cliente escolhe o tipo do vídeo.') }}
            </p>
        </div>
        @can('create', \App\Models\VideoType::class)
            <a href="{{ route('video_types.create') }}"
                class="inline-flex justify-center items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-extrabold text-white bg-orange-600 hover:bg-orange-500 shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                {{ __('Novo tipo de vídeo') }}
            </a>
        @endcan
    </div>

    <div class="space-y-3">
        @foreach($types as $type)
            <div class="bg-white rounded-2xl border border-zinc-200 shadow-xs p-5 space-y-3 {{ $type->is_active ? '' : 'opacity-60' }}">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-base font-bold text-zinc-900">{{ $type->name }}</h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-zinc-100 text-zinc-600 border border-zinc-200">{{ $type->duration_seconds }} s</span>
                            @if(! $type->is_active)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">{{ __('Inativo') }}</span>
                            @endif
                        </div>
                        @if($type->description)
                            <p class="text-xs text-zinc-600 mt-1">{{ $type->description }}</p>
                        @endif
                    </div>

                    @can('update', $type)
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('video_types.edit', $type) }}" class="px-3 py-1.5 rounded-lg text-[11px] font-bold text-zinc-700 bg-zinc-100 hover:bg-zinc-200 transition">{{ __('Editar') }}</a>
                            <form action="{{ route('video_types.toggle', $type) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-3 py-1.5 rounded-lg text-[11px] font-bold text-zinc-700 bg-zinc-100 hover:bg-zinc-200 transition">
                                    {{ $type->is_active ? __('Desativar') : __('Ativar') }}
                                </button>
                            </form>
                            <form action="{{ route('video_types.destroy', $type) }}" method="POST"
                                onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Excluir este tipo de vídeo? Roteiros já escritos mantêm a estrutura usada.')) }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 rounded-lg text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition">{{ __('Excluir') }}</button>
                            </form>
                        </div>
                    @endcan
                </div>

                <ol class="flex flex-wrap gap-2">
                    @foreach($type->blocks as $block)
                        <li class="px-2.5 py-1 rounded-lg text-[11px] bg-orange-50 text-orange-900 border border-orange-100" title="{{ $block['purpose'] }}">
                            <span class="font-bold">{{ $block['name'] }}</span>
                            <span class="text-orange-700">{{ $block['start'] }}–{{ $block['end'] }} s</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
</div>
@endsection
