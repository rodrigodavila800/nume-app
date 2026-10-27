@extends('layouts.app')

@section('title', __('Calendário Geral de Publicações & Entregas | NUME'))

@section('content')
@php
    $prevMonth = $selectedDate->copy()->subMonth();
    $nextMonth = $selectedDate->copy()->addMonth();
    $mesExtenso = $selectedDate->translatedFormat('F \d\e Y');
@endphp

<div class="space-y-6">

    <!-- Top Header & Breadcrumb -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-zinc-400 mb-1">
                <span>{{ __('Visão Geral') }}</span>
                <span class="text-zinc-600">/</span>
                <span class="text-orange-400">{{ __('Calendário Geral') }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-3">
                <span>{{ __('Planejamento Audiovisual & Social') }}</span>
                <span class="text-xs font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-orange-500/10 text-orange-400 border border-orange-500/20">
                    Modo Criador PRO
                </span>
            </h1>
            <p class="text-xs text-zinc-400 mt-1">
                {{ __('Visão unificada de prazos de produções, publicações agendadas e postagens realizadas em todas as redes.') }}
            </p>
        </div>

        <!-- Barra de Ações / Mês / Filtro -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Filtro de Cliente -->
            <form method="GET" action="{{ route('calendar.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="year" value="{{ $selectedDate->year }}">
                <input type="hidden" name="month" value="{{ $selectedDate->month }}">
                <div class="relative">
                    <select name="cliente_id" onchange="this.form.submit()"
                            class="bg-zinc-900 border border-zinc-700/80 rounded-xl px-3 py-2 text-xs font-medium text-zinc-200 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 pr-8">
                        <option value="">{{ __('Todos os Clientes') }}</option>
                        @foreach($clientes as $c)
                            <option value="{{ $c->id }}" {{ $clienteId == $c->id ? 'selected' : '' }}>
                                {{ $c->company_name ?: $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            <!-- Seletor de Mês -->
            <div class="flex items-center bg-zinc-900 border border-zinc-800 rounded-xl p-1 shadow-sm">
                <a href="{{ route('calendar.index', ['year' => $prevMonth->year, 'month' => $prevMonth->month, 'cliente_id' => $clienteId]) }}"
                   class="p-1.5 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 transition"
                   title="{{ __('Mês anterior') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <span class="px-3 text-xs font-bold text-white capitalize min-w-[140px] text-center">
                    {{ $mesExtenso }}
                </span>
                <a href="{{ route('calendar.index', ['year' => $nextMonth->year, 'month' => $nextMonth->month, 'cliente_id' => $clienteId]) }}"
                   class="p-1.5 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 transition"
                   title="{{ __('Próximo mês') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <!-- Botão Hoje -->
            @if(!$selectedDate->isCurrentMonth())
                <a href="{{ route('calendar.index', ['cliente_id' => $clienteId]) }}"
                   class="px-3 py-2 text-xs font-semibold bg-zinc-800 hover:bg-zinc-700 text-zinc-200 rounded-xl border border-zinc-700 transition">
                    {{ __('Hoje') }}
                </a>
            @endif
        </div>
    </div>

    <!-- Cards de Métricas do Mês -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Card 1: Agendados -->
        <div class="bg-zinc-900/80 border border-purple-500/20 rounded-2xl p-4 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-purple-400">{{ __('Publicações Agendadas') }}</p>
                <p class="text-2xl font-black text-white mt-1">{{ $stats['total_scheduled'] }}</p>
                <p class="text-[10px] text-zinc-400 mt-0.5">{{ __('Prontas para disparo automático via Cron/API') }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-purple-400 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        <!-- Card 2: Publicados -->
        <div class="bg-zinc-900/80 border border-emerald-500/20 rounded-2xl p-4 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-400">{{ __('Vídeos Postados') }}</p>
                <p class="text-2xl font-black text-white mt-1">{{ $stats['total_published'] }}</p>
                <p class="text-[10px] text-zinc-400 mt-0.5">{{ __('Distribuídos com sucesso nas redes sociais') }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        <!-- Card 3: Prazos de Produções -->
        <div class="bg-zinc-900/80 border border-orange-500/20 rounded-2xl p-4 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-orange-400">{{ __('Prazos de Produção') }}</p>
                <p class="text-2xl font-black text-white mt-1">{{ $stats['total_productions'] }}</p>
                <p class="text-[10px] text-zinc-400 mt-0.5">{{ __('Deadlines de entrega com clientes no mês') }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-orange-500/10 border border-orange-500/30 flex items-center justify-center text-orange-400 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Legenda Rápida -->
    <div class="flex flex-wrap items-center gap-4 px-3 py-2 bg-zinc-900/40 border border-zinc-800/80 rounded-xl text-xs text-zinc-400">
        <span class="font-bold text-zinc-300">{{ __('Legenda:') }}</span>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
            <span>{{ __('Agendado') }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
            <span>{{ __('Publicado') }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
            <span>{{ __('Processando') }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
            <span>{{ __('Falhou') }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
            <span>{{ __('Prazo de Produção') }}</span>
        </div>
    </div>

    <!-- Grade Mensal do Calendário -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl shadow-xl overflow-hidden">
        
        <!-- Cabeçalho dos Dias da Semana -->
        <div class="grid grid-cols-7 border-b border-zinc-800 bg-zinc-950/60 text-center text-xs font-extrabold uppercase tracking-wider text-zinc-400 py-3">
            <div>{{ __('Seg') }}</div>
            <div>{{ __('Ter') }}</div>
            <div>{{ __('Qua') }}</div>
            <div>{{ __('Qui') }}</div>
            <div>{{ __('Sex') }}</div>
            <div class="text-orange-400/90">{{ __('Sáb') }}</div>
            <div class="text-orange-400/90">{{ __('Dom') }}</div>
        </div>

        <!-- Grade de Dias -->
        <div class="grid grid-cols-7 divide-x divide-y divide-zinc-800/60">
            @foreach($weeks as $week)
                @foreach($week as $day)
                    @php
                        $isCurrentMonth = $day['is_current_month'];
                        $isToday = $day['is_today'];
                        $dayEvents = $day['events'];
                    @endphp
                    <div class="min-h-[140px] p-2 flex flex-col justify-between transition-colors {{ $isCurrentMonth ? 'bg-zinc-900/30 hover:bg-zinc-800/30' : 'bg-zinc-950/40 text-zinc-600' }} {{ $isToday ? 'ring-2 ring-inset ring-orange-500/50 bg-orange-950/10' : '' }}">
                        
                        <!-- Top do Dia: Número e Badges -->
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-black {{ $isToday ? 'w-6 h-6 rounded-full bg-orange-600 text-white flex items-center justify-center shadow-md shadow-orange-600/30' : ($isCurrentMonth ? 'text-zinc-300' : 'text-zinc-600') }}">
                                {{ $day['day_number'] }}
                            </span>
                            @if(count($dayEvents) > 0)
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-zinc-800 text-zinc-400 border border-zinc-700/60">
                                    {{ count($dayEvents) }}
                                </span>
                            @endif
                        </div>

                        <!-- Lista de Eventos do Dia -->
                        <div class="flex-1 space-y-1.5 overflow-y-auto max-h-[120px] [scrollbar-width:none]">
                            @forelse($dayEvents as $event)
                                @if($event['type'] === 'publication')
                                    <!-- Card de Publicação Social -->
                                    <div class="group relative rounded-lg p-1.5 border transition-all text-left {{ $event['status'] === 'agendado' ? 'bg-purple-950/30 border-purple-500/30 hover:border-purple-400' : ($event['status'] === 'publicado' ? 'bg-emerald-950/30 border-emerald-500/30 hover:border-emerald-400' : 'bg-zinc-800/60 border-zinc-700 hover:border-zinc-500') }}">
                                        <div class="flex items-start gap-1.5">
                                            <!-- Miniatura do Vídeo -->
                                            <img src="{{ $event['thumbnail_url'] }}"
                                                 alt="{{ $event['title'] }}"
                                                 class="w-7 h-10 object-cover rounded shrink-0 bg-zinc-950 border border-zinc-800">
                                            
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="text-[10px] font-bold truncate text-zinc-100">
                                                        {{ $event['title'] }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1 text-[9px] text-zinc-400 mt-0.5">
                                                    @if($event['scheduled_at'])
                                                        <span class="font-bold text-purple-300">{{ $event['scheduled_at'] }}</span>
                                                    @endif
                                                    <span class="truncate">{{ $event['company'] ?: $event['cliente'] }}</span>
                                                </div>

                                                <!-- Ícones das Plataformas -->
                                                <div class="flex items-center gap-1 mt-1">
                                                    @foreach($event['platforms'] as $plat)
                                                        @if(in_array($plat, ['instagram', 'instagram_reels']))
                                                            <span class="text-[8px] font-bold px-1 rounded bg-pink-500/20 text-pink-300">IG</span>
                                                        @elseif(in_array($plat, ['youtube', 'youtube_shorts']))
                                                            <span class="text-[8px] font-bold px-1 rounded bg-red-500/20 text-red-300">YT</span>
                                                        @elseif($plat === 'tiktok')
                                                            <span class="text-[8px] font-bold px-1 rounded bg-cyan-500/20 text-cyan-300">TK</span>
                                                        @elseif($plat === 'facebook')
                                                            <span class="text-[8px] font-bold px-1 rounded bg-blue-500/20 text-blue-300">FB</span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        @if($event['view_url'])
                                            <a href="{{ $event['view_url'] }}" class="absolute inset-0 z-10" title="{{ __('Ver produção') }}"></a>
                                        @endif
                                    </div>
                                @else
                                    <!-- Card de Prazo de Produção -->
                                    <a href="{{ $event['view_url'] }}"
                                       class="block rounded-lg p-1.5 bg-blue-950/30 border border-blue-500/30 hover:border-blue-400 transition-all text-left">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[9px] font-extrabold uppercase tracking-wider text-blue-400 flex items-center gap-1">
                                                <span>{{ __('Entrega') }}</span>
                                            </span>
                                            <span class="text-[8px] font-bold px-1 rounded bg-blue-500/20 text-blue-300">
                                                {{ ucfirst($event['status']) }}
                                            </span>
                                        </div>
                                        <p class="text-[10px] font-bold text-zinc-100 truncate mt-0.5">
                                            {{ $event['title'] }}
                                        </p>
                                        <p class="text-[9px] text-zinc-400 truncate">
                                            {{ $event['company'] ?: $event['cliente'] }}
                                        </p>
                                    </a>
                                @endif
                            @empty
                                <!-- Dia sem eventos -->
                            @endforelse
                        </div>

                    </div>
                @endforeach
            @endforeach
        </div>

    </div>

</div>
@endsection
