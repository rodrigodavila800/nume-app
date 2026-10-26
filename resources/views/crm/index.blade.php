@extends('layouts.app')

@section('title', __('CRM · Funil de vendas').' | NUME')
@section('breadcrumb', __('CRM'))

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Funil de vendas') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Leads por etapa. Cadastros, pedidos e respostas a orçamentos movem os cards sozinhos; o resto a equipe move na ficha.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('crm.leads.index') }}" method="GET" class="flex items-center gap-2">
                <label for="crm-search" class="sr-only">{{ __('Buscar') }}</label>
                <input id="crm-search" name="search" value="{{ $search }}" placeholder="{{ __('Nome, empresa ou e-mail') }}"
                       class="bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500">
                <a href="{{ route('crm.leads.index', ['owner' => 'me']) }}" class="text-xs text-zinc-400 hover:text-white whitespace-nowrap">{{ __('Só meus') }}</a>
            </form>
            @can('create', \App\Models\Lead::class)
                <a href="{{ route('crm.leads.create') }}" class="px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold whitespace-nowrap transition">+ {{ __('Novo lead') }}</a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-semibold">{{ session('success') }}</div>
    @endif

    <div class="flex gap-3 overflow-x-auto pb-3">
        @foreach($columns as $stage => $label)
            @php $cards = $leads->get($stage, collect()); @endphp
            <section class="w-64 shrink-0 rounded-2xl bg-zinc-900 border border-zinc-800 p-3 space-y-2">
                <header class="flex items-center justify-between">
                    <h2 class="text-xs font-extrabold uppercase tracking-wider {{ $stage === 'ganho' ? 'text-emerald-400' : ($stage === 'perdido' ? 'text-rose-400' : 'text-zinc-300') }}">{{ $label }}</h2>
                    <span class="text-[10px] font-mono text-zinc-500">{{ $cards->count() }}</span>
                </header>
                @forelse($cards as $lead)
                    <a href="{{ route('crm.leads.show', $lead) }}" class="block p-3 rounded-xl bg-zinc-950 border {{ $lead->isStale() ? 'border-amber-500/50' : 'border-zinc-800' }} hover:border-orange-500/60 transition space-y-1">
                        <p class="text-xs font-bold text-white truncate">{{ $lead->company ?: $lead->name }}</p>
                        @if($lead->company)
                            <p class="text-[11px] text-zinc-400 truncate">{{ $lead->name }}</p>
                        @endif
                        <div class="flex flex-wrap items-center gap-1.5 text-[10px] text-zinc-500">
                            <span>{{ \App\Models\Lead::sourceLabels()[$lead->source] ?? $lead->source }}</span>
                            @if($lead->estimated_value)
                                <span class="text-orange-300 font-mono">€ {{ number_format($lead->estimated_value, 0, ',', '.') }}</span>
                            @endif
                            @if($lead->owner)
                                <span>· {{ $lead->owner->name }}</span>
                            @endif
                        </div>
                        @if($lead->isStale())
                            <p class="text-[10px] font-bold text-amber-400">⏰ {{ __('Sem contato há mais de :days dias', ['days' => \App\Models\Lead::STALE_DAYS]) }}</p>
                        @endif
                    </a>
                @empty
                    <p class="text-[11px] text-zinc-600 text-center py-4">{{ __('Nenhum lead') }}</p>
                @endforelse
            </section>
        @endforeach
    </div>
</div>
@endsection
