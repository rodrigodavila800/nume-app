<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-zinc-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 | {{ __('Sessão Expirada - NUME') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full flex flex-col justify-center items-center bg-zinc-950 text-zinc-100 p-6 selection:bg-orange-500 selection:text-white">

    <div class="w-full max-w-md text-center space-y-6">
        
        <!-- Logo -->
        <div class="flex justify-center">
            <a href="/" class="inline-block transition hover:opacity-80">
                <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-9 w-auto object-contain">
            </a>
        </div>

        <!-- Card de Erro -->
        <div class="bg-zinc-900/90 border border-zinc-800 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            
            <!-- Glow Decorativo -->
            <div class="absolute -top-16 -right-16 w-32 h-32 bg-orange-500/10 rounded-full blur-2xl pointer-events-none"></div>

            <div class="relative z-10 space-y-5">
                
                <!-- Ícone de Segurança / Tempo -->
                <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mx-auto text-2xl shadow-inner">
                    🛡️
                </div>

                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-zinc-800 border border-zinc-700/60 text-[11px] font-extrabold uppercase tracking-widest text-amber-400 mb-2">
                        <span>419</span>
                        <span class="text-zinc-500">•</span>
                        <span>{{ __('Página Expirada') }}</span>
                    </div>
                    <h1 class="text-2xl font-black text-white tracking-tight">
                        {{ __('Sessão Expirada') }}
                    </h1>
                </div>

                <p class="text-sm text-zinc-400 leading-relaxed">
                    {{ __('Por medidas de segurança e integridade de dados (proteção CSRF), a sua sessão de navegação expirou após um período de inatividade.') }}
                </p>

                <!-- Ações -->
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <button type="button" onclick="window.location.reload();" 
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs uppercase tracking-wider shadow-lg shadow-orange-600/20 transition cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>{{ __('Recarregar Página') }}</span>
                    </button>

                    <a href="{{ url('/') }}" 
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 text-zinc-300 hover:text-white font-bold text-xs uppercase tracking-wider transition">
                        <span>{{ __('Ir para o Início') }}</span>
                    </a>
                </div>

            </div>

        </div>

        <!-- Footer -->
        <p class="text-xs text-zinc-600">
            © {{ date('Y') }} NUME Creative. {{ __('Plataforma Audiovisual Segura.') }}
        </p>

    </div>

</body>
</html>
