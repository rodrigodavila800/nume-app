<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NUME | {{ __('Cadastro de Cliente') }}</title>
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
<body class="min-h-screen flex flex-col antialiased selection:bg-orange-500 selection:text-white bg-zinc-50">

    <!-- Language Switcher — Fixed top-right -->
    <div class="fixed top-4 right-4 z-50 flex items-center gap-2 bg-white/80 backdrop-blur border border-zinc-200 shadow-sm px-3 py-1.5 rounded-full">
        <a href="{{ route('lang.switch', 'pt_BR') }}" class="text-lg px-1 rounded transition {{ session('locale', 'pt_BR') === 'pt_BR' ? 'grayscale-0' : 'grayscale opacity-40 hover:opacity-70 hover:grayscale-0' }}" title="Português"></a>
        <a href="{{ route('lang.switch', 'es') }}" class="text-lg px-1 rounded transition {{ session('locale') === 'es' ? 'grayscale-0' : 'grayscale opacity-40 hover:opacity-70 hover:grayscale-0' }}" title="Español"></a>
    </div>

    <div class="min-h-screen flex">
        <!-- Left branding panel -->
        <div class="hidden lg:flex lg:w-[46%] bg-zinc-950 flex-col justify-between p-10 relative overflow-hidden flex-shrink-0">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(249,115,22,0.12),transparent_50%),radial-gradient(circle_at_10%_80%,rgba(249,115,22,0.06),transparent_50%)] pointer-events-none"></div>

            <div class="relative z-10">
                <a href="/" class="inline-flex items-center transition hover:opacity-80">
                    <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-8 w-auto object-contain">
                </a>
            </div>

            <div class="relative z-10 space-y-5">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-widest bg-orange-500/20 text-orange-400 border border-orange-500/20 mb-4">
                        {{ __('Portal do Cliente & Contratante') }}
                    </span>
                    <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-white">
                        {{ __('Sua empresa com vídeos de alta performance.') }}
                    </h1>
                    <p class="mt-3 text-zinc-400 text-sm leading-relaxed max-w-xs">
                        {{ __('Cadastre-se para aprovar roteiros, contratar novos pacotes e acompanhar as entregas dos seus vídeos.') }}
                    </p>
                </div>
                <ul class="space-y-2.5 text-xs text-zinc-400">
                    <li class="flex items-center gap-3">
                        <span class="w-5 h-5 rounded-full bg-orange-600/20 flex items-center justify-center text-orange-500 text-[10px] flex-shrink-0">✓</span>
                        {{ __('Aprovação de roteiros com 1 clique') }}
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="w-5 h-5 rounded-full bg-orange-600/20 flex items-center justify-center text-orange-500 text-[10px] flex-shrink-0">✓</span>
                        {{ __('Histórico completo de vídeos gravados') }}
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="w-5 h-5 rounded-full bg-orange-600/20 flex items-center justify-center text-orange-500 text-[10px] flex-shrink-0">✓</span>
                        {{ __('Download de arquivos em alta resolução') }}
                    </li>
                </ul>
            </div>

            <div class="relative z-10 text-xs text-zinc-700">
                © {{ date('Y') }} NUME Creative. {{ __('Todos os direitos reservados.') }}
            </div>
        </div>

        <!-- Right: Register Form -->
        <div class="flex-1 bg-zinc-50 flex flex-col items-center justify-center p-6 sm:p-10 overflow-y-auto">
            <div class="w-full max-w-[400px] space-y-5">

                <!-- Mobile logo -->
                <div class="lg:hidden text-center">
                    <a href="/"><img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-7 w-auto object-contain mx-auto"></a>
                </div>

                <div>
                    <p class="text-xs font-black uppercase tracking-[.2em] text-orange-600 mb-1">{{ __('Novo Cliente') }}</p>
                    <h2 class="text-xl font-extrabold text-zinc-900">{{ __('Criar Conta de Cliente') }}</h2>
                    <p class="mt-1 text-xs text-zinc-500">{{ __('Cadastre sua empresa para gerenciar produções e contratar pacotes.') }}</p>
                </div>

                @if(isset($intendedPack) && $intendedPack)
                    <div class="p-3.5 rounded-xl bg-orange-50 border border-orange-200 text-xs text-orange-900 space-y-1">
                        <p class="font-bold text-orange-700 uppercase tracking-wider text-[10px]">{{ __('Contratação em Andamento') }}</p>
                        <p class="font-bold text-sm text-zinc-900">{{ $intendedPack->name }}</p>
                        <p class="text-[11px] text-zinc-600">{{ __('Crie sua conta para finalizar o pedido no Stripe.') }}</p>
                    </div>
                @endif

                @if($errors->any())
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 px-3.5 py-2.5 rounded-xl text-xs font-medium space-y-1">
                        @foreach ($errors->all() as $err)
                            <p class="flex items-start gap-1.5">
                                <span class="text-rose-500 mt-0.5">•</span>
                                <span>{{ $err }}</span>
                            </p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('portal.register') }}" class="space-y-3.5">
                    @csrf

                    <div class="space-y-1">
                        <label for="name" class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Nome Completo') }} <span class="text-orange-500">*</span></label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}"
                            required autofocus autocomplete="name"
                            placeholder="Ex: Carlos Albuquerque"
                            class="input-field">
                        @error('name')
                            <p class="text-[11px] text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1">
                        <label for="email" class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('E-mail Corporativo ou Pessoal') }} <span class="text-orange-500">*</span></label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                            required autocomplete="email"
                            placeholder="carlos@empresa.com"
                            class="input-field">
                        @error('email')
                            <p class="text-[11px] text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1">
                        <label for="company_name" class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Empresa / Marca') }} <span class="text-zinc-400 text-[10px]">({{ __('opcional') }})</span></label>
                        <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}"
                            autocomplete="organization"
                            placeholder="Ex: Acme Logistics"
                            class="input-field">
                    </div>

                    <div class="space-y-1">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="password" class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Senha') }} <span class="text-orange-500">*</span></label>
                                <input id="password" type="password" name="password"
                                    required autocomplete="new-password"
                                    placeholder="••••••••"
                                    class="input-field">
                                @error('password')
                                    <p class="text-[11px] text-rose-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-1">
                                <label for="password_confirmation" class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Confirmar Senha') }} <span class="text-orange-500">*</span></label>
                                <input id="password_confirmation" type="password" name="password_confirmation"
                                    required autocomplete="new-password"
                                    placeholder="••••••••"
                                    class="input-field">
                                @error('password_confirmation')
                                    <p class="text-[11px] text-rose-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <p class="text-[10px] text-zinc-500 pt-0.5 leading-normal">
                            {{ __('Mínimo de 8 caracteres contendo maiúsculas, minúsculas, números e símbolos (ex: @, #, $, !).') }}
                        </p>
                    </div>

                    <div class="pt-1">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" name="terms" value="1" required
                                   class="mt-0.5 rounded border-zinc-300 text-orange-600 focus:ring-orange-500 h-4 w-4">
                            <span class="text-xs text-zinc-600 leading-tight">
                                {{ __('Li e concordo com os') }}
                                <a href="{{ route('legal.privacy') }}" target="_blank" class="text-orange-600 hover:underline font-semibold">
                                    {{ __('Termos de Privacidade e Proteção de Dados (RGPD)') }}
                                </a>.
                            </span>
                        </label>
                        @error('terms')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                            class="w-full rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-[0.98] py-3 font-bold tracking-wide text-sm text-white shadow-lg shadow-orange-900/10 transition-all flex items-center justify-center gap-2">
                            <span>{{ __('Criar Minha Conta de Cliente') }}</span>
                            <span>→</span>
                        </button>
                    </div>
                </form>

                <div class="pt-2 text-center border-t border-zinc-200">
                    <p class="text-xs text-zinc-600">
                        {{ __('Já possui conta de cliente?') }}
                        <a href="{{ route('portal.login', request()->has('pack') ? ['pack' => request('pack')] : []) }}" class="text-orange-600 hover:text-orange-500 font-bold ml-1 transition">
                            {{ __('Fazer Login') }} →
                        </a>
                    </p>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
