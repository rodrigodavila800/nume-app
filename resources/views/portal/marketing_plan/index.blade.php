@extends('layouts.portal')

@section('title', __('Plano Estratégico & Grade Editorial | Portal NUME'))

@section('content')
@php
    $totalItems = $plan->items->count();
    $inProductionCount = $plan->items->filter(fn($i) => !empty($i->production_id))->count();
    $allInProduction = $totalItems > 0 && $inProductionCount >= $totalItems;
@endphp

<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header da Página -->
        <div class="md:flex md:items-center md:justify-between mb-8 pb-6 border-b border-zinc-200">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-800 border border-orange-200">
                        ⚡ {{ __('Sofi IA Estrategista') }}
                    </span>
                    <span class="text-xs font-semibold text-zinc-500">
                        📅 {{ \Carbon\Carbon::createFromFormat('Y-m', $plan->month_year)->translatedFormat('F / Y') }}
                    </span>
                    @if($inProductionCount > 0)
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            ✓ {{ $inProductionCount }}/{{ $totalItems }} {{ __('em produção') }}
                        </span>
                    @endif
                </div>
                <h1 class="mt-2 text-2xl font-extrabold text-zinc-900 tracking-tight sm:text-3xl">
                    {{ __('Grade Editorial & Plano de Marketing') }}
                </h1>
                <p class="mt-1 text-sm text-zinc-600">
                    {{ __('Cronograma científico de distribuição dos seus vídeos com ganchos de alta retenção (0-3s), SEO e funil de vendas.') }}
                </p>
            </div>
            
            <div class="mt-4 flex md:ml-4 md:mt-0 gap-3">
                @if($allInProduction)
                    <button type="button" disabled class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200 cursor-not-allowed opacity-90 shadow-sm" title="{{ __('Todos os vídeos desta grade já foram enviados para produção na esteira.') }}">
                        <span>✓</span>
                        <span>{{ __('Grade 100% em Produção') }}</span>
                    </button>
                @else
                    <form id="form-regenerate-plan" action="{{ route('portal.marketing-plan.regenerate') }}" method="POST">
                        @csrf
                        <button type="button" onclick="confirmRegeneratePlan(event)" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-zinc-700 bg-white border border-zinc-300 shadow-sm hover:bg-zinc-50 hover:text-zinc-900 transition cursor-pointer">
                            <span>🔄</span>
                            <span>{{ __('Recalcular com Sofi IA') }}</span>
                        </button>
                    </form>
                @endif

                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-orange-600 hover:bg-orange-700 shadow-sm transition">
                    <span>🖨️</span>
                    <span>{{ __('Imprimir / Salvar PDF') }}</span>
                </button>
            </div>
        </div>

        @if(session('status'))
            <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if(session('warning'))
            <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-semibold flex items-center gap-2">
                <span>⚠️</span>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold">{{ session('error') }}</div>
        @endif

        <!-- Aprovação do plano pelo cliente -->
        @if($plan->isApproved())
            <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <span class="font-bold">✓ {{ __('Plano aprovado em :date. Os vídeos já podem seguir para orçamento e produção.', ['date' => $plan->approved_at?->format('d/m/Y')]) }}</span>
                <div class="flex items-center gap-3">
                    <a href="{{ route('portal.anamnesis.edit') }}" class="text-xs font-bold text-emerald-800 hover:underline">{{ __('Ver anamnese') }}</a>
                    <a href="{{ route('portal.quotes.request') }}" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-orange-600 hover:bg-orange-500 transition">{{ __('Pedir orçamento') }}</a>
                </div>
            </div>
        @else
            <div class="mb-6 p-5 rounded-2xl bg-amber-50 border border-amber-200 space-y-4 print:hidden">
                <div>
                    <p class="text-sm font-extrabold text-amber-900">{{ __('Este plano é uma proposta da Sofi e precisa da sua aprovação') }}</p>
                    <p class="text-xs text-amber-800 mt-1">{{ __('Depois de aprovado, os vídeos seguem para orçamento e roteiro. Se algo não estiver certo, peça um ajuste e a Sofi refaz o plano.') }}</p>
                    @if($plan->adjustment_request)
                        <p class="text-xs text-amber-900 mt-2"><span class="font-bold">{{ __('Último ajuste pedido:') }}</span> {{ $plan->adjustment_request }}</p>
                    @endif
                </div>
                <div class="flex flex-col lg:flex-row gap-3">
                    <form action="{{ route('portal.marketing-plan.approve', $plan->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full lg:w-auto inline-flex justify-center items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-extrabold text-white bg-emerald-600 hover:bg-emerald-500 shadow-sm transition">
                            ✓ {{ __('Aprovar plano') }}
                        </button>
                    </form>
                    <form action="{{ route('portal.marketing-plan.adjust', $plan->id) }}" method="POST" class="flex-1 flex flex-col sm:flex-row gap-2">
                        @csrf
                        <label for="adjustment_request" class="sr-only">{{ __('O que você quer mudar no plano?') }}</label>
                        <input id="adjustment_request" name="adjustment_request" type="text" minlength="10" maxlength="1000" required
                            placeholder="{{ __('O que você quer mudar? Ex.: mais vídeos de bastidores, menos oferta direta') }}"
                            class="flex-1 text-sm px-3 py-2.5 rounded-xl border border-amber-300 bg-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20">
                        <button type="submit" class="inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-sm font-bold text-amber-900 bg-white border border-amber-300 hover:bg-amber-100 transition">
                            {{ __('Pedir ajuste') }}
                        </button>
                    </form>
                </div>
                @error('adjustment_request') <p class="text-xs text-rose-700">{{ $message }}</p> @enderror
            </div>
        @endif

        <!-- Card de Meta Estratégica do Mês -->
        <div class="mb-8 rounded-2xl bg-gradient-to-br from-zinc-900 via-zinc-900 to-zinc-950 p-6 sm:p-8 text-white shadow-xl border border-zinc-800 relative overflow-hidden">
            <div class="relative z-10">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-orange-500/20 text-orange-400 border border-orange-500/30">
                        🎯 {{ __('Objetivo Estratégico do Período') }}
                    </span>
                    <span class="text-xs text-zinc-400 font-mono">
                        {{ __('Cota:') }} {{ $plan->items->count() }} {{ __('vídeos programados') }}
                    </span>
                </div>

                <p class="text-lg sm:text-xl font-medium text-zinc-100 leading-relaxed max-w-4xl">
                    "{{ $plan->strategic_goal }}"
                </p>

                <!-- Grid de KPIs da Estratégia -->
                <div class="mt-6 pt-6 border-t border-zinc-800 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <span class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">{{ __('Retenção Estimada') }}</span>
                        <span class="text-xl font-extrabold text-orange-400 font-mono">94%</span>
                    </div>
                    <div>
                        <span class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">{{ __('Estrutura de Vídeo') }}</span>
                        <span class="text-sm font-semibold text-zinc-200">{{ __('3 Atos (Gancho 0-3s)') }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">{{ __('Padrão Visual') }}</span>
                        <span class="text-sm font-semibold text-zinc-200">{{ __('4K HDR Cinematográfico') }}</span>
                    </div>
                    <div>
                        <span class="block text-xs font-bold text-zinc-400 uppercase tracking-wider">{{ __('Foco Algorítmico') }}</span>
                        <span class="text-sm font-semibold text-zinc-200">{{ __('Reels, TikTok & Shorts') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grade Editorial Semanal dos Vídeos -->
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-zinc-900 flex items-center gap-2">
                    <span>📅</span>
                    <span>{{ __('Cronograma de Postagens') }}</span>
                </h2>
                <span class="text-xs text-zinc-500 font-medium">
                    {{ __('Passe o mouse ou toque para copiar legendas e ganchos') }}
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @forelse($plan->items as $index => $item)
                    <div class="rounded-2xl bg-white border border-zinc-200 p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                        <div>
                            <!-- Header do Item -->
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-lg bg-orange-100 text-orange-800 font-extrabold text-xs flex items-center justify-center font-mono">
                                        #{{ $index + 1 }}
                                    </span>
                                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500">
                                        {{ __('Semana') }} {{ $item->week_number }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <!-- Funil Badge -->
                                    @if($item->funnel_stage === 'top')
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                            🚀 {{ __('Topo: Atração') }}
                                        </span>
                                    @elseif($item->funnel_stage === 'middle')
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                            💎 {{ __('Meio: Autoridade') }}
                                        </span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            🎯 {{ __('Fundo: Oferta') }}
                                        </span>
                                    @endif

                                    @if($item->videoType)
                                        <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-orange-50 text-orange-800 border border-orange-100">🎬 {{ $item->videoType->name }}</span>
                                    @endif

                                    <!-- Formato Badge -->
                                    <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-zinc-100 text-zinc-700 font-mono">
                                        {{ $item->format === 'vertical' ? '📱 9:16' : '🎬 16:9' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Tópico & Dia de Postagem -->
                            <h3 class="text-base font-bold text-zinc-900 mt-2">
                                {{ $item->topic }}
                            </h3>
                            <div class="flex items-center gap-2 text-xs text-orange-600 font-semibold mt-1">
                                <span>⏰</span>
                                <span>{{ __('Melhor momento sugerido:') }} {{ __($item->scheduled_day_time) }}</span>
                            </div>

                            <!-- Gancho de Scroll-Stop (0-3s) -->
                            <div class="mt-4 p-3.5 rounded-xl bg-orange-50/60 border border-orange-100">
                                <span class="block text-[11px] font-extrabold uppercase tracking-wider text-orange-800 mb-1">
                                    ⚡ {{ __('Gancho dos Primeiros 3 Segundos (Hook):') }}
                                </span>
                                <p class="text-xs text-zinc-800 font-medium italic">
                                    "{{ $item->hook_text }}"
                                </p>
                            </div>

                            <!-- Legenda & Hashtags -->
                            <div class="mt-3 p-3.5 rounded-xl bg-zinc-50 border border-zinc-100">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">
                                        📝 {{ __('Legenda Otimizada para SEO:') }}
                                    </span>
                                    <button type="button" 
                                            onclick="navigator.clipboard.writeText(`{{ addslashes($item->seo_caption) }}`); if(window.sofiToast) window.sofiToast('{{ __('Legenda copiada para a área de transferência!') }}');"
                                            class="text-[11px] font-bold text-orange-600 hover:text-orange-700 transition cursor-pointer">
                                        📋 {{ __('Copiar') }}
                                    </button>
                                </div>
                                <p class="text-xs text-zinc-700 line-clamp-3">
                                    {{ $item->seo_caption }}
                                </p>
                            </div>
                        </div>

                        <!-- Footer com Status e Ação de Conversão em 1 Clique -->
                        <div class="mt-5 pt-4 border-t border-zinc-100 flex flex-col gap-2.5 print:hidden">
                            @if ($item->production_id && $item->production)
                                <a href="{{ route('portal.productions.show', $item->production) }}" 
                                   class="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition shadow-xs">
                                    <span>✓</span>
                                    <span>{{ __('Em Produção na Esteira') }}</span>
                                    <span>→</span>
                                </a>
                            @elseif (! $plan->isApproved())
                                <p class="text-xs text-zinc-500 text-center">{{ __('Aprove o plano para enviar este vídeo à produção.') }}</p>
                            @else
                                <form action="{{ route('portal.marketing-plan.convert-item', $item) }}" method="POST" class="w-full">
                                    @csrf
                                    <button type="submit" 
                                            class="w-full inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-bold bg-orange-600 hover:bg-orange-700 active:scale-98 text-white shadow-md shadow-orange-900/15 transition cursor-pointer">
                                        <span>🎬</span>
                                        <span>{{ __('Transformar em Produção Audiovisual') }}</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full p-8 text-center bg-white rounded-2xl border border-zinc-200">
                        <p class="text-sm text-zinc-500">
                            {{ __('Nenhum item na grade editorial deste mês.') }}
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
    const inProductionCount = {{ $inProductionCount }};
    const confirmMessage = inProductionCount > 0
        ? '{{ __("A Sofi irá recalcular apenas as ideias que ainda não viraram produção. Os :count vídeos que você já enviou para a esteira continuarão salvos e intactos.", ["count" => $inProductionCount]) }}'
        : '{{ __("A Sofi irá analisar novamente o DNA da sua marca e gerar novos ganchos de 0-3s, títulos SEO e horários recomendados para seus vídeos.") }}';

    function confirmRegeneratePlan(e) {
        if (e) e.preventDefault();
        
        window.sofiConfirm({
            title: '{{ __("Recalcular Grade com Sofi IA?") }}',
            text: confirmMessage,
            confirmText: '{{ __("Sim, Recalcular") }}',
            cancelText: '{{ __("Cancelar") }}',
            icon: 'question'
        }).then((result) => {
            if (result.isConfirmed) {
                // Efeito visual de carregamento no botão
                const btn = e.target.closest('button');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span>⏳</span> <span>{{ __("Gerando com Sofi IA...") }}</span>';
                }
                document.getElementById('form-regenerate-plan').submit();
            }
        });
    }
</script>
@endpush
@endsection

