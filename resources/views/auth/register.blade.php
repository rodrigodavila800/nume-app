<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NUME | {{ __('Cadastro de Produtoras') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="h-screen overflow-hidden bg-zinc-950 text-white antialiased selection:bg-orange-500 selection:text-white">

    <!-- Language Switcher — Fixed top-right -->
    <div class="fixed top-4 right-4 z-50 flex items-center gap-2 bg-zinc-900/80 backdrop-blur border border-zinc-800 px-3 py-1.5 rounded-full">
        <a href="{{ route('lang.switch', 'pt_BR') }}" class="text-lg px-1 rounded transition {{ session('locale', 'pt_BR') === 'pt_BR' ? 'grayscale-0' : 'grayscale opacity-40 hover:opacity-70 hover:grayscale-0' }}" title="Português">🇧🇷</a>
        <a href="{{ route('lang.switch', 'es') }}" class="text-lg px-1 rounded transition {{ session('locale') === 'es' ? 'grayscale-0' : 'grayscale opacity-40 hover:opacity-70 hover:grayscale-0' }}" title="Español">🇪🇸</a>
    </div>

    <div class="h-screen flex">
        <!-- Left: Branding Panel -->
        <div class="hidden lg:flex lg:w-[44%] relative flex-col justify-between p-10 bg-zinc-900 border-r border-zinc-800 overflow-hidden flex-shrink-0">
            <div class="absolute top-0 right-0 w-96 h-96 bg-orange-600/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-orange-600/5 rounded-full blur-2xl translate-y-1/3 -translate-x-1/4 pointer-events-none"></div>

            <div class="relative z-10">
                <a href="/" class="inline-flex items-center transition hover:opacity-80">
                    <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-8 w-auto object-contain">
                </a>
            </div>

            <div class="relative z-10 space-y-5">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-widest bg-orange-500/20 text-orange-400 border border-orange-500/20 mb-4">
                        {{ __('Programa de Produtoras') }}
                    </span>
                    <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-white">
                        {{ __('O ecossistema oficial da NUME Creative.') }}
                    </h1>
                    <p class="mt-3 text-zinc-400 text-sm leading-relaxed max-w-xs">
                        {{ __('Uma infraestrutura completa de roteiros, aprovações, vídeos estratégicos e faturamento B2B.') }}
                    </p>
                </div>
                <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-2">
                    <p class="text-xs font-bold text-white">{{ __('Operação Exclusiva') }}</p>
                    <p class="text-xs text-zinc-400 leading-relaxed">
                        {{ __('Atualmente a plataforma opera em regime fechado com a produtora fundadora NUME Creative. Novas vagas para produtoras serão abertas em fases graduais.') }}
                    </p>
                </div>
            </div>

            <div class="relative z-10 text-xs text-zinc-600">
                © {{ date('Y') }} NUME Creative. Zaragoza · Brasil.
            </div>
        </div>

        <!-- Right: "Em Breve" Notice -->
        <div class="flex-1 flex flex-col items-center justify-center p-6 sm:p-10">
            <div class="w-full max-w-[400px] text-center space-y-6">

                <!-- Mobile logo -->
                <div class="lg:hidden mb-2">
                    <a href="/"><img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-8 w-auto object-contain mx-auto"></a>
                </div>

                <!-- Icon / Badge -->
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-orange-500/10 border border-orange-500/20 text-orange-500 mx-auto">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                </div>

                <div class="space-y-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-600/20 text-orange-400 border border-orange-600/30">
                        {{ __('Em Breve') }}
                    </span>
                    <h2 class="text-2xl font-extrabold text-white">
                        {{ __('Cadastro de Produtoras') }}
                    </h2>
                    <p class="text-xs text-zinc-400 leading-relaxed max-w-sm mx-auto">
                        {{ __('No momento, novas contas de produtoras estão restritas. A única produtora ativa e oficial da plataforma é a NUME Creative.') }}
                    </p>
                </div>

                <div class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 text-left space-y-2">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                        <span class="text-xs font-bold text-zinc-200">{{ __('Produtora Ativa') }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <div>
                            <p class="text-sm font-extrabold text-white">NUME Creative</p>
                            <p class="text-xs text-zinc-500">{{ __('Espaço de trabalho:') }} <code class="text-orange-400 bg-orange-500/10 px-1.5 py-0.5 rounded font-mono">nume</code></p>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-orange-600/20 text-orange-400 border border-orange-600/30">
                            {{ __('Oficial') }}
                        </span>
                    </div>
                </div>

                <div class="pt-2 space-y-3">
                    <a href="{{ route('login') }}"
                       class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-[0.98] py-3 font-bold tracking-wide text-sm text-white shadow-lg shadow-orange-900/20 transition-all">
                        {{ __('Acessar Painel NUME (Login)') }}
                    </a>

                    <a href="/"
                       class="w-full inline-flex items-center justify-center text-xs font-semibold text-zinc-400 hover:text-white transition">
                        ← {{ __('Voltar para a Página Inicial') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
