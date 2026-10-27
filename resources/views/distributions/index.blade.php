@extends('layouts.app')

@section('title', __('Monitor de Distribuição Social') . ' | NUME')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900">✕</button>
        </div>
    @endif

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-orange-100 text-orange-700 border border-orange-200">
                    {{ __('Social Publishing Hub') }}
                </span>
                <span class="text-xs text-zinc-500">
                    {{ __('Auditoria & Monitoramento') }}
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 mt-1">
                {{ __('Distribuição Social Multiplataforma') }}
            </h1>
            <p class="text-xs sm:text-sm text-zinc-500 mt-1">
                {{ __('Acompanhe em tempo real todas as publicações automatizadas dos vídeos dos seus clientes no Instagram, TikTok, YouTube Shorts e redes parceiras.') }}
            </p>
        </div>
    </div>

    <!-- KPIs Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="bg-zinc-900 rounded-2xl p-4 border border-zinc-800 shadow-xl flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Total de Disparos') }}</span>
                <span class="text-xl font-black text-white mt-1 block">{{ $stats['total'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-zinc-800 text-zinc-300 border border-zinc-700 flex items-center justify-center text-lg font-bold">
                
            </div>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-4 border border-zinc-800 shadow-xl flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Publicados') }}</span>
                <span class="text-xl font-black text-emerald-400 mt-1 block">{{ $stats['publicados'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-lg font-bold border border-emerald-500/20">
                ✓
            </div>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-4 border border-zinc-800 shadow-xl flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Agendados') }}</span>
                <span class="text-xl font-black text-purple-400 mt-1 block">{{ $stats['agendados'] ?? 0 }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center text-lg font-bold border border-purple-500/20">
                
            </div>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-4 border border-zinc-800 shadow-xl flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Processando') }}</span>
                <span class="text-xl font-black text-amber-400 mt-1 block">{{ $stats['processando'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-lg font-bold border border-amber-500/20 animate-pulse">
                
            </div>
        </div>

        <div class="bg-zinc-900 rounded-2xl p-4 border border-zinc-800 shadow-xl flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Falhas / Atenção') }}</span>
                <span class="text-xl font-black text-rose-400 mt-1 block">{{ $stats['falhas'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center text-lg font-bold border border-rose-500/20">
                
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-zinc-900 rounded-2xl p-4 border border-zinc-800 shadow-xl">
        <form method="GET" action="{{ route('distributions.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-5">
                <input type="text" name="search" value="{{ request('search') }}" 
                    placeholder="{{ __('Buscar por título do vídeo ou cliente...') }}"
                    class="w-full text-xs font-medium px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-white placeholder-zinc-500 focus:border-orange-500 focus:bg-zinc-900 focus:outline-none transition">
            </div>

            <div class="sm:col-span-3">
                <select name="status" class="w-full text-xs font-semibold px-3 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-200 focus:border-orange-500 focus:bg-zinc-900 focus:outline-none transition">
                    <option value="">{{ __('Todos os Status') }}</option>
                    <option value="publicado" @selected(request('status') === 'publicado')>{{ __('Publicado') }}</option>
                    <option value="agendado" @selected(request('status') === 'agendado')>{{ __('Agendado') }}</option>
                    <option value="processando" @selected(request('status') === 'processando')>{{ __('Processando') }}</option>
                    <option value="falhou" @selected(request('status') === 'falhou')>{{ __('Falhou') }}</option>
                </select>
            </div>

            <div class="sm:col-span-3">
                <select name="platform" class="w-full text-xs font-semibold px-3 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-200 focus:border-orange-500 focus:bg-zinc-900 focus:outline-none transition">
                    <option value="">{{ __('Todas as Redes') }}</option>
                    @foreach($platforms as $p)
                        <option value="{{ $p->value }}" @selected(request('platform') === $p->value)>{{ $p->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-1 flex items-center gap-1">
                <button type="submit" class="w-full h-full py-2.5 px-3 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition flex items-center justify-center shadow-md shadow-orange-900/20 cursor-pointer">
                    
                </button>
            </div>
        </form>
    </div>

    <!-- Tabela de Publicações -->
    <div class="bg-zinc-900 rounded-2xl border border-zinc-800 shadow-xl overflow-hidden">
        @if($publications->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-950/60 border-b border-zinc-800 text-zinc-400 uppercase tracking-wider font-bold text-[10px]">
                        <tr>
                            <th class="px-5 py-3.5">{{ __('Conteúdo / Vídeo') }}</th>
                            <th class="px-5 py-3.5">{{ __('Cliente / Empresa') }}</th>
                            <th class="px-5 py-3.5">{{ __('Destinos') }}</th>
                            <th class="px-5 py-3.5">{{ __('Status') }}</th>
                            <th class="px-5 py-3.5">{{ __('Data / Agendamento') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ __('Ações') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        @foreach($publications as $pub)
                            <tr class="hover:bg-zinc-800/30 transition">
                                <td class="px-5 py-4">
                                    <div class="font-bold text-white">{{ $pub->titulo }}</div>
                                    @if($pub->video && $pub->video->production)
                                        <div class="text-[11px] text-zinc-400 mt-0.5 flex items-center gap-1">
                                            <span>Produção:</span>
                                            <a href="{{ route('productions.show', $pub->video->production) }}" class="text-orange-400 hover:text-orange-300 hover:underline font-semibold">
                                                {{ $pub->video->production->title }}
                                            </a>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-bold text-white">{{ $pub->cliente?->name ?? 'Cliente Removido' }}</div>
                                    <div class="text-[11px] text-zinc-400">{{ $pub->cliente?->company_name ?? 'Pessoa Física' }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($pub->plataformas as $platformSlug)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-zinc-800 text-zinc-300 border border-zinc-700">
                                                {{ $platformSlug }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider
                                        @class([
                                            'bg-amber-500/20 text-amber-300 border border-amber-500/30 animate-pulse' => in_array($pub->status, ['pendente', 'processando']),
                                            'bg-purple-500/20 text-purple-300 border border-purple-500/30' => $pub->status === 'agendado',
                                            'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' => $pub->status === 'publicado',
                                            'bg-rose-500/20 text-rose-300 border border-rose-500/30' => $pub->status === 'falhou',
                                        ])">
                                        <span class="w-1.5 h-1.5 rounded-full mr-1.5
                                            @class([
                                                'bg-amber-400' => in_array($pub->status, ['pendente', 'processando']),
                                                'bg-purple-400' => $pub->status === 'agendado',
                                                'bg-emerald-400' => $pub->status === 'publicado',
                                                'bg-rose-400' => $pub->status === 'falhou',
                                            ])"></span>
                                        {{ ucfirst($pub->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 font-mono text-[11px]">
                                    @if($pub->status === 'agendado' && $pub->scheduled_at)
                                        <span class="text-purple-400 font-bold flex items-center gap-1">
                                            <span>{{ $pub->scheduled_at->format('d/m/Y H:i') }}</span>
                                        </span>
                                    @else
                                        <span class="text-zinc-400">{{ $pub->created_at->format('d/m/Y H:i') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <form action="{{ route('distributions.refresh', $pub) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" 
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 transition cursor-pointer">
                                            <svg class="w-3 h-3 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            <span>Sincronizar</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($publications->hasPages())
                <div class="p-4 border-t border-zinc-800">
                    {{ $publications->links() }}
                </div>
            @endif
        @else
            <div class="p-12 text-center space-y-3">
                <div class="w-12 h-12 mx-auto rounded-full bg-zinc-800 border border-zinc-700 text-zinc-400 flex items-center justify-center text-xl">
                    
                </div>
                <div class="space-y-1">
                    <h4 class="text-sm font-bold text-white">{{ __('Nenhuma publicação social encontrada') }}</h4>
                    <p class="text-xs text-zinc-400 max-w-sm mx-auto">
                        {{ __('Quando os clientes realizarem disparos a partir da esteira de vídeos entregues, os registros aparecerão detalhados aqui.') }}
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
