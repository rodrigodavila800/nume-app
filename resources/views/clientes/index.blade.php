@extends('layouts.app')

@section('title', __('NUME | Clientes'))
@section('breadcrumb', __('Clientes'))

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Gestão de Clientes') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Controle as marcas e empresas que contratam produções da NUME.') }}</p>
        </div>

        @can('create', App\Models\Cliente::class)
            <a href="{{ route('clientes.create') }}"
               class="inline-flex items-center gap-2 font-bold rounded-xl text-xs sm:text-sm px-4 py-2.5 bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white shadow-lg shadow-orange-900/20 transition-all self-start sm:self-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>{{ __('Novo Cliente') }}</span>
            </a>
        @endcan
    </div>

    <!-- Navegação Comercial & Clientes -->
    <x-commercial-tabs active="clientes" />

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold flex items-center gap-2">
            <span>✓</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Table Card -->
    <div class="rounded-2xl bg-zinc-900 border border-zinc-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-950/60 border-b border-zinc-800 text-zinc-400 uppercase font-bold tracking-wider text-[10px]">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('Cliente') }}</th>
                        <th class="px-5 py-3.5">{{ __('Empresa / Marca') }}</th>
                        <th class="px-5 py-3.5">{{ __('Jornada (DNA · Anamnese · Plano)') }}</th>
                        <th class="px-5 py-3.5">{{ __('Produções') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60">
                    @forelse ($clientes as $cliente)
                        @php
                            $plan = $cliente->marketingPlans->first();
                        @endphp
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="px-5 py-4">
                                <a href="{{ route('clientes.show', $cliente) }}" class="flex items-center gap-3 group">
                                    <div class="w-8 h-8 rounded-full bg-orange-600/20 border border-orange-500/20 text-orange-400 font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ substr($cliente->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <span class="font-bold text-white group-hover:text-orange-400 transition block">{{ $cliente->name }}</span>
                                        <span class="text-zinc-500 font-mono text-[10px]">{{ $cliente->email }}</span>
                                    </div>
                                </a>
                            </td>
                            <td class="px-5 py-4">
                                <span class="text-zinc-200 font-medium block">{{ $cliente->company_name ?? '—' }}</span>
                                @if($cliente->brand_niche)
                                    <span class="text-[10px] text-zinc-500">{{ $cliente->brand_niche }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $cliente->hasCompletedBrandProfile() ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-zinc-800 text-zinc-500' }}">
                                        {{ $cliente->hasCompletedBrandProfile() ? '✓ DNA' : '○ DNA' }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $cliente->hasCompletedAnamnesis() ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-zinc-800 text-zinc-500' }}">
                                        {{ $cliente->hasCompletedAnamnesis() ? '✓ Anamnese' : '○ Anamnese' }}
                                    </span>
                                    @if ($plan)
                                        @if ($plan->status === \App\Models\MarketingPlan::STATUS_APPROVED)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                ✓ {{ __('Plano Aprovado') }}
                                            </span>
                                        @elseif ($plan->status === \App\Models\MarketingPlan::STATUS_ADJUSTMENT)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                                ⚠️ {{ __('Ajuste no Plano') }}
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                                                ⏳ {{ __('Plano em Revisão') }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-zinc-800 text-zinc-500">
                                            ○ {{ __('Sem Plano') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="px-2 py-0.5 rounded-md bg-zinc-800 text-zinc-300 font-semibold text-[11px]">
                                    {{ $cliente->productions_count }} {{ __('produções') }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('clientes.show', $cliente) }}"
                                       class="px-3 py-1.5 rounded-lg bg-orange-600/20 hover:bg-orange-600 text-orange-400 hover:text-white font-bold text-xs border border-orange-500/30 transition">
                                        {{ __('Ficha 360º →') }}
                                    </a>
                                    @can('update', $cliente)
                                        <a href="{{ route('clientes.edit', $cliente) }}" class="p-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-300 transition" title="Editar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                    @endcan
                                    @can('delete', $cliente)
                                        <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" class="inline" onsubmit="return confirm('Remover este cliente?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg bg-zinc-800 hover:bg-rose-950 hover:text-rose-400 text-zinc-400 transition" title="Remover">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-zinc-500">
                                {{ __('Nenhum cliente cadastrado ainda.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pt-2">
        {{ $clientes->links() }}
    </div>
</div>
@endsection
