@extends('layouts.portal')

@section('title', __('Painel de Produção & Estratégia | NUME Portal'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

    @if (session('status'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 px-5 py-4 rounded-2xl text-sm font-semibold flex items-center gap-3 shadow-xs">
            <span class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('warning'))
        <div class="bg-amber-500/10 border-2 border-amber-500/40 text-amber-900 px-5 py-4 rounded-2xl text-sm font-semibold flex items-center gap-3 shadow-xs">
            <span class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </span>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    {{-- Hero Executivo --}}
    <div class="bg-gradient-to-br from-zinc-950 via-zinc-900 to-zinc-950 rounded-3xl p-6 sm:p-8 text-white border border-zinc-800 shadow-2xl relative overflow-hidden">
        <div class="absolute right-0 top-0 translate-x-1/4 -translate-y-1/4 w-96 h-96 bg-orange-600/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider bg-orange-500/20 text-orange-400 border border-orange-500/30 mb-3">
                    <span class="w-2 h-2 rounded-full bg-orange-400 animate-pulse"></span>
                    {{ __('Painel Executivo de Vídeos') }}
                </span>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                    {{ __('Sua Esteira de Conteúdo & Retenção') }}
                </h1>
                <p class="mt-2 text-sm sm:text-base text-zinc-300 max-w-2xl leading-relaxed">
                    {{ __('Acompanhe em tempo real a evolução dos seus roteiros, aprove novas narrativas e monitore a entrega de vídeos projetados para converter audiência em clientes.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <a href="{{ route('portal.packs.index') }}"
                   class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-orange-600 hover:bg-orange-500 text-white shadow-lg shadow-orange-900/30 transition-all transform active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>{{ __('Contratar Novos Vídeos') }}</span>
                </a>
            </div>
        </div>
    </div>

    @php
        $hasCompletedProfile = auth('cliente')->user()?->hasCompletedBrandProfile();

        $statusLabel = [
            'draft'          => __('Rascunho'),
            'in_production'  => __('Em Produção'),
            'pending_review' => __('Aguardando Revisão'),
            'approved'       => __('Aprovado'),
            'published'      => __('Publicado'),
            'archived'       => __('Arquivado'),
        ];

        $statusClass = [
            'draft'          => 'bg-zinc-100 text-zinc-700 border border-zinc-200',
            'in_production'  => 'bg-orange-100 text-orange-800 border border-orange-200',
            'pending_review' => 'bg-amber-100 text-amber-800 border border-amber-200',
            'approved'       => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
            'published'      => 'bg-blue-100 text-blue-800 border border-blue-200',
            'archived'       => 'bg-zinc-100 text-zinc-500 border border-zinc-200',
        ];
    @endphp

    @if (! $hasCompletedProfile)
        <div class="bg-gradient-to-r from-amber-500/15 via-orange-500/10 to-amber-500/5 rounded-2xl p-5 sm:p-6 border-2 border-orange-500/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
            <div class="flex items-start gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-orange-600 text-white flex items-center justify-center text-xl shrink-0 shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div class="space-y-1">
                    <h2 class="text-sm font-bold text-zinc-900 flex items-center gap-2">
                        <span>{{ __('Complete o Perfil da sua Marca para Desbloquear as Produções') }}</span>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-orange-600 text-white">{{ __('Obrigatório') }}</span>
                    </h2>
                    <p class="text-xs text-zinc-600 max-w-2xl leading-relaxed">
                        {{ __('Para abrir seus projetos e aprovar roteiros, precisamos calibrar os dados essenciais da sua marca (nicho de mercado, público-alvo e canais de contato).') }}
                    </p>
                </div>
            </div>
            <a href="{{ route('portal.profile.edit') }}"
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-95 text-white font-bold text-xs uppercase tracking-wider shadow-md shadow-orange-900/20 transition whitespace-nowrap shrink-0">
                <span>{{ __('Preencher Perfil Agora') }}</span>
                <span>&rarr;</span>
            </a>
        </div>
    @endif

    {{-- Cards de Estatísticas e Métricas --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
        <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-xs hover:border-orange-300 transition-all">
            <div class="flex items-center justify-between">
                <p class="text-xs font-extrabold uppercase tracking-wider text-zinc-500">{{ __('Produções em Esteira') }}</p>
                <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <span class="text-3xl font-black text-zinc-900">{{ $productions->total() }}</span>
                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-orange-100 text-orange-800 border border-orange-200/60">{{ __('Em acompanhamento') }}</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-xs hover:border-emerald-300 transition-all">
            <div class="flex items-center justify-between">
                <p class="text-xs font-extrabold uppercase tracking-wider text-zinc-500">{{ __('Contratos & Assinaturas') }}</p>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <span class="text-3xl font-black text-zinc-900">{{ $activeSubscriptions->count() }}</span>
                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full {{ $activeSubscriptions->isNotEmpty() ? 'bg-emerald-100 text-emerald-800 border border-emerald-200/60' : 'bg-zinc-100 text-zinc-600 border border-zinc-200/60' }}">
                    {{ $activeSubscriptions->isNotEmpty() ? __('Assinatura Ativa') : __('Sem contrato ativo') }}
                </span>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-xs hover:border-purple-300 transition-all">
            <div class="flex items-center justify-between">
                <p class="text-xs font-extrabold uppercase tracking-wider text-zinc-500">{{ __('Padrão Sofi IA') }}</p>
                <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <span class="text-lg font-black text-zinc-800">{{ __('Ganchos + Retenção') }}</span>
                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-purple-100 text-purple-800 border border-purple-200/60">{{ __('100% Otimizado') }}</span>
            </div>
        </div>
    </div>

    {{-- Seção de Contratos & Assinaturas Ativas --}}
    @if ($activeSubscriptions->isNotEmpty())
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-zinc-900 flex items-center gap-2">
                        <span>{{ __('Contratos Recorrentes & Assinaturas Ativas') }}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                            {{ $activeSubscriptions->count() }} {{ __('ativo(s)') }}
                        </span>
                    </h2>
                    <p class="text-xs text-zinc-500">{{ __('Seus planos recorrentes com renovação programada e cota garantida de vídeos.') }}</p>
                </div>
                <a href="{{ route('portal.packs.index') }}" class="text-xs font-bold text-orange-600 hover:text-orange-500 transition">
                    {{ __('Ver Todos os Planos') }} &rarr;
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                @foreach ($activeSubscriptions as $sub)
                    <div class="bg-gradient-to-br from-zinc-900 via-zinc-900 to-zinc-950 rounded-2xl p-6 text-white border border-zinc-800 shadow-xl relative overflow-hidden flex flex-col justify-between">
                        <div class="absolute -right-8 -top-8 w-36 h-36 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

                        <div class="relative z-10 space-y-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                            🔄 {{ __('Contrato Ativo') }}
                                        </span>
                                        <span class="text-[11px] font-mono font-bold text-zinc-400">
                                            {{ __('Ciclo :curr de :total', ['curr' => $sub->subscription_cycle_current, 'total' => $sub->subscription_cycles_total]) }}
                                        </span>
                                    </div>
                                    <h3 class="text-base font-extrabold text-white">
                                        {{ __($sub->pack?->name ?? 'Contrato Audiovisual') }}
                                    </h3>
                                </div>
                                <span class="text-lg font-black text-emerald-400 font-mono">
                                    € {{ number_format($sub->amount, 2, ',', '.') }}
                                    <span class="text-[10px] text-zinc-400 font-sans font-medium">/{{ $sub->subscription_interval === 'quarter' ? __('tri') : __('mês') }}</span>
                                </span>
                            </div>

                            <!-- Barra de Cota e Utilização -->
                            <div class="p-3.5 rounded-xl bg-zinc-950/70 border border-zinc-800/80 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-zinc-400 font-medium">{{ __('Cota do Ciclo Atual:') }}</span>
                                    <span class="font-bold text-white">
                                        {{ $sub->videosRemaining() }} {{ __('disponíveis') }} 
                                        <span class="text-zinc-500 text-[11px] font-normal">({{ $sub->videos_used }}/{{ $sub->video_quantity }} {{ __('usados') }})</span>
                                    </span>
                                </div>
                                @php
                                    $subPct = $sub->video_quantity > 0 ? min(100, round(($sub->videos_used / $sub->video_quantity) * 100)) : 0;
                                    $subWidthClass = match(true) {
                                        $subPct >= 100 => 'w-full',
                                        $subPct >= 75 => 'w-3/4',
                                        $subPct >= 50 => 'w-1/2',
                                        $subPct >= 25 => 'w-1/4',
                                        $subPct > 0 => 'w-1/12',
                                        default => 'w-0',
                                    };
                                @endphp
                                <div class="w-full bg-zinc-800 rounded-full h-2 overflow-hidden">
                                    <div class="bg-gradient-to-r from-emerald-500 to-teal-400 h-2 rounded-full transition-all duration-500 {{ $subWidthClass }}"></div>
                                </div>
                            </div>

                            <!-- Metadados de Renovação e Vigência -->
                            <div class="grid grid-cols-2 gap-3 text-[11px]">
                                <div class="p-2.5 rounded-lg bg-zinc-950/40 border border-zinc-800/50">
                                    <span class="text-zinc-500 block text-[10px] uppercase font-bold">{{ __('Próxima Renovação') }}</span>
                                    <span class="text-zinc-200 font-medium mt-0.5 block">
                                        {{ $sub->subscription_renews_at?->format('d/m/Y') ?? __('Automática') }}
                                    </span>
                                </div>
                                <div class="p-2.5 rounded-lg bg-zinc-950/40 border border-zinc-800/50">
                                    <span class="text-zinc-500 block text-[10px] uppercase font-bold">{{ __('Vigência do Contrato') }}</span>
                                    <span class="text-zinc-200 font-medium mt-0.5 block">
                                        {{ __('Até') }} {{ $sub->subscription_ends_at?->format('d/m/Y') ?? __('3 meses') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3.5 border-t border-zinc-800/80 flex items-center justify-between text-xs relative z-10">
                            <span class="text-[11px] text-zinc-400 flex items-center gap-1.5">
                                <span>💳</span> {{ ucfirst($sub->payment_gateway) }} · {{ __('Fatura oficial AEAT') }}
                            </span>
                            <div class="flex items-center gap-2">
                                @if ($sub->isPaid())
                                    <a href="{{ route('portal.purchases.invoice', $sub) }}" target="_blank"
                                       class="text-[11px] font-bold text-emerald-400 hover:text-emerald-300 transition">
                                        {{ __('Ver Factura') }} 📄
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Seção de Propostas & Orçamentos Recentes --}}
    @if (isset($recentQuotes) && $recentQuotes->isNotEmpty())
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-zinc-900">{{ __('Propostas & Orçamentos Recebidos') }}</h2>
                    <p class="text-xs text-zinc-500">
                        {{ __('Orçamentos personalizados enviados pela produtora com aprovação e início de gravação com 1 clique.') }}
                    </p>
                </div>
                <a href="{{ route('portal.quotes.index') }}" class="text-xs font-bold text-orange-600 hover:text-orange-700 transition flex items-center gap-1">
                    <span>{{ __('Ver todos') }}</span>
                    <span>&rarr;</span>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($recentQuotes as $q)
                    @php
                        $isExp = $q->isExpired();
                        $isAcc = $q->isAccepted();
                        $isRej = $q->isRejected();
                        $isPend = $q->isSent() && ! $isExp;
                    @endphp
                    <div class="bg-white rounded-2xl p-5 border border-zinc-200 shadow-xs hover:border-orange-200 transition space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="font-mono text-[11px] font-bold text-zinc-500 block">#{{ $q->number }}</span>
                                <h3 class="text-sm font-bold text-zinc-900 mt-0.5 line-clamp-1">
                                    {{ $q->project_title ?: __('Produção Audiovisual') }}
                                </h3>
                            </div>
                            <div>
                                @if ($isAcc)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        {{ __('Aprovado') }}
                                    </span>
                                @elseif ($isRej)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-zinc-100 text-zinc-600 border border-zinc-200">
                                        {{ __('Declinado') }}
                                    </span>
                                @elseif ($isExp)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        {{ __('Expirado') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-50 text-orange-700 border border-orange-200">
                                        {{ __('Aguardando Aprovação') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-baseline justify-between pt-2 border-t border-zinc-100 text-xs">
                            <span class="text-zinc-500">{{ __('Valor Total:') }}</span>
                            <span class="font-mono font-extrabold text-zinc-900 text-sm">
                                € {{ number_format($q->total_amount, 2, ',', '.') }}
                            </span>
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            @if ($isPend && !empty($q->approval_token))
                                <a href="{{ route('quotes.public.show', $q->approval_token) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-orange-600 hover:bg-orange-500 text-white shadow-xs transition">
                                    <span>{{ __('Revisar & Aprovar') }}</span>
                                    <span>&rarr;</span>
                                </a>
                            @elseif ($isAcc && $q->production_id)
                                <a href="{{ route('portal.productions.show', $q->production_id) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs transition">
                                    <span>{{ __('Ver Produção') }}</span>
                                    <span>&rarr;</span>
                                </a>
                            @elseif (!empty($q->approval_token))
                                <a href="{{ route('quotes.public.show', $q->approval_token) }}"
                                   class="text-xs font-semibold text-zinc-600 hover:text-zinc-900 transition">
                                    {{ __('Ver Detalhes') }} &rarr;
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Projetos em Produção --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-zinc-900">{{ __('Projetos em Produção') }}</h2>
                <p class="text-xs text-zinc-500">
                    @if ($hasCompletedProfile)
                        {{ __('Clique para revisar roteiros, feedbacks e materiais finais.') }}
                    @else
                        <span class="text-orange-600 font-semibold">{{ __('Preencha o perfil da sua marca para desbloquear o acesso aos projetos.') }}</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse ($productions as $production)
                <div class="bg-white rounded-2xl p-6 border {{ $hasCompletedProfile ? 'border-zinc-200/80 hover:border-orange-300 hover:shadow-md' : 'border-amber-200/80' }} shadow-xs transition-all group">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1 min-w-0">
                            <span class="text-[11px] font-mono font-bold uppercase tracking-wider text-zinc-400">
                                REF: {{ strtoupper(substr($production->uuid ?? '00000000', 0, 8)) }}
                            </span>
                            <h3 class="text-base font-bold text-zinc-900 {{ $hasCompletedProfile ? 'group-hover:text-orange-600' : '' }} transition truncate">
                                @if ($hasCompletedProfile)
                                    <a href="{{ route('portal.productions.show', $production) }}">{{ $production->title }}</a>
                                @else
                                    {{ $production->title }}
                                @endif
                            </h3>
                        </div>
                        <span class="inline-flex items-center shrink-0 px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider {{ $statusClass[$production->status] ?? 'bg-zinc-100 text-zinc-600' }}">
                            {{ $statusLabel[$production->status] ?? $production->status }}
                        </span>
                    </div>

                    @if ($production->description)
                        <p class="mt-3 text-xs sm:text-sm text-zinc-500 line-clamp-2 leading-relaxed">
                            {{ $production->description }}
                        </p>
                    @endif

                    <div class="mt-5 pt-4 border-t border-zinc-100 flex items-center justify-between">
                        @if (! $hasCompletedProfile)
                            <span class="text-xs font-semibold text-amber-700 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <span>{{ __('Perfil da marca incompleto') }}</span>
                            </span>
                            <a href="{{ route('portal.profile.edit') }}"
                               class="inline-flex items-center gap-1 text-xs font-bold text-orange-600 hover:text-orange-700 bg-orange-50 hover:bg-orange-100 px-3 py-1.5 rounded-lg transition">
                                <span>{{ __('Desbloquear') }}</span>
                                <span>&rarr;</span>
                            </a>
                        @else
                            <span class="text-xs font-medium text-zinc-500">{{ __('Acompanhar status e entregas') }}</span>
                            <a href="{{ route('portal.productions.show', $production) }}"
                               class="inline-flex items-center gap-1.5 text-xs font-bold text-orange-600 hover:text-orange-700">
                                <span>{{ __('Abrir Projeto') }}</span>
                                <span>&rarr;</span>
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white rounded-2xl p-10 text-center border-2 border-dashed border-zinc-200/90 shadow-xs">
                    <div class="mx-auto w-14 h-14 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center text-2xl mb-4 border border-orange-100 shadow-xs">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-zinc-900">{{ __('Nenhum projeto em esteira no momento') }}</h3>
                    <p class="mt-1.5 text-xs sm:text-sm text-zinc-500 max-w-md mx-auto leading-relaxed">
                        {{ __('Adquira um pacote de vídeos ou entre em contato com sua produtora para iniciar sua próxima campanha de alta conversão.') }}
                    </p>
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('portal.packs.index') }}"
                           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-orange-600 text-white hover:bg-orange-500 shadow-md shadow-orange-900/20 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>{{ __('Explorar Pacotes Disponíveis') }}</span>
                        </a>
                        <a href="{{ route('portal.marketing-plan.index') }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-700 bg-zinc-100 hover:bg-zinc-200 transition">
                            <span>📅</span>
                            <span>{{ __('Ver Grade Editorial Sofi') }}</span>
                        </a>
                    </div>
                </div>
            @endforelse
        </div>

        <div>{{ $productions->links() }}</div>
    </div>

    {{-- Histórico de Pacotes Avulsos & Faturas Oficiais --}}
    <div class="space-y-4 pb-4">
        <div>
            <h2 class="text-lg font-bold text-zinc-900">{{ __('Pacotes Avulsos & Histórico de Faturas') }}</h2>
            <p class="text-xs text-zinc-500">{{ __('Histórico de produções fechadas, saldo de cotas e download de faturas oficiais da AEAT.') }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-zinc-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50/80 text-zinc-500 uppercase text-[11px] font-bold tracking-wider border-b border-zinc-200">
                        <tr>
                            <th class="px-5 py-3.5">{{ __('Pacote Estratégico') }}</th>
                            <th class="px-5 py-3.5">{{ __('Cota de Vídeos') }}</th>
                            <th class="px-5 py-3.5">{{ __('Status') }}</th>
                            <th class="px-5 py-3.5 text-center">{{ __('Factura') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ __('Data de Ativação') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 font-medium text-zinc-700">
                        @forelse ($purchases as $purchase)
                            <tr class="hover:bg-zinc-50/60 transition">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-zinc-900 block">{{ __($purchase->pack?->name ?? 'Pacote Personalizado') }}</span>
                                        @if ($purchase->isSubscription())
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                🔄 {{ __('Contrato') }} ({{ __('Ciclo :curr/:total', ['curr' => $purchase->subscription_cycle_current, 'total' => $purchase->subscription_cycles_total]) }})
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-zinc-100 text-zinc-600 border border-zinc-200">
                                                📦 {{ __('Avulso') }}
                                            </span>
                                        @endif
                                    </div>
                                    @if(!empty($purchase->addons_purchased))
                                        <div class="flex flex-wrap gap-1 mt-1.5">
                                            @foreach($purchase->addons_purchased as $addon)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-orange-50 text-orange-700 border border-orange-200">
                                                    + {{ $addon }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-24 bg-zinc-200 rounded-full h-2 overflow-hidden">
                                            @php
                                                $pct = $purchase->video_quantity > 0
                                                    ? min(100, round(($purchase->videos_used / $purchase->video_quantity) * 100))
                                                    : 0;
                                                $wClass = match(true) {
                                                    $pct >= 100 => 'w-full',
                                                    $pct >= 90 => 'w-11/12',
                                                    $pct >= 80 => 'w-4/5',
                                                    $pct >= 75 => 'w-3/4',
                                                    $pct >= 60 => 'w-3/5',
                                                    $pct >= 50 => 'w-1/2',
                                                    $pct >= 40 => 'w-2/5',
                                                    $pct >= 33 => 'w-1/3',
                                                    $pct >= 25 => 'w-1/4',
                                                    $pct >= 20 => 'w-1/5',
                                                    $pct > 0 => 'w-1/12',
                                                    default => 'w-0',
                                                };
                                            @endphp
                                            <div class="bg-orange-500 h-2 rounded-full {{ $wClass }}"></div>
                                        </div>
                                        <span class="text-xs font-semibold text-zinc-700">
                                            {{ $purchase->videos_used }}/{{ $purchase->video_quantity }} {{ __('vídeos') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    @php
                                        $psc = match($purchase->status) {
                                            'paid'    => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            default   => 'bg-zinc-50 text-zinc-600 border-zinc-200',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold border {{ $psc }}">
                                        {{ ucfirst($purchase->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    @if ($purchase->isPaid())
                                        <a href="{{ route('portal.purchases.invoice', $purchase) }}"
                                           target="_blank"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-zinc-100 hover:bg-orange-50 text-zinc-700 hover:text-orange-700 border border-zinc-200 hover:border-orange-300 transition"
                                           title="{{ __('Descargar Factura Oficial AEAT') }}">
                                            <span>📄</span>
                                            <span>PDF</span>
                                            @if($purchase->invoice_number)
                                                <span class="text-[10px] text-zinc-400 font-mono">#{{ $purchase->invoice_number }}</span>
                                            @endif
                                        </a>
                                    @else
                                        <span class="text-xs text-zinc-400 italic">{{ __('Pendente') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right text-xs text-zinc-500">
                                    {{ $purchase->paid_at?->format('d/m/Y') ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-zinc-500 text-xs">
                                    {{ __('Nenhum pacote contratado no histórico.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>{{ $purchases->links() }}</div>
    </div>

</div>
@endsection
