@extends('layouts.app')

@section('title', __('NUME | Editar Orçamento ' . $quote->number))
@section('breadcrumb', __('Editar Orçamento'))

@section('content')
@php
    $inputs = $quote->inputs ?? [];
    $calc = $quote->calculation ?? [];
    $extras = $inputs['extras'] ?? [];
@endphp
<div class="space-y-6">

    <!-- Header & Ações -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('quotes.index') }}" class="text-xs text-zinc-400 hover:text-white transition">← {{ __('Voltar para lista') }}</a>
                <span class="text-zinc-600">/</span>
                <span class="font-mono text-xs text-orange-400 font-bold">{{ $quote->number }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight mt-1">{{ $quote->client_name }} · {{ $quote->project_title ?: 'Vídeos' }}</h1>
            <p class="text-xs text-zinc-400 mt-0.5">
                {{ __('Criado em') }} {{ $quote->created_at->format('d/m/Y H:i') }} · 
                <span class="uppercase font-bold text-orange-400">{{ $quote->status }}</span>
                @if($quote->sent_at)
                    · {{ __('Enviado em') }} {{ $quote->sent_at->format('d/m/Y H:i') }}
                @endif
                @if($quote->accepted_at)
                    · <span class="text-emerald-400 font-bold">✓ {{ __('Aprovado em') }} {{ $quote->accepted_at->format('d/m/Y') }}</span>
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Download PDF -->
            <a href="{{ route('quotes.pdf', $quote) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 font-bold text-xs transition border border-zinc-700">
                <span>📄</span>
                <span>{{ __('Baixar PDF') }}</span>
            </a>

            <!-- Status Buttons -->
            @if(!$quote->isAccepted())
                <form action="{{ route('quotes.update-status', $quote) }}" method="POST" class="inline">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="accepted">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition shadow-md shadow-emerald-950/40">
                        <span>✓</span>
                        <span>{{ __('Marcar como Aprovado') }}</span>
                    </button>
                </form>
            @endif

            <span id="header-flag" class="inline-flex items-center gap-2 bg-amber-500/10 border border-amber-500/20 text-amber-400 px-3 py-1.5 rounded-full text-xs font-bold font-mono">
                {{ $inputs['filmmakers'] ?? 2 }} filmmakers · € {{ number_format($quote->total_amount, 2, ',', '.') }}
            </span>
        </div>
    </div>

    <!-- Layout Grid: 2 Colunas -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Coluna Esquerda: Sticky Monitor & Sofi (5 cols) -->
        <div class="lg:col-span-5 space-y-5 lg:sticky lg:top-20">

            <!-- Abas do Painel Esquerdo -->
            <div class="flex border-b border-zinc-800">
                <button type="button" id="tab-orc"
                        class="pb-2.5 px-3 border-b-2 font-bold text-xs transition border-orange-500 text-orange-500">
                    {{ __('Orçamento & Sofi') }}
                </button>
                <button type="button" id="tab-pdf"
                        class="pb-2.5 px-3 border-b-2 font-bold text-xs transition border-transparent text-zinc-500 hover:text-zinc-300">
                    {{ __('Proposta PDF & Envio') }}
                </button>
            </div>

            <!-- Painel 1: Orçamento & Estimador -->
            <div id="painel-orc" class="space-y-5">
                <!-- Seção: Pedido com Sofi IA -->
                <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-3 shadow-lg shadow-black/30">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-orange-400 flex items-center gap-1.5">
                            <span>✨</span> {{ __('Pedido do cliente') }}
                        </span>
                        <span class="text-[10px] text-zinc-500">{{ __('Sofi IA Parser') }}</span>
                    </div>

                    <div>
                        <textarea id="descricao" name="request_text" rows="3"
                                  class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-3 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500 leading-relaxed font-sans">{{ $quote->request_text }}</textarea>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <button type="button" id="btn-falar"
                                class="px-3 py-1.5 rounded-xl border border-zinc-700 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-semibold transition">
                            🎙 {{ __('Falar o pedido') }}
                        </button>
                        <button type="button" id="btn-estimar"
                                class="px-3.5 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition shadow-md shadow-orange-900/30">
                            {{ __('Recalcular com Sofi IA') }}
                        </button>
                    </div>

                    <div id="estado-estimar" class="text-[11px] text-zinc-500 leading-snug">
                        {{ __('A Sofi atualiza os valores e sugere adaptações baseadas no briefing.') }}
                    </div>

                    <div id="leitura-sofi" class="{{ empty($quote->ai_reading) ? 'hidden' : '' }}">
                        @if(!empty($quote->ai_reading))
                            <div class="p-3.5 rounded-xl bg-orange-950/30 border border-orange-800/40 space-y-2 mt-3">
                                <div class="flex items-center gap-2 text-xs font-bold text-orange-400">
                                    <span>✨</span> <span>{{ $quote->ai_reading['resumo'] ?? '' }}</span>
                                </div>
                                @if(!empty($quote->ai_reading['suposicoes']))
                                    <div>
                                        <div class="text-[10px] font-bold uppercase text-zinc-500">{{ __('Premissas assumidas:') }}</div>
                                        <ul class="text-[11px] text-zinc-300 list-disc pl-4 space-y-0.5 mt-0.5">
                                            @foreach($quote->ai_reading['suposicoes'] as $s)
                                                <li>{{ $s }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Seção: Monitor de Custos e Totais -->
                <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-4 shadow-xl shadow-black/40">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">{{ __('Resumo do Orçamento') }}</h2>
                        <span class="text-[10px] font-mono text-zinc-400 bg-zinc-950 px-2 py-0.5 rounded border border-zinc-800">EUR</span>
                    </div>

                    <div id="res-monitor" class="space-y-1"></div>

                    <div class="flex flex-wrap gap-2 pt-2 border-t border-zinc-800">
                        <button type="button" id="btn-copiar"
                                class="flex-1 py-2 px-3 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs transition">
                            📋 {{ __('Copiar Orçamento') }}
                        </button>
                        <button type="button" id="btn-reset"
                                class="py-2 px-3 rounded-xl border border-zinc-700 hover:bg-zinc-800 text-zinc-300 font-semibold text-xs transition">
                            {{ __('Restaurar Padrão') }}
                        </button>
                    </div>
                    <div id="toast-orc" class="min-h-[16px]"></div>

                    <p class="text-[10px] text-zinc-500 leading-normal border-t border-zinc-800/80 pt-2">
                        {{ $defaults['fonte_hora'] }}
                    </p>
                </div>
            </div>

            <!-- Painel 2: Proposta em PDF & Envio (Espanhol) -->
            <div id="painel-pdf" class="hidden space-y-4">
                <div class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider">{{ __('Despacho da Proposta (SMTP)') }}</h3>
                        <span class="text-[10px] text-emerald-400 bg-emerald-950/40 border border-emerald-800/30 px-2 py-0.5 rounded">PDF Oficial Anexo</span>
                    </div>

                    <div class="space-y-2.5">
                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Para (E-mail do cliente):') }}</label>
                            <input id="em_para" type="email" value="{{ $quote->client_email }}" placeholder="cliente@empresa.com"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:border-orange-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Asunto:') }}</label>
                            <input id="em_assunto" type="text"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Mensaje (Espanhol):') }}</label>
                            <textarea id="em_corpo" rows="8"
                                      class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-orange-500 font-sans leading-relaxed"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-zinc-400 bg-zinc-950 p-2.5 rounded-xl border border-zinc-800">
                        <span>📎 Anexo:</span>
                        <span class="font-mono text-orange-400 font-bold">Presupuesto_{{ $quote->number }}.pdf</span>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2 border-t border-zinc-800">
                        <button type="button" id="btn-enviar-email"
                                class="flex-1 py-2 px-3 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs transition">
                            ➤ {{ __('Enviar Agora via SMTP') }}
                        </button>
                        <button type="button" id="btn-refazer-email"
                                class="py-2 px-3 rounded-xl border border-zinc-700 hover:bg-zinc-800 text-zinc-300 font-semibold text-xs transition">
                            {{ __('Refazer Texto') }}
                        </button>
                    </div>
                    <div id="toast-envio" class="min-h-[16px]"></div>
                </div>

                <!-- Preview visual da proposta formatada -->
                <div class="space-y-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Pré-visualização da Proposta (A4):') }}</span>
                    <div id="preview-proposta"></div>
                </div>
            </div>

        </div>

        <!-- Coluna Direita: Ficha de Pedido e Sliders (7 cols) -->
        <div class="lg:col-span-7">
            <form id="quote-form" action="{{ route('quotes.update', $quote) }}" method="POST" autocomplete="off"
                  class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-6 shadow-xl shadow-black/40">
                @csrf
                @method('PUT')
                <input type="hidden" id="quote_uuid" value="{{ $quote->uuid }}">
                <input type="hidden" id="ai_reading" name="ai_reading" value="{{ !empty($quote->ai_reading) ? json_encode($quote->ai_reading) : '' }}">
                <input type="hidden" id="quote_language" name="language" value="{{ $quote->language ?: 'es' }}">

                <!-- Seção 1: Cliente e Projeto -->
                <div class="space-y-3.5 pb-5 border-b border-zinc-800">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span>👤</span> {{ __('Cliente & Projeto') }}
                        </h2>
                        @if($clientes->isNotEmpty())
                            <select id="cliente_select" name="cliente_id"
                                    class="bg-zinc-950 border border-zinc-800 rounded-xl px-2.5 py-1 text-xs text-zinc-300 focus:outline-none focus:border-orange-500">
                                <option value="">{{ __('+ Cliente Avulso / Selecionar...') }}</option>
                                @foreach($clientes as $cl)
                                    <option value="{{ $cl->id }}" {{ $quote->cliente_id == $cl->id ? 'selected' : '' }}
                                            data-name="{{ $cl->company_name ?: $cl->name }}"
                                            data-email="{{ $cl->email }}"
                                            data-phone="{{ $cl->phone }}">
                                        {{ $cl->company_name ? "{$cl->company_name} ({$cl->name})" : $cl->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Nome do cliente') }} *</label>
                            <input id="cliente" name="client_name" type="text" required value="{{ $quote->client_name }}"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-orange-500">
                        </div>

                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Contato (telefone ou cargo)') }}</label>
                            <input id="contato" name="client_contact" type="text" value="{{ $quote->client_contact }}"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-orange-500">
                        </div>

                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('E-mail do cliente') }}</label>
                            <input id="email" name="client_email" type="email" value="{{ $quote->client_email }}" placeholder="cliente@empresa.com"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-orange-500 font-mono">
                        </div>

                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Título do projeto') }} *</label>
                            <input id="projeto" name="project_title" type="text" required value="{{ $quote->project_title }}"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-orange-500">
                        </div>

                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Quantidade de vídeos') }} *</label>
                            <input id="videos" name="inputs[videos]" type="number" min="1" max="500" step="1" value="{{ $inputs['videos'] ?? 4 }}" required
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-orange-500 font-mono font-bold">
                        </div>

                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Valor unitário por vídeo (€ opcional)') }}</label>
                            <input id="valor_unit" name="inputs[valor_unit]" type="number" min="0" step="10" placeholder="0" value="{{ $inputs['valor_unit'] ?? 0 }}"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-orange-500 font-mono">
                        </div>
                    </div>

                    <!-- Prazo de Entrega (Slider) -->
                    <div class="pt-2">
                        <div class="flex justify-between items-baseline mb-1">
                            <span class="text-xs font-semibold text-zinc-300">{{ __('Prazo de entrega') }}</span>
                            <span id="v_prazo" class="text-sm font-black font-mono text-orange-400">{{ $inputs['prazo'] ?? 15 }} dias</span>
                        </div>
                        <input type="range" id="prazo" name="inputs[prazo]" min="1" max="30" step="1" value="{{ $inputs['prazo'] ?? 15 }}"
                               class="quote-range">
                        <div class="flex justify-between text-[10px] text-zinc-500 font-mono mt-0.5">
                            <span>1 dia</span>
                            <span>15 dias</span>
                            <span>30 dias</span>
                        </div>
                        <p id="hint-prazo" class="text-[11px] text-zinc-500 mt-1"></p>
                    </div>
                </div>

                <!-- Seção 2: Horas de Trabalho (Sliders) -->
                <div class="space-y-4 pb-5 border-b border-zinc-800">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>⏱</span> {{ __('Horas de Trabalho da Equipe') }}
                    </h2>

                    <!-- Pré-produção -->
                    <div>
                        <div class="flex justify-between items-baseline mb-1">
                            <span class="text-xs text-zinc-300">{{ __('Pré-produção') }}</span>
                            <span id="v_pre" class="text-xs font-black font-mono text-orange-400">{{ $inputs['pre'] ?? 2 }} h</span>
                        </div>
                        <input type="range" id="pre" name="inputs[pre]" min="0" max="20" step="0.5" value="{{ $inputs['pre'] ?? 2 }}"
                               class="quote-range">
                        <div class="flex justify-between text-[10px] text-zinc-500 font-mono mt-0.5">
                            <span>0 h</span>
                            <span>10 h</span>
                            <span>20 h</span>
                        </div>
                    </div>

                    <!-- Gravação e Preparação -->
                    <div>
                        <div class="flex justify-between items-baseline mb-1">
                            <span class="text-xs text-zinc-300">{{ __('Gravação e preparação (por filmmaker)') }}</span>
                            <span id="v_gravacao" class="text-xs font-black font-mono text-orange-400">{{ $inputs['gravacao'] ?? 4 }} h</span>
                        </div>
                        <input type="range" id="gravacao" name="inputs[gravacao]" min="0" max="16" step="0.5" value="{{ $inputs['gravacao'] ?? 4 }}"
                               class="quote-range">
                        <div class="flex justify-between text-[10px] text-zinc-500 font-mono mt-0.5">
                            <span>0 h</span>
                            <span>8 h</span>
                            <span>16 h</span>
                        </div>
                    </div>

                    <!-- Edição -->
                    <div>
                        <div class="flex justify-between items-baseline mb-1">
                            <span class="text-xs text-zinc-300">{{ __('Edição e pós-produção') }}</span>
                            <span id="v_edicao" class="text-xs font-black font-mono text-orange-400">{{ $inputs['edicao'] ?? 12 }} h</span>
                        </div>
                        <input type="range" id="edicao" name="inputs[edicao]" min="0" max="40" step="0.5" value="{{ $inputs['edicao'] ?? 12 }}"
                               class="quote-range">
                        <div class="flex justify-between text-[10px] text-zinc-500 font-mono mt-0.5">
                            <span>0 h</span>
                            <span>20 h</span>
                            <span>40 h</span>
                        </div>
                        <p id="hint-edicao" class="text-[11px] text-zinc-500 mt-1"></p>
                    </div>

                    <!-- Motion / VFX / IA -->
                    <div>
                        <div class="flex justify-between items-baseline mb-1">
                            <span class="text-xs text-zinc-300">{{ __('Motion graphics, VFX ou IA') }}</span>
                            <span id="v_motion" class="text-xs font-black font-mono text-orange-400">{{ $inputs['motion'] ?? 0 }} h</span>
                        </div>
                        <input type="range" id="motion" name="inputs[motion]" min="0" max="30" step="0.5" value="{{ $inputs['motion'] ?? 0 }}"
                               class="quote-range">
                        <div class="flex justify-between text-[10px] text-zinc-500 font-mono mt-0.5">
                            <span>0 h</span>
                            <span>15 h</span>
                            <span>30 h</span>
                        </div>
                    </div>
                </div>

                <!-- Seção 3: Filmmakers & Tarifas -->
                <div class="space-y-4 pb-5 border-b border-zinc-800">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>🎥</span> {{ __('Equipe de Filmmakers & Tarifas Horárias') }}
                    </h2>

                    @php
                        $fmVal = (int) ($inputs['filmmakers'] ?? 2);
                        $fmRadio = $fmVal >= 4 ? 4 : $fmVal;
                    @endphp
                    <!-- Chips de Filmmakers -->
                    <div class="flex flex-wrap gap-2">
                        @foreach([1, 2, 3, 4] as $fmOpt)
                            <label class="cursor-pointer">
                                <input type="radio" name="fm" value="{{ $fmOpt }}" {{ $fmRadio == $fmOpt ? 'checked' : '' }} class="peer sr-only">
                                <span class="inline-block px-3.5 py-1.5 rounded-full border border-zinc-800 text-xs font-semibold text-zinc-300 peer-checked:bg-orange-600 peer-checked:text-white peer-checked:border-orange-500 transition">
                                    {{ $fmOpt == 4 ? '4 ou mais' : "{$fmOpt} filmmaker" . ($fmOpt > 1 ? 's' : '') }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <input type="hidden" name="inputs[filmmakers]" id="hidden_filmmakers" value="{{ $fmVal }}">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <div id="lbl-fm4" class="{{ $fmRadio >= 4 ? '' : 'hidden' }}">
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Quantos filmmakers?') }}</label>
                            <input id="fm_n" name="inputs[fm_n]" type="number" min="4" max="50" step="1" value="{{ $fmVal >= 4 ? $fmVal : 4 }}"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-orange-500 font-mono">
                        </div>

                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Tarifa Filmmaker (€/h)') }} *</label>
                            <input id="valor_hora" name="inputs[valor_hora]" type="number" min="0" step="0.01" value="{{ $inputs['valor_hora'] ?? $defaults['valor_hora'] }}" required
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-orange-500 font-mono">
                        </div>

                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Tarifa Editor (€/h)') }} *</label>
                            <input id="valor_hora_editor" name="inputs[valor_hora_editor]" type="number" min="0" step="0.01" value="{{ $inputs['valor_hora_editor'] ?? $defaults['valor_hora_editor'] }}" required
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-orange-500 font-mono">
                        </div>
                    </div>
                </div>

                <!-- Seção 4: Serviços Extras, Drone, Margem & IVA -->
                <div class="space-y-4 pb-5 border-b border-zinc-800">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>⚡</span> {{ __('Serviços Extras, Dron & Tributos') }}
                    </h2>

                    <!-- Extras 1, 2, 3 -->
                    <div class="space-y-2">
                        @for($i = 1; $i <= 3; $i++)
                            @php
                                $exItem = $extras[$i - 1] ?? null;
                            @endphp
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                                <div class="sm:col-span-8">
                                    <input id="ex_nome_{{ $i }}" name="inputs[extras][{{ $i }}][nome]" type="text"
                                           value="{{ $exItem['nome'] ?? '' }}"
                                           placeholder="Ej.: {{ $i == 1 ? 'locución profesional' : ($i == 2 ? 'subtítulos en inglés' : 'maquillaje') }}"
                                           class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:border-orange-500">
                                </div>
                                <div class="sm:col-span-4">
                                    <input id="ex_valor_{{ $i }}" name="inputs[extras][{{ $i }}][valor]" type="number" min="0" step="10" placeholder="€ Valor"
                                           value="{{ isset($exItem['valor']) && $exItem['valor'] > 0 ? $exItem['valor'] : '' }}"
                                           class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:border-orange-500 font-mono">
                                </div>
                            </div>
                        @endfor
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-2">
                        <!-- Drone Checkbox + Valor -->
                        <div class="flex items-center gap-3 bg-zinc-950 p-2.5 rounded-xl border border-zinc-800">
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-white">
                                <input id="drone" name="inputs[drone]" type="checkbox" value="1" {{ !empty($inputs['drone']) ? 'checked' : '' }}
                                       class="w-4 h-4 rounded text-orange-600 focus:ring-0">
                                <span>🛸 {{ __('Incluir Dron') }}</span>
                            </label>
                            <input id="drone_valor" name="inputs[drone_valor]" type="number" min="0" step="10" value="{{ $inputs['drone_valor'] ?? $defaults['drone_valor'] }}"
                                   class="w-24 bg-zinc-900 border border-zinc-800 rounded-lg px-2 py-1 text-xs text-white font-mono text-right ml-auto">
                        </div>

                        <!-- IVA -->
                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Alíquota de IVA (%)') }}</label>
                            <input id="iva" name="inputs[iva]" type="number" min="0" max="100" step="1" value="{{ $inputs['iva'] ?? $defaults['iva'] }}"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white font-mono">
                        </div>

                        <!-- Margem Interna -->
                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Margem de Ganho (%) · Interna') }}</label>
                            <input id="margem" name="inputs[margem]" type="number" min="0" max="100" step="1" value="{{ $inputs['margem'] ?? $defaults['margem'] }}"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white font-mono">
                        </div>

                        <!-- Ajuste Adicional / Desconto -->
                        <div>
                            <label class="block text-[11px] text-zinc-400 mb-1">{{ __('Ajuste Adicional (€) · Interno') }}</label>
                            <input id="adicional" name="inputs[adicional]" type="number" step="10" value="{{ $inputs['adicional'] ?? 0 }}" placeholder="0"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white font-mono">
                        </div>
                    </div>

                    <!-- Câmbios por vídeo -->
                    <div class="pt-2">
                        <span class="block text-[11px] text-zinc-400 mb-1.5">{{ __('Alterações/Câmbios incluídos por vídeo:') }}</span>
                        <div class="flex gap-2">
                            @php $cambiosVal = (int) ($inputs['cambios'] ?? 3); @endphp
                            @foreach([1, 2, 3] as $cb)
                                <label class="cursor-pointer">
                                    <input type="radio" name="cambios" value="{{ $cb }}" {{ $cambiosVal == $cb ? 'checked' : '' }} class="peer sr-only">
                                    <span class="inline-block px-4 py-1.5 rounded-full border border-zinc-800 text-xs font-semibold text-zinc-300 peer-checked:bg-orange-600 peer-checked:text-white peer-checked:border-orange-500 transition">
                                        {{ $cb }} {{ $cb == 1 ? 'câmbio' : 'câmbios' }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Botão de Ação -->
                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('quotes.index') }}" class="text-xs text-zinc-400 hover:text-white transition">
                        {{ __('Cancelar') }}
                    </a>

                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-extrabold text-sm transition shadow-lg shadow-orange-900/30">
                        💾 {{ __('Salvar Alterações') }}
                    </button>
                </div>
            </form>
        </div>

    </div>

</div>

<script src="{{ asset('js/quotes-calculator.js') }}?v={{ file_exists(public_path('js/quotes-calculator.js')) ? filemtime(public_path('js/quotes-calculator.js')) : time() }}"></script>
@endsection
