@extends('layouts.app')

@section('title', __('Sofi Batch Content Planner | NUME'))

@section('content')
<div class="space-y-6">

    <!-- Top Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-zinc-400 mb-1">
                <a href="{{ route('calendar.index') }}" class="hover:text-zinc-200 transition">{{ __('Calendário') }}</a>
                <span class="text-zinc-600">/</span>
                <span class="text-orange-400">{{ __('Sofi Batch Planner') }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-3">
                <span>⚡ {{ __('Planejador Mensal de Conteúdo em Lote') }}</span>
                <span class="text-xs font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-orange-500/10 text-orange-400 border border-orange-500/20">
                    Sofi IA Pro
                </span>
            </h1>
            <p class="text-xs text-zinc-400 mt-1">
                {{ __('Gere o planejamento editorial completo do cliente para 30 dias em segundos (4, 8 ou 12 roteiros de alta retenção calibrados pelo DNA da marca).') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('calendar.index') }}"
               class="px-3.5 py-2 text-xs font-semibold bg-zinc-800 hover:bg-zinc-700 text-zinc-200 rounded-xl border border-zinc-700 transition flex items-center gap-2">
                <span>🗓️</span>
                <span>{{ __('Ver Calendário Geral') }}</span>
            </a>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if(session('success'))
        <div class="rounded-2xl bg-emerald-950/40 border border-emerald-500/40 p-4 text-emerald-300 text-xs font-semibold flex items-start gap-3">
            <span class="text-lg">✨</span>
            <div>
                <p class="font-bold text-emerald-200">{{ __('Sucesso!') }}</p>
                <p class="mt-0.5">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl bg-rose-950/40 border border-rose-500/40 p-4 text-rose-300 text-xs font-semibold">
            <p class="font-bold text-rose-200 mb-1">{{ __('Verifique os campos informados:') }}</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Formulário de Parâmetros da Campanha -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-5 sm:p-6 shadow-xl">
        <form method="POST" action="{{ route('batch_planner.generate') }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- Cliente -->
                <div>
                    <label for="cliente_id" class="block text-xs font-bold uppercase tracking-wider text-zinc-300 mb-1.5">
                        {{ __('Cliente da Produtora') }} <span class="text-orange-500">*</span>
                    </label>
                    <select id="cliente_id" name="cliente_id" required
                            class="w-full bg-zinc-950 border border-zinc-700/80 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                        <option value="">{{ __('Selecione o Cliente...') }}</option>
                        @foreach($clientes as $c)
                            <option value="{{ $c->id }}" {{ (old('cliente_id', $selectedClienteId) == $c->id) ? 'selected' : '' }}>
                                {{ $c->company_name ?: $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Tema Central do Mês -->
                <div class="md:col-span-1 lg:col-span-2">
                    <label for="theme" class="block text-xs font-bold uppercase tracking-wider text-zinc-300 mb-1.5">
                        {{ __('Tema Central ou Foco da Campanha') }} <span class="text-orange-500">*</span>
                    </label>
                    <input type="text" id="theme" name="theme" required
                           value="{{ old('theme', $planMeta['theme'] ?? '') }}"
                           placeholder="{{ __('Ex: Campanha de Inverno, Lançamento do Produto X, Desmistificando Serviços...') }}"
                           class="w-full bg-zinc-950 border border-zinc-700/80 rounded-xl px-3 py-2.5 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                </div>

                <!-- Quantidade de Vídeos -->
                <div>
                    <label for="quantity" class="block text-xs font-bold uppercase tracking-wider text-zinc-300 mb-1.5">
                        {{ __('Quantidade no Mês') }} <span class="text-orange-500">*</span>
                    </label>
                    <select id="quantity" name="quantity" required
                            class="w-full bg-zinc-950 border border-zinc-700/80 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                        <option value="4" {{ old('quantity', $planMeta['quantity'] ?? 4) == 4 ? 'selected' : '' }}>
                            4 {{ __('Vídeos (1 por semana)') }}
                        </option>
                        <option value="8" {{ old('quantity', $planMeta['quantity'] ?? '') == 8 ? 'selected' : '' }}>
                            8 {{ __('Vídeos (2 por semana)') }}
                        </option>
                        <option value="12" {{ old('quantity', $planMeta['quantity'] ?? '') == 12 ? 'selected' : '' }}>
                            12 {{ __('Vídeos (3 por semana)') }}
                        </option>
                    </select>
                </div>

            </div>

            <!-- Idioma e Notas Adicionais da Reunião -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t border-zinc-800/80 pt-4">
                
                <div>
                    <label for="language" class="block text-xs font-bold uppercase tracking-wider text-zinc-300 mb-1.5">
                        {{ __('Idioma dos Roteiros') }}
                    </label>
                    <select id="language" name="language"
                            class="w-full bg-zinc-950 border border-zinc-700/80 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                        <option value="pt_BR" {{ old('language', $planMeta['language'] ?? 'pt_BR') === 'pt_BR' ? 'selected' : '' }}>🇧🇷 Português (Brasil)</option>
                        <option value="es" {{ old('language', $planMeta['language'] ?? '') === 'es' ? 'selected' : '' }}>🇪🇸 Español (España / Latam)</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="notes" class="block text-xs font-bold uppercase tracking-wider text-zinc-300 mb-1.5">
                        {{ __('Contexto ou Diretrizes da Reunião de Pauta (Opcional)') }}
                    </label>
                    <input type="text" id="notes" name="notes"
                           value="{{ old('notes') }}"
                           placeholder="{{ __('Ex: O cliente quer enfatizar o parcelamento em 10x sem juros e não quer citar o concorrente Y.') }}"
                           class="w-full bg-zinc-950 border border-zinc-700/80 rounded-xl px-3 py-2.5 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                </div>

            </div>

            <div class="flex justify-end pt-2">
                <button type="submit"
                        class="flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white font-extrabold text-xs shadow-lg shadow-orange-600/30 hover:scale-[1.01] active:scale-[0.99] transition-all">
                    <span>✨</span>
                    <span>{{ __('Gerar Plano Mensal Completo com Sofi IA') }}</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Visualização do Plano Gerado em Lote -->
    @if(!empty($generatedPlan))
        <div class="space-y-4">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 bg-zinc-900 border border-orange-500/30 rounded-2xl">
                <div>
                    <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                        <span>📋</span>
                        <span>{{ __('Roteiros Estratégicos Gerados') }} ({{ count($generatedPlan) }} {{ __('Vídeos') }})</span>
                    </h2>
                    <p class="text-xs text-zinc-400 mt-0.5">
                        {{ __('Revise os ganchos e scripts. Ao clicar no botão abaixo, todas as produções e roteiros serão criados e distribuídos automaticamente no calendário.') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('batch_planner.commit') }}">
                    @csrf
                    <input type="hidden" name="cliente_id" value="{{ $planMeta['cliente_id'] }}">
                    
                    @foreach($generatedPlan as $idx => $item)
                        <input type="hidden" name="items[{{ $idx }}][title]" value="{{ $item['title'] }}">
                        <input type="hidden" name="items[{{ $idx }}][description]" value="{{ $item['description'] }}">
                        <input type="hidden" name="items[{{ $idx }}][script_content]" value="{{ $item['script_content'] }}">
                        <input type="hidden" name="items[{{ $idx }}][suggested_date]" value="{{ $item['suggested_date'] }}">
                    @endforeach

                    @unless($planApproved)
                        <div class="mb-3 p-3 rounded-xl bg-amber-950/60 border border-amber-500/40 text-amber-200 text-xs space-y-2">
                            <p class="font-bold text-amber-300">{{ __('O cliente ainda não aprovou o plano de marketing') }}</p>
                            @can('createWithoutPlan', \App\Models\Roteiro::class)
                                <label for="plan_override_reason" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Justificativa') }} *</label>
                                <textarea id="plan_override_reason" name="plan_override_reason" rows="2" maxlength="1000" required
                                          class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-500"></textarea>
                            @else
                                <p class="text-zinc-300">{{ __('Peça ao cliente para aprovar o plano no portal, ou fale com um admin da produtora.') }}</p>
                            @endcan
                        </div>
                    @endunless
                    @error('plan_override_reason') <p class="mb-2 text-xs text-rose-400">{{ $message }}</p> @enderror

                    <button type="submit"
                            class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2 transition">
                        <span>🚀</span>
                        <span>{{ __('Criar Todas as Produções & Agendar no Calendário') }}</span>
                    </button>
                </form>
            </div>

            <!-- Grade de Cards com Roteiros -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach($generatedPlan as $idx => $item)
                    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-5 shadow-lg space-y-4 flex flex-col justify-between">
                        
                        <div>
                            <!-- Topo do Card -->
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-orange-500/20 text-orange-400 border border-orange-500/30">
                                        {{ __('Vídeo #') }}{{ $item['item_number'] }} • {{ __('Semana') }} {{ (int) ceil($item['target_day_offset'] / 7) }}
                                    </span>
                                    @php
                                        $fStage = $item['funnel_stage'] ?? 'meio';
                                        $fBadgeClass = match($fStage) {
                                            'topo' => 'bg-sky-500/10 text-sky-400 border-sky-500/30',
                                            'fundo' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                            default => 'bg-purple-500/10 text-purple-400 border-purple-500/30',
                                        };
                                        $fIcon = match($fStage) {
                                            'topo' => '🎯',
                                            'fundo' => '💰',
                                            default => '💎',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $fBadgeClass }}">
                                        {{ $fIcon }} {{ $item['funnel_label'] ?? __('Etapa de Funil') }}
                                    </span>
                                </div>
                                <span class="text-xs font-bold text-zinc-400 flex items-center gap-1 shrink-0">
                                    <span>🗓️</span>
                                    <span>{{ \Carbon\Carbon::parse($item['suggested_date'])->format('d/m/Y') }}</span>
                                </span>
                            </div>

                            <!-- Título da Produção -->
                            <h3 class="text-sm font-extrabold text-white leading-snug">
                                {{ $item['title'] }}
                            </h3>

                            <p class="text-[11px] text-zinc-400 mt-1">
                                {{ $item['description'] }}
                            </p>

                            <!-- Gancho de Retenção (0-3s) -->
                            @if(!empty($item['hook']))
                                <div class="mt-3 p-2.5 bg-orange-950/20 border border-orange-500/30 rounded-xl">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-orange-400 block mb-0.5">
                                        ⚡ {{ __('Gancho de Retenção (0-3s):') }}
                                    </span>
                                    <p class="text-xs text-orange-200 font-medium italic">
                                        "{{ $item['hook'] }}"
                                    </p>
                                </div>
                            @endif

                            <!-- Roteiro Estruturado Retrátil -->
                            <details class="mt-3 group bg-zinc-950/60 border border-zinc-800 rounded-xl p-3">
                                <summary class="flex items-center justify-between text-xs font-bold text-zinc-300 hover:text-white cursor-pointer list-none">
                                    <span>📝 {{ __('Ver Roteiro Completo') }}</span>
                                    <span class="group-open:rotate-180 transition-transform">▼</span>
                                </summary>
                                <div class="mt-3 pt-3 border-t border-zinc-800 text-xs text-zinc-300 font-mono whitespace-pre-line leading-relaxed">
{{ $item['script_content'] }}
                                </div>
                            </details>
                        </div>

                        <!-- Rodapé do Card -->
                        <div class="pt-3 border-t border-zinc-800/80 flex items-center justify-between text-[11px] text-zinc-500">
                            <span class="font-semibold uppercase tracking-wider text-[10px]">
                                {{ __('Framework:') }} <span class="text-zinc-300">{{ str_replace('_', ' ', $item['framework']) }}</span>
                            </span>
                            <span class="text-emerald-400 font-bold flex items-center gap-1">
                                <span>✓</span>
                                <span>{{ __('Pronto para gravação') }}</span>
                            </span>
                        </div>

                    </div>
                @endforeach
            </div>

        </div>
    @endif

</div>
@endsection
