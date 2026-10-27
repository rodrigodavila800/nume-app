@extends('layouts.app')

@section('title', __('NUME | Clientes'))
@section('breadcrumb', __('Clientes'))

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>{{ __('Gestão de Clientes') }}</h1>
            <p>{{ __('Controle as marcas e empresas que contratam produções da NUME.') }}</p>
        </div>

        @can('create', App\Models\Cliente::class)
            <a href="{{ route('clientes.create') }}" class="btn primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>{{ __('Novo Cliente') }}</span>
            </a>
        @endcan
    </div>

    <!-- Navegação Comercial & Clientes -->
    <x-commercial-tabs active="clientes" />

    <!-- Table Card -->
    <div class="card">
        <div class="overflow-x-auto">
            <table class="market-table">
                <thead>
                    <tr>
                        <th>{{ __('Cliente') }}</th>
                        <th>{{ __('Empresa / Marca') }}</th>
                        <th>{{ __('Jornada (DNA · Anamnese · Plano)') }}</th>
                        <th>{{ __('Produções') }}</th>
                        <th class="text-right">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clientes as $cliente)
                        @php
                            $plan = $cliente->marketingPlans->first();
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('clientes.show', $cliente) }}" class="flex items-center gap-3 group">
                                    <div class="user-avatar">
                                        {{ strtoupper(substr($cliente->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <span class="font-semibold text-white group-hover:text-orange-400 transition block">{{ $cliente->name }}</span>
                                        <span class="text-zinc-500 font-mono text-xs">{{ $cliente->email }}</span>
                                    </div>
                                </a>
                            </td>
                            <td>
                                <span class="text-zinc-200 font-medium block">{{ $cliente->company_name ?? '—' }}</span>
                                @if($cliente->brand_niche)
                                    <span class="text-xs text-zinc-500">{{ $cliente->brand_niche }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="status-pill {{ $cliente->hasCompletedBrandProfile() ? 'positive' : '' }}">
                                        {{ $cliente->hasCompletedBrandProfile() ? '✓ DNA' : '○ DNA' }}
                                    </span>
                                    <span class="status-pill {{ $cliente->hasCompletedAnamnesis() ? 'positive' : '' }}">
                                        {{ $cliente->hasCompletedAnamnesis() ? '✓ Anamnese' : '○ Anamnese' }}
                                    </span>
                                    @if ($plan)
                                        @if ($plan->status === \App\Models\MarketingPlan::STATUS_APPROVED)
                                            <span class="status-pill positive">
                                                ✓ {{ __('Plano Aprovado') }}
                                            </span>
                                        @elseif ($plan->status === \App\Models\MarketingPlan::STATUS_ADJUSTMENT)
                                            <span class="status-pill negative">
                                                {{ __('Ajuste no Plano') }}
                                            </span>
                                        @else
                                            <span class="status-pill copper">
                                                {{ __('Plano em Revisão') }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="status-pill">
                                            ○ {{ __('Sem Plano') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="status-pill">
                                    {{ $cliente->productions_count }} {{ __('produções') }}
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('clientes.show', $cliente) }}" class="btn primary text-xs py-1.5 px-3">
                                        {{ __('Ficha 360º →') }}
                                    </a>
                                    @can('update', $cliente)
                                        <a href="{{ route('clientes.edit', $cliente) }}" class="btn text-xs py-1.5 px-2.5" title="Editar">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>
                                    @endcan
                                    @can('delete', $cliente)
                                        <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" class="inline" onsubmit="return confirm('Remover este cliente?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn danger text-xs py-1.5 px-2.5" title="Remover">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-zinc-500">
                                {{ __('Nenhum cliente cadastrado ainda.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-4">
            {{ $clientes->links() }}
        </div>
    </div>
</div>
@endsection
