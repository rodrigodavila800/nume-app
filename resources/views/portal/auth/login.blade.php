<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NUME | {{ __('Portal do Cliente') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .input-field {
            width: 100%;
            background: #fff;
            border: 1.5px solid #e4e4e7;
            border-radius: 0.75rem;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            color: #18181b;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-field::placeholder { color: #a1a1aa; }
        .input-field:focus { border-color: #f97316; box-shadow: 0 0 0 3px rgba(249,115,22,0.12); }
    </style>
    @include('partials.favicons')
</head>
<body class="h-screen overflow-hidden antialiased selection:bg-orange-500 selection:text-white">

    <!-- Language Switcher — Fixed top-right -->
    <div class="fixed top-4 right-4 z-50 flex items-center gap-1.5 bg-white/90 backdrop-blur border border-zinc-200 shadow-sm p-1 rounded-full">
        <a href="{{ route('lang.switch', 'pt_BR') }}" class="text-[11px] font-bold px-2.5 py-1 rounded-full transition {{ session('locale', 'pt_BR') === 'pt_BR' ? 'bg-orange-600 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-900' }}" title="Português">BR</a>
        <a href="{{ route('lang.switch', 'es') }}" class="text-[11px] font-bold px-2.5 py-1 rounded-full transition {{ session('locale') === 'es' ? 'bg-orange-600 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-900' }}" title="Español">ES</a>
    </div>

    <div class="h-screen flex">
        <!-- Left branding panel -->
        <div class="hidden lg:flex lg:w-[46%] bg-zinc-950 flex-col justify-between p-10 relative overflow-hidden flex-shrink-0">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(249,115,22,0.12),transparent_50%),radial-gradient(circle_at_10%_80%,rgba(249,115,22,0.06),transparent_50%)] pointer-events-none"></div>

            <div class="relative z-10">
                <a href="/" class="inline-flex items-center transition hover:opacity-80">
                    <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-8 w-auto object-contain brightness-0 invert">
                </a>
            </div>

            <div class="relative z-10 space-y-5">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-widest bg-orange-500/20 text-orange-400 border border-orange-500/20 mb-4">
                        {{ __('Portal Exclusivo do Cliente') }}
                    </span>
                    <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-white">
                        {{ __('Acompanhe cada etapa da sua produção.') }}
                    </h1>
                    <p class="mt-3 text-zinc-400 text-sm leading-relaxed max-w-xs">
                        {{ __('Roteiros para aprovação, status em tempo real e todo o histórico dos seus vídeos em um só lugar.') }}
                    </p>
                </div>
                <ul class="space-y-2.5 text-xs text-zinc-400">
                    <li class="flex items-center gap-3">
                        <span class="w-5 h-5 rounded-full bg-orange-600/20 flex items-center justify-center text-orange-500 text-[10px] flex-shrink-0">✓</span>
                        {{ __('Aprovação de roteiros com 1 clique') }}
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="w-5 h-5 rounded-full bg-orange-600/20 flex items-center justify-center text-orange-500 text-[10px] flex-shrink-0">✓</span>
                        {{ __('Acompanhamento de produção em tempo real') }}
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="w-5 h-5 rounded-full bg-orange-600/20 flex items-center justify-center text-orange-500 text-[10px] flex-shrink-0">✓</span>
                        {{ __('Acesso seguro sem compartilhar dados internos') }}
                    </li>
                </ul>
            </div>

            <div class="relative z-10 text-xs text-zinc-700">
                © {{ date('Y') }} NUME Creative. {{ __('Todos os direitos reservados.') }}
            </div>
        </div>

        <!-- Right: Login Form -->
        <div class="flex-1 bg-zinc-50 flex flex-col items-center justify-center p-6 sm:p-10 overflow-y-auto">
            <div class="w-full max-w-[360px] space-y-5">

                <!-- Mobile logo -->
                <div class="lg:hidden text-center">
                    <a href="/"><img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-7 w-auto object-contain mx-auto"></a>
                </div>

                <div>
                    <p class="text-xs font-black uppercase tracking-[.2em] text-orange-600 mb-1">{{ __('Acesso ao Portal') }}</p>
                    <h2 class="text-xl font-extrabold text-zinc-900">{{ __('Bem-vindo de volta') }}</h2>
                    <p class="mt-1 text-xs text-zinc-500">{{ __('Entre com a sua conta para acessar suas produções.') }}</p>
                </div>

                @if(isset($intendedPack) && $intendedPack)
                    <div class="p-3.5 rounded-xl bg-orange-50 border border-orange-200 text-xs text-orange-900 space-y-1">
                        <p class="font-bold text-orange-700 uppercase tracking-wider text-[10px]">{{ __('Contratação de Pacote') }}</p>
                        <p class="font-bold text-sm text-zinc-900">{{ $intendedPack->name }}</p>
                        <p class="text-[11px] text-zinc-600">{{ __('Entre com sua conta de cliente para prosseguir com a contratação.') }}</p>
                    </div>
                @endif

                @session('status')
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2 rounded-xl text-xs font-medium">
                        {{ $value }}
                    </div>
                @endsession

                @session('warning')
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2 rounded-xl text-xs font-medium">
                        {{ $value }}
                    </div>
                @endsession

                @if($errors->any())
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 px-3 py-2 rounded-xl text-xs font-medium">
                        {{ $errors->first() }}
                    </div>
                @endif

                <!-- Google Login Button -->
                <a href="{{ route('portal.auth.google') }}"
                   class="w-full flex items-center justify-center gap-3 bg-white hover:bg-zinc-50 border border-zinc-200 hover:border-zinc-300 text-zinc-800 font-semibold text-sm rounded-xl py-2.5 shadow-sm transition">
                    <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    <span>{{ __('Entrar com Google') }}</span>
                </a>

                <div class="relative flex items-center gap-3">
                    <div class="flex-1 h-px bg-zinc-200"></div>
                    <span class="text-[11px] text-zinc-400 font-semibold">{{ __('ou com e-mail e senha') }}</span>
                    <div class="flex-1 h-px bg-zinc-200"></div>
                </div>

                <form method="POST" action="{{ route('portal.login') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="tenant_slug" value="{{ old('tenant_slug', 'nume') }}">

                    <div class="space-y-1">
                        <label for="email" class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('E-mail') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                            required autocomplete="username"
                            placeholder="seu@email.com"
                            class="input-field">
                        @error('email')
                            <p class="text-[11px] text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1">
                        <label for="password" class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Senha') }}</label>
                        <input id="password" type="password" name="password"
                            required autocomplete="current-password"
                            placeholder="••••••••"
                            class="input-field">
                        @error('password')
                            <p class="text-[11px] text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between pt-0.5">
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-zinc-500">
                            <input type="checkbox" name="remember" class="w-3 h-3 rounded border-zinc-300 text-orange-600 focus:ring-orange-500">
                            <span>{{ __('Lembrar acesso') }}</span>
                        </label>
                    </div>

                    <button type="submit"
                        class="w-full rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-[0.98] py-2.5 font-bold tracking-wide text-sm text-white shadow-lg shadow-orange-900/10 transition-all">
                        {{ __('Acessar meu Portal') }}
                    </button>
                </form>

                <div class="pt-2 text-center border-t border-zinc-200 space-y-2">
                    <p class="text-xs text-zinc-600">
                        {{ __('Ainda não possui conta de cliente?') }}
                        <a href="{{ route('portal.register', request()->has('pack') ? ['pack' => request('pack')] : []) }}" class="text-orange-600 hover:text-orange-500 font-bold ml-1 transition">
                            {{ __('Criar Conta de Cliente') }} →
                        </a>
                    </p>
                    <p class="text-[11px] text-zinc-400">
                        {{ __('Acesso restrito à equipe interna?') }}
                        <a href="{{ route('login') }}" class="text-zinc-500 hover:text-zinc-700 underline ml-1">{{ __('Painel da Produtora') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
