@extends('layouts.app')

@section('title', __('Planos de Assinatura Recorrente | NUME Admin'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 py-6">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-zinc-800 pb-6">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-500/20 text-orange-400 border border-orange-500/30">
                    {{ __('Modo Criador & Retainers') }}
                </span>
                <span class="text-xs text-zinc-500 font-mono">Receita Recorrente (MRR)</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight mt-1">
                {{ __('Planos de Assinatura Recorrente') }}
            </h1>
            <p class="text-sm text-zinc-400 mt-1 max-w-2xl">
                {{ __('Configure ofertas contínuas com cotas mensais de vídeos, fidelidade contratual e cobrança recorrente automatizada.') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.plans.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-orange-600 hover:bg-orange-500 text-white shadow-lg shadow-orange-900/20 transition active:scale-95 cursor-pointer">
                <span>+</span>
                <span>{{ __('Novo Plano de Assinatura') }}</span>
            </a>
        </div>
    </div>

    {{-- Cards de Indicadores do Catálogo de Planos --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('Planos Ativos no Catálogo') }}</span>
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-3xl font-extrabold text-white">{{ $stats['total_plans'] }}</span>
                <span class="text-xs text-zinc-500">{{ __('planos vigentes') }}</span>
            </div>
            <p class="text-[11px] text-zinc-400 mt-1">
                {{ __('Disponíveis para contratação na vitrine e portal') }}
            </p>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('Cota Média de Vídeos / Mês') }}</span>
                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-3xl font-extrabold text-white">{{ $stats['avg_monthly_quota'] }}</span>
                <span class="text-xs text-zinc-500">{{ __('vídeos/ciclo') }}</span>
            </div>
            <p class="text-[11px] text-zinc-400 mt-1">
                {{ __('Capacidade produtiva média contratada por plano') }}
            </p>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800 shadow-xl">
            <div class="flex items-center justify-between text-zinc-400 text-xs font-bold uppercase tracking-wider">
                <span>{{ __('Ticket Médio Recorrente') }}</span>
                <span class="text-emerald-400 font-mono text-xs">€ / Mês</span>
            </div>
            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-3xl font-extrabold text-emerald-400">€ {{ number_format($stats['avg_ticket'], 2, ',', '.') }}</span>
            </div>
            <p class="text-[11px] text-zinc-400 mt-1">
                {{ __('Valor médio por ciclo de assinatura') }}
            </p>
        </div>
    </div>

    {{-- Grid de Planos Recorrentes --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($plans as $plan)
            <div class="bg-zinc-900 rounded-2xl border border-zinc-800 p-6 flex flex-col justify-between hover:border-orange-500/50 transition-all shadow-xl group">
                <div class="space-y-4">
                    {{-- Badges de Topo --}}
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider {{ $plan->status === 'active' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-zinc-800 text-zinc-400 border border-zinc-700' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $plan->status === 'active' ? 'bg-emerald-400' : 'bg-zinc-500' }}"></span>
                            {{ $plan->status === 'active' ? __('Ativo') : __('Inativo') }}
                        </span>

                        <span class="inline-flex items-center gap-1 text-[11px] font-mono text-orange-400 bg-orange-500/10 border border-orange-500/20 px-2 py-0.5 rounded-md font-bold">
                            {{ $plan->billing_interval === 'quarter' ? __('Trimestral') : __('Mensal') }}
                        </span>
                    </div>

                    {{-- Título e Categoria --}}
                    <div>
                        <h3 class="text-lg font-extrabold text-white group-hover:text-orange-400 transition">{{ $plan->name }}</h3>
                        <p class="text-xs text-zinc-400 mt-0.5">{{ $plan->category_name }}</p>
                    </div>

                    {{-- Preço e Cota --}}
                    <div class="p-4 bg-zinc-950/80 rounded-xl border border-zinc-800/80 flex items-baseline justify-between">
                        <div>
                            <span class="text-2xl font-black text-white">€ {{ number_format($plan->price, 2, ',', '.') }}</span>
                            <span class="text-xs text-zinc-400 font-medium">/ {{ $plan->billing_interval === 'quarter' ? __('trimestre') : __('mês') }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-black text-orange-400 block">{{ $plan->video_quantity }} {{ __('vídeos') }}</span>
                            <span class="text-[10px] text-zinc-500 uppercase font-bold">{{ __('por ciclo') }}</span>
                        </div>
                    </div>

                    {{-- Compromisso / Fidelidade --}}
                    <div class="flex items-center gap-2 text-xs text-zinc-400">
                        <span>{{ __('Fidelidade Contratual:') }} <strong class="text-zinc-200">{{ $plan->commitment_months }} {{ __('meses') }}</strong></span>
                    </div>

                    {{-- Descrição --}}
                    @if ($plan->description)
                        <p class="text-xs text-zinc-400 line-clamp-2 leading-relaxed">
                            {{ $plan->description }}
                        </p>
                    @endif

                    {{-- Entregáveis / Features --}}
                    @if (!empty($plan->features))
                        <div class="space-y-1.5 pt-2 border-t border-zinc-800">
                            @foreach (array_slice((array) $plan->features, 0, 4) as $feat)
                                <div class="flex items-center gap-2 text-xs text-zinc-300">
                                    <span class="text-emerald-400 font-bold text-xs">✓</span>
                                    <span class="truncate">{{ $feat }}</span>
                                </div>
                            @endforeach
                            @if (count((array) $plan->features) > 4)
                                <p class="text-[10px] text-zinc-500 font-medium pl-4">+ {{ count((array) $plan->features) - 4 }} {{ __('outros benefícios') }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Ações no Rodapé do Card --}}
                <div class="pt-5 mt-5 border-t border-zinc-800 flex items-center justify-between gap-2">
                    <a href="{{ route('admin.plans.edit', $plan) }}"
                       class="flex-1 py-2 px-3 rounded-xl text-center text-xs font-bold text-zinc-200 bg-zinc-800 hover:bg-orange-600 hover:text-white transition shadow-sm inline-flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>{{ __('Editar Plano') }}</span>
                    </a>

                    <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" onsubmit="return confirm('Deseja realmente remover este plano do catálogo?');" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-2 rounded-xl text-zinc-500 hover:text-rose-400 hover:bg-zinc-800 transition" title="{{ __('Excluir plano') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-zinc-900 border border-zinc-800 rounded-2xl p-12 text-center space-y-4 shadow-xl">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-zinc-800 text-zinc-400 flex items-center justify-center text-3xl">
                    
                </div>
                <h3 class="text-base font-bold text-white">{{ __('Nenhum Plano de Assinatura Recorrente Cadastrado') }}</h3>
                <p class="text-xs text-zinc-400 max-w-md mx-auto leading-relaxed">
                    {{ __('Crie retainers de produção de vídeo com cobrança recorrente, cota mensal pré-fixada e fidelidade contratual (estilo Modo Criador).') }}
                </p>
                <a href="{{ route('admin.plans.create') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-orange-600 hover:bg-orange-500 text-white shadow-lg shadow-orange-900/20 transition">
                    + {{ __('Criar Primeiro Plano de Assinatura') }}
                </a>
            </div>
        @endforelse
    </div>

    @if ($plans->hasPages())
        <div class="pt-4">
            {{ $plans->links() }}
        </div>
    @endif

</div>
@endsection
