@extends('layouts.app')

@section('title', __('Uso de IA') . ' | NUME')
@section('breadcrumb', __('IA & Consumo'))

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">

    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($budget['exhausted'])
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-bold flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                <span>{{ __('Limite mensal da produtora atingido (:used de :limit tokens). A Sofi está operando no modo de reserva automático até o próximo mês ou aumento do limite.', ['used' => number_format($budget['used'], 0, ',', '.'), 'limit' => number_format($budget['limit'], 0, ',', '.')]) }}</span>
            </div>
        </div>
    @elseif($budget['at_warning'])
        <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-bold flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                <span>{{ __('Atenção: o consumo deste mês atingiu :percent% do limite mensal (:used de :limit tokens).', ['percent' => $budget['percent'], 'used' => number_format($budget['used'], 0, ',', '.'), 'limit' => number_format($budget['limit'], 0, ',', '.')]) }}</span>
            </div>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-white">{{ __('Uso de IA & Limites Mensais') }}</h1>
            <p class="text-xs text-zinc-400 mt-1 max-w-2xl">
                {{ __('Acompanhe o consumo de tokens da Sofi IA por mês, função e cliente do portal. Quando um limite é atingido, a Sofi usa o texto pronto de reserva sem interromper a operação.') }}
            </p>
        </div>

        <form method="GET" action="{{ route('ai_usage.index') }}" class="flex items-center gap-2">
            <label for="month" class="text-xs font-bold text-zinc-400">{{ __('Mês:') }}</label>
            <input type="month" id="month" name="month" value="{{ $month }}"
                class="px-3 py-1.5 rounded-xl border border-zinc-800 bg-zinc-900 text-xs font-bold text-white focus:outline-none focus:border-orange-500">
            <button type="submit"
                class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-white bg-orange-600 hover:bg-orange-500 transition cursor-pointer">
                {{ __('Filtrar') }}
            </button>
        </form>
    </div>

    <x-settings-tabs active="ai_usage" />

    <!-- Resumo do Mês + Configuração de Limites -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-zinc-900 rounded-2xl border border-zinc-800 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-zinc-400">
                    {{ __('Consumo em :month', ['month' => $month]) }}
                </h2>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $budget['exhausted'] ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30' : ($budget['at_warning'] ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30' : 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30') }}">
                    @if($budget['limit'])
                        {{ $budget['percent'] }}% {{ __('do limite mensal') }}
                    @else
                        {{ __('Sem limite definido') }}
                    @endif
                </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
                <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800">
                    <span class="text-[11px] font-bold text-zinc-400 uppercase block">{{ __('Chamadas') }}</span>
                    <span class="text-xl font-black text-white mt-1 block">{{ number_format($monthTotals['calls'], 0, ',', '.') }}</span>
                </div>
                <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800">
                    <span class="text-[11px] font-bold text-zinc-400 uppercase block">{{ __('Tokens de entrada') }}</span>
                    <span class="text-xl font-black text-white mt-1 block">{{ number_format($monthTotals['input_tokens'], 0, ',', '.') }}</span>
                </div>
                <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800">
                    <span class="text-[11px] font-bold text-zinc-400 uppercase block">{{ __('Tokens de saída') }}</span>
                    <span class="text-xl font-black text-white mt-1 block">{{ number_format($monthTotals['output_tokens'], 0, ',', '.') }}</span>
                </div>
                <div class="p-4 rounded-xl bg-orange-500/10 border border-orange-500/25">
                    <span class="text-[11px] font-bold text-orange-400 uppercase block">{{ __('Total de tokens') }}</span>
                    <span class="text-xl font-black text-orange-400 mt-1 block">{{ number_format($monthTotals['total_tokens'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <div class="bg-zinc-900 rounded-2xl border border-zinc-800 p-6">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-zinc-300">{{ __('Limites Mensais de Tokens') }}</h2>
            <p class="text-[11px] text-zinc-400 mt-1">
                {{ __('Deixe em branco ou 0 para uso ilimitado. Ao atingir 80% do limite da produtora, um alerta é exibido.') }}
            </p>

            <form method="POST" action="{{ route('ai_usage.limits.update') }}" class="space-y-4 mt-4">
                @csrf
                <div>
                    <label for="monthly_token_limit" class="block text-xs font-bold text-zinc-300">
                        {{ __('Limite mensal da produtora (tokens)') }}
                    </label>
                    <input type="number" id="monthly_token_limit" name="monthly_token_limit" min="0" step="1000"
                        value="{{ old('monthly_token_limit', $budget['limit']) }}"
                        placeholder="{{ __('Sem limite') }}"
                        class="mt-1 w-full px-3 py-2 rounded-xl bg-zinc-950 border border-zinc-800 text-xs font-semibold text-white placeholder-zinc-600 focus:outline-none focus:border-orange-500">
                </div>

                <div>
                    <label for="monthly_cliente_token_limit" class="block text-xs font-bold text-zinc-300">
                        {{ __('Limite mensal por cliente do portal (tokens)') }}
                    </label>
                    <input type="number" id="monthly_cliente_token_limit" name="monthly_cliente_token_limit" min="0" step="500"
                        value="{{ old('monthly_cliente_token_limit', $budget['cliente_limit']) }}"
                        placeholder="{{ __('Sem limite') }}"
                        class="mt-1 w-full px-3 py-2 rounded-xl bg-zinc-950 border border-zinc-800 text-xs font-semibold text-white placeholder-zinc-600 focus:outline-none focus:border-orange-500">
                </div>

                <button type="submit"
                    class="w-full py-2.5 px-4 rounded-xl text-xs font-extrabold text-white bg-orange-600 hover:bg-orange-500 shadow-xs transition cursor-pointer">
                    {{ __('Salvar limites') }}
                </button>
            </form>
        </div>
    </div>

    <!-- Consumo por Função e por Cliente -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-zinc-900 rounded-2xl border border-zinc-800 p-6 space-y-4">
            <h2 class="text-base font-bold text-white">{{ __('Por função da Sofi') }}</h2>

            @if($byAgent->isEmpty())
                <p class="text-xs text-zinc-500 py-4">{{ __('Nenhum uso registrado neste mês.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-zinc-800 text-zinc-400 font-bold">
                                <th class="pb-2">{{ __('Função') }}</th>
                                <th class="pb-2 text-right">{{ __('Chamadas') }}</th>
                                <th class="pb-2 text-right">{{ __('Entrada') }}</th>
                                <th class="pb-2 text-right">{{ __('Saída') }}</th>
                                <th class="pb-2 text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60">
                            @foreach($byAgent as $row)
                                <tr>
                                    <td class="py-2.5 font-bold text-white">{{ $row->label }}</td>
                                    <td class="py-2.5 text-right text-zinc-400">{{ number_format((int) $row->calls, 0, ',', '.') }}</td>
                                    <td class="py-2.5 text-right text-zinc-400">{{ number_format((int) $row->input_tokens, 0, ',', '.') }}</td>
                                    <td class="py-2.5 text-right text-zinc-400">{{ number_format((int) $row->output_tokens, 0, ',', '.') }}</td>
                                    <td class="py-2.5 text-right font-extrabold text-orange-400">{{ number_format((int) $row->total_tokens, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="bg-zinc-900 rounded-2xl border border-zinc-800 p-6 space-y-4">
            <h2 class="text-base font-bold text-white">{{ __('Por cliente do portal') }}</h2>

            @if($byCliente->isEmpty())
                <p class="text-xs text-zinc-500 py-4">{{ __('Nenhum uso vinculado a cliente neste mês.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-zinc-800 text-zinc-400 font-bold">
                                <th class="pb-2">{{ __('Cliente') }}</th>
                                <th class="pb-2 text-right">{{ __('Chamadas') }}</th>
                                <th class="pb-2 text-right">{{ __('Total de tokens') }}</th>
                                <th class="pb-2 text-right">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60">
                            @foreach($byCliente as $row)
                                <tr>
                                    <td class="py-2.5 font-bold text-white">{{ $row->cliente_name }}</td>
                                    <td class="py-2.5 text-right text-zinc-400">{{ number_format((int) $row->calls, 0, ',', '.') }}</td>
                                    <td class="py-2.5 text-right font-extrabold text-orange-400">{{ number_format((int) $row->total_tokens, 0, ',', '.') }}</td>
                                    <td class="py-2.5 text-right">
                                        @if($row->exhausted)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/15 text-rose-300 border border-rose-500/30">{{ __('No limite') }}</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">{{ __('Ativo') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Histórico Mensal -->
    <div class="bg-zinc-900 rounded-2xl border border-zinc-800 p-6 space-y-4">
        <h2 class="text-base font-bold text-white">{{ __('Histórico dos últimos meses') }}</h2>

        @if($monthlyHistory->isEmpty())
            <p class="text-xs text-zinc-500">{{ __('Ainda não há histórico registrado.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 text-zinc-400 font-bold">
                            <th class="pb-2">{{ __('Mês') }}</th>
                            <th class="pb-2 text-right">{{ __('Chamadas') }}</th>
                            <th class="pb-2 text-right">{{ __('Tokens de entrada') }}</th>
                            <th class="pb-2 text-right">{{ __('Tokens de saída') }}</th>
                            <th class="pb-2 text-right">{{ __('Total de tokens') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        @foreach($monthlyHistory as $item)
                            <tr>
                                <td class="py-2.5 font-bold text-white">
                                    <a href="{{ route('ai_usage.index', ['month' => $item['month']]) }}" class="hover:text-orange-400 underline">
                                        {{ $item['month'] }}
                                    </a>
                                </td>
                                <td class="py-2.5 text-right text-zinc-400">{{ number_format($item['calls'], 0, ',', '.') }}</td>
                                <td class="py-2.5 text-right text-zinc-400">{{ number_format($item['input_tokens'], 0, ',', '.') }}</td>
                                <td class="py-2.5 text-right text-zinc-400">{{ number_format($item['output_tokens'], 0, ',', '.') }}</td>
                                <td class="py-2.5 text-right font-extrabold text-orange-400">{{ number_format($item['total_tokens'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
