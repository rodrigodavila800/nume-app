@extends('layouts.app')

@section('title', __('Roteiros de ' . $production->title . ' | NUME'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-800">
        <div>
            <a href="{{ route('productions.show', $production) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-400 hover:text-orange-400 transition mb-2">
                <span>←</span>
                <span>{{ __('Voltar para a Produção') }}</span>
            </a>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                {{ __('Roteiros de') }} {{ $production->title }}
            </h1>
            <p class="text-xs text-zinc-400 mt-1">
                {{ __('Gerencie roteiros, versões de copy Sofi e aprovações de conteúdo.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('roteiros.create', $production) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-orange-600 hover:bg-orange-500 text-white shadow-lg shadow-orange-900/20 transition cursor-pointer">
                <span>+</span>
                <span>{{ __('Novo Roteiro com Sofi') }}</span>
            </a>
        </div>
    </div>

    @if ($roteiros->isEmpty())
        <div class="bg-zinc-900 border border-zinc-800 p-12 rounded-2xl text-center space-y-3">
            <div class="w-12 h-12 mx-auto rounded-full bg-zinc-800 border border-zinc-700 text-zinc-400 flex items-center justify-center text-xl">
                
            </div>
            <h3 class="text-sm font-bold text-white">{{ __('Nenhum roteiro criado ainda') }}</h3>
            <p class="text-xs text-zinc-400 max-w-sm mx-auto">
                {{ __('Gere o primeiro roteiro usando o assistente criativo Sofi AI Copilot para maximizar a retenção nos primeiros 3 segundos.') }}
            </p>
            <div class="pt-2">
                <a href="{{ route('roteiros.create', $production) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-zinc-800 hover:bg-orange-600 text-zinc-200 hover:text-white rounded-xl text-xs font-bold transition">
                    + {{ __('Criar Roteiro Agora') }}
                </a>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($roteiros as $roteiro)
                <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-6 shadow-xl space-y-4 hover:border-zinc-700 transition">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-zinc-800/80">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-base font-bold text-white">{{ __('Roteiro') }} #{{ $roteiro->id }}</h2>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider
                                    @if($roteiro->status === 'draft') bg-zinc-800 text-zinc-300 border border-zinc-700
                                    @elseif($roteiro->status === 'submitted') bg-amber-500/20 text-amber-300 border border-amber-500/30
                                    @elseif($roteiro->status === 'approved') bg-emerald-500/20 text-emerald-300 border border-emerald-500/30
                                    @elseif($roteiro->status === 'in_production') bg-orange-500/20 text-orange-300 border border-orange-500/30
                                    @elseif($roteiro->status === 'rejected') bg-rose-500/20 text-rose-300 border border-rose-500/30
                                    @else bg-blue-500/20 text-blue-300 border border-blue-500/30
                                    @endif">
                                    <span class="w-1.5 h-1.5 rounded-full 
                                        @if($roteiro->status === 'draft') bg-zinc-400
                                        @elseif($roteiro->status === 'submitted') bg-amber-400 animate-pulse
                                        @elseif($roteiro->status === 'approved') bg-emerald-400
                                        @elseif($roteiro->status === 'in_production') bg-orange-400 animate-pulse
                                        @elseif($roteiro->status === 'rejected') bg-rose-400
                                        @else bg-blue-400
                                        @endif"></span>
                                    {{ ucfirst(str_replace('_', ' ', $roteiro->status)) }}
                                </span>
                            </div>
                            <p class="text-xs text-zinc-400 mt-0.5">
                                {{ __('Criado por:') }} <strong class="text-zinc-200">{{ $roteiro->creator->name }}</strong>
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('roteiros.show', ['production' => $production, 'roteiro' => $roteiro]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 transition">
                                <span>{{ __('Ver Completo') }}</span>
                                <span>→</span>
                            </a>

                            @can('update', $roteiro)
                                @if (in_array($roteiro->status, ['draft', 'rejected']))
                                    <a href="{{ route('roteiros.edit', ['production' => $production, 'roteiro' => $roteiro]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-amber-300 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 transition">
                                        {{ $roteiro->status === 'rejected' ? __('Ajustar') : __('Editar') }}
                                    </a>
                                @endif
                                @if ($roteiro->status === 'draft')
                                    <form action="{{ route('roteiros.submit', ['production' => $production, 'roteiro' => $roteiro]) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-emerald-300 bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 transition cursor-pointer">
                                            {{ __('Submeter') }}
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>

                    <div class="p-4 bg-zinc-950/60 border border-zinc-800/80 rounded-xl">
                        <p class="text-xs text-zinc-300 leading-relaxed font-mono whitespace-pre-line">{{ Str::limit($roteiro->content, 260) }}</p>
                    </div>

                    @if ($roteiro->rejection_reason)
                        <div class="p-3 bg-rose-950/40 border border-rose-500/30 rounded-xl text-xs text-rose-300 space-y-1">
                            <p class="font-bold text-rose-400">{{ __('Motivo do Feedback / Ajuste:') }}</p>
                            <p>{{ $roteiro->rejection_reason }}</p>
                        </div>
                    @endif

                    @if ($roteiro->approved_by)
                        <div class="text-[11px] text-zinc-400 flex items-center gap-1.5">
                            <span class="text-emerald-400 font-bold">✓</span>
                            <span>{{ __('Aprovado por') }} <strong class="text-zinc-200">{{ $roteiro->approver->name }}</strong> em {{ $roteiro->approved_at->format('d/m/Y H:i') }}</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if($roteiros->hasPages())
            <div class="pt-4">
                {{ $roteiros->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
