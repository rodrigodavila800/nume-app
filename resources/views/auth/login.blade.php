<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NUME | {{ __('Área da Produtora') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/app-shell.css') }}">
    @include('partials.favicons')
</head>
<body class="h-screen overflow-hidden bg-zinc-950 text-white antialiased selection:bg-orange-500 selection:text-white">

    <!-- Language Switcher — Fixed top-right -->
    <div class="fixed top-4 right-4 z-50 flex items-center gap-2 bg-zinc-900/80 backdrop-blur border border-zinc-800 px-3 py-1.5 rounded-full">
        <a href="{{ route('lang.switch', 'pt_BR') }}" class="text-lg px-1 rounded transition {{ session('locale', 'pt_BR') === 'pt_BR' ? 'grayscale-0' : 'grayscale opacity-40 hover:opacity-70 hover:grayscale-0' }}" title="Português"></a>
        <a href="{{ route('lang.switch', 'es') }}" class="text-lg px-1 rounded transition {{ session('locale') === 'es' ? 'grayscale-0' : 'grayscale opacity-40 hover:opacity-70 hover:grayscale-0' }}" title="Español"></a>
    </div>

    <div class="h-screen flex">
        <!-- Left: Branding Panel -->
        <div class="hidden lg:flex lg:w-[44%] relative flex-col justify-between p-10 bg-zinc-900 border-r border-zinc-800 overflow-hidden">
            <div class="absolute top-0 right-0 w-96 h-96 bg-orange-600/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-orange-600/5 rounded-full blur-2xl translate-y-1/3 -translate-x-1/4 pointer-events-none"></div>

            <div class="relative z-10">
                <a href="/" class="inline-flex items-center transition hover:opacity-80">
                    <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-8 w-auto object-contain">
                </a>
            </div>

            <div class="relative z-10 space-y-6">
                <div>
                    <p class="text-xs font-black uppercase tracking-[.22em] text-orange-500 mb-3">{{ __('Plataforma da Produtora') }}</p>
                    <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-white">
                        {{ __('Gerencie produções.') }}<br>{{ __('Escale com inteligência.') }}
                    </h1>
                    <p class="mt-3 text-zinc-400 text-sm leading-relaxed max-w-xs">
                        {{ __('Roteiros, aprovações de clientes, entregas e faturamento — tudo em um painel estratégico.') }}
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-4 pt-4 border-t border-zinc-800">
                    <div>
                        <p class="text-2xl font-extrabold text-white">+47%</p>
                        <p class="text-xs text-zinc-500 mt-1">{{ __('Retenção média de audiência') }}</p>
                    </div>
                    <div>
                        <p class="text-2xl font-extrabold text-white">3x</p>
                        <p class="text-xs text-zinc-500 mt-1">{{ __('Mais aprovações em 1ª revisão') }}</p>
                    </div>
                    <div>
                        <p class="text-2xl font-extrabold text-white">24h</p>
                        <p class="text-xs text-zinc-500 mt-1">{{ __('Tempo médio de feedback') }}</p>
                    </div>
                </div>
            </div>

            <div class="relative z-10 text-xs text-zinc-600">
                © {{ date('Y') }} NUME Creative. Zaragoza · Brasil.
            </div>
        </div>

        <!-- Right: Login Form -->
        <div class="flex-1 flex flex-col items-center justify-center p-6 sm:p-10">
            <div class="w-full max-w-[360px] space-y-5">

                <!-- Mobile logo -->
                <div class="lg:hidden text-center mb-2">
                    <a href="/"><img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-8 w-auto object-contain mx-auto"></a>
                </div>

                <div>
                    <p class="text-xs font-black uppercase tracking-[.2em] text-orange-500 mb-1">{{ __('Acesso da Produtora') }}</p>
                    <h2 class="text-xl font-extrabold text-white">{{ __('Entrar no painel') }}</h2>
                    <p class="mt-1 text-xs text-zinc-500">{{ __('Insira as credenciais da sua conta NUME.') }}</p>
                </div>

                @session('status')
                    <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-3 py-2 rounded-xl text-xs font-medium">
                        {{ $value }}
                    </div>
                @endsession

                @session('warning')
                    <div class="bg-amber-500/10 border border-amber-500/20 text-amber-400 px-3 py-2 rounded-xl text-xs font-medium">
                        {{ $value }}
                    </div>
                @endsession

                @session('error')
                    <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 px-3 py-2 rounded-xl text-xs font-medium">
                        {{ $value }}
                    </div>
                @endsession

                <!-- Google Login Button -->
                <a href="{{ route('auth.google') }}"
                   class="w-full flex items-center justify-center gap-3 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 text-white font-semibold text-sm rounded-xl py-2.5 transition">
                    <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    <span>{{ __('Entrar com Google') }}</span>
                </a>

                <div class="relative flex items-center gap-3">
                    <div class="flex-1 h-px bg-zinc-800"></div>
                    <span class="text-[11px] text-zinc-600 font-semibold">{{ __('ou com e-mail') }}</span>
                    <div class="flex-1 h-px bg-zinc-800"></div>
                </div>

                <form method="POST" action="{{ route('login') }}" class="space-y-3">
                    @csrf

                    <div class="space-y-1">
                        <label for="tenant_slug" class="text-[11px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Espaço de Trabalho') }}</label>
                        <input id="tenant_slug" type="text" name="tenant_slug" value="{{ old('tenant_slug') }}"
                            required autofocus autocomplete="organization"
                            placeholder="slug-da-sua-produtora"
                            class="input-field">
                        @error('tenant_slug')
                            <p class="text-[11px] text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1">
                        <label for="email" class="text-[11px] font-bold uppercase tracking-wider text-zinc-400">{{ __('E-mail') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                            required autocomplete="username"
                            placeholder="seu@email.com"
                            class="input-field">
                        @error('email')
                            <p class="text-[11px] text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label for="password" class="text-[11px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Senha') }}</label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-[11px] text-orange-500 hover:text-orange-400 transition">{{ __('Esqueceu?') }}</a>
                            @endif
                        </div>
                        <input id="password" type="password" name="password"
                            required autocomplete="current-password"
                            placeholder="••••••••"
                            class="input-field">
                        @error('password')
                            <p class="text-[11px] text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between pt-0.5">
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-zinc-500">
                            <input type="checkbox" name="remember" class="w-3 h-3 rounded border-zinc-700 bg-zinc-800 text-orange-600 focus:ring-orange-500 focus:ring-offset-zinc-900">
                            <span>{{ __('Lembrar-me') }}</span>
                        </label>
                    </div>

                    <button type="submit"
                        class="w-full rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-[0.98] py-2.5 font-bold tracking-wide text-sm text-white shadow-lg shadow-orange-900/20 transition-all">
                        {{ __('Entrar na plataforma') }}
                    </button>
                </form>

                @if (Route::has('register'))
                    <p class="text-center text-xs text-zinc-500">
                        {{ __('É uma nova produtora?') }}
                        <a href="{{ route('register') }}" class="text-orange-500 hover:text-orange-400 font-semibold ml-1 transition">{{ __('Cadastro em breve') }} →</a>
                    </p>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
