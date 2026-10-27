<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-zinc-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Aprovação de Roteiro') }} | {{ $production->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    @include('partials.favicons')
</head>
<body class="min-h-full bg-zinc-950 text-zinc-100 font-sans antialiased selection:bg-orange-500 selection:text-white py-6 px-4 sm:px-6 lg:px-8">

    <div class="max-w-2xl mx-auto space-y-6">

        <!-- Top Header: Brand & Security -->
        <div class="flex items-center justify-between pb-4 border-b border-zinc-800">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-orange-600 flex items-center justify-center font-black text-white text-base shadow-lg shadow-orange-600/30">N</span>
                <div>
                    <p class="text-xs font-black tracking-wider uppercase text-zinc-100">NUME</p>
                    <p class="text-[10px] text-zinc-400">{{ __('Aprovação Instantânea') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span>{{ __('Link Seguro Sem Senha') }}</span>
            </div>
        </div>

        <!-- Feedback Alerts -->
        @if(session('success'))
            <div class="rounded-2xl bg-emerald-950/40 border border-emerald-500/40 p-4 text-emerald-300 text-xs font-semibold flex items-start gap-3">
                <span class="text-lg"></span>
                <div>
                    <p class="font-bold text-emerald-200">{{ __('Ação Registrada com Sucesso!') }}</p>
                    <p class="mt-0.5">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if(session('info'))
            <div class="rounded-2xl bg-purple-950/40 border border-purple-500/40 p-4 text-purple-300 text-xs font-semibold flex items-start gap-3">
                <span class="text-lg"></span>
                <div>
                    <p class="font-bold text-purple-200">{{ __('Aviso') }}</p>
                    <p class="mt-0.5">{{ session('info') }}</p>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl bg-rose-950/40 border border-rose-500/40 p-4 text-rose-300 text-xs font-semibold">
                <p class="font-bold text-rose-200 mb-1">{{ __('Por favor, verifique os campos:') }}</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Card da Produção -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-5 sm:p-6 shadow-2xl space-y-5">
            
            <!-- Metadata do Vídeo -->
            <div>
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-orange-400">
                        {{ $cliente?->company_name ?: ($cliente?->name ?? 'Cliente') }}
                    </span>
                    @if($roteiro->status === 'submitted')
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500/20 text-amber-300 border border-amber-500/30 animate-pulse">
                            {{ __('Aguardando Sua Aprovação') }}
                        </span>
                    @elseif($roteiro->status === 'approved')
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            {{ __('Roteiro Aprovado') }}
                        </span>
                    @elseif($roteiro->status === 'rejected')
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                            {{ __('Ajustes Solicitados') }}
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-zinc-800 text-zinc-300">
                            {{ ucfirst($roteiro->status) }}
                        </span>
                    @endif
                </div>

                <h1 class="text-xl sm:text-2xl font-black text-white leading-tight">
                    {{ $production->title }}
                </h1>
                @if($production->description)
                    <p class="text-xs text-zinc-400 mt-1">
                        {{ $production->description }}
                    </p>
                @endif
            </div>

            <!-- Conteúdo do Roteiro -->
            <div class="border-t border-zinc-800 pt-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-zinc-400 flex items-center gap-2">
                        <span>{{ __('Roteiro Audiovisual & Copy') }}</span>
                    </h2>
                    <span class="text-[10px] text-zinc-500 font-medium">
                        {{ __('Otimizado para Alta Retenção') }}
                    </span>
                </div>

                <div class="bg-zinc-950/70 border border-zinc-800/80 rounded-2xl p-4 sm:p-5 text-sm text-zinc-200 leading-relaxed whitespace-pre-line font-mono selection:bg-orange-500 selection:text-white">
{{ $roteiro->content }}
                </div>
            </div>

            <!-- Motivo de Rejeição Anterior (se houver) -->
            @if($roteiro->rejection_reason)
                <div class="bg-rose-950/20 border border-rose-500/20 rounded-2xl p-3 text-xs text-rose-300">
                    <p class="font-bold text-rose-400 mb-0.5">{{ __('Último pedido de ajuste:') }}</p>
                    <p>{{ $roteiro->rejection_reason }}</p>
                </div>
            @endif

            <!-- Painel de Ações do Cliente -->
            @if($roteiro->status === 'submitted')
                <div class="border-t border-zinc-800 pt-5 space-y-4">
                    <!-- Botão de Aprovação Principal -->
                    <form method="POST" action="{{ request()->fullUrlWithQuery([]) }}">
                        @csrf
                        {{-- A rota POST de approve requer a mesma assinatura da URL assinada --}}
                        <button type="submit"
                                formaction="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('magic.roteiros.approve', now()->addMinutes(60), ['roteiro' => $roteiro->uuid ?: $roteiro->id]) }}"
                                class="w-full flex items-center justify-center gap-2 py-4 px-6 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-sm shadow-xl shadow-emerald-600/30 hover:scale-[1.01] active:scale-[0.99] transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>{{ __('Aprovar Roteiro Agora (1-Clique)') }}</span>
                        </button>
                    </form>

                    <!-- Seção de Ajustes / Alterações -->
                    <details class="group bg-zinc-950/40 border border-zinc-800 rounded-2xl p-3 transition">
                        <summary class="flex items-center justify-between text-xs font-bold text-zinc-400 hover:text-zinc-200 cursor-pointer list-none">
                            <span class="flex items-center gap-1.5">
                                <span>{{ __('Precisa de ajustes ou alterações?') }}</span>
                            </span>
                            <span class="group-open:rotate-180 transition-transform">▼</span>
                        </summary>

                        <form method="POST" action="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('magic.roteiros.reject', now()->addMinutes(60), ['roteiro' => $roteiro->uuid ?: $roteiro->id]) }}" class="mt-3 pt-3 border-t border-zinc-800 space-y-3">
                            @csrf
                            <div>
                                <label for="rejection_reason" class="block text-[11px] font-semibold text-zinc-300 mb-1">
                                    {{ __('Descreva quais pontos gostaria de modificar no roteiro:') }}
                                </label>
                                <textarea id="rejection_reason" name="rejection_reason" rows="3" required
                                          placeholder="{{ __('Ex: Gostaria de trocar a chamada final para convidar para o evento do dia 15...') }}"
                                          class="w-full bg-zinc-900 border border-zinc-700/80 rounded-xl p-3 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500"></textarea>
                            </div>
                            <button type="submit"
                                    class="w-full py-2.5 px-4 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 font-bold text-xs border border-zinc-700 transition">
                                {{ __('Enviar Ajustes para a Produtora') }}
                            </button>
                        </form>
                    </details>
                </div>
            @elseif($roteiro->status === 'approved')
                <div class="border-t border-zinc-800 pt-4 text-center">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-bold">
                        <span>{{ __('Roteiro já Aprovado! Produção em andamento.') }}</span>
                    </div>
                </div>
            @endif

        </div>

        <!-- Footer -->
        <div class="text-center text-[10px] text-zinc-500 space-y-1">
            <p>{{ __('Este link é pessoal e intransferível. Criptografado com padrão HMAC SHA-256.') }}</p>
            <p>© {{ date('Y') }} NUME — {{ __('Ecossistema Audiovisual de Alta Performance') }}</p>
        </div>

    </div>

</body>
</html>
