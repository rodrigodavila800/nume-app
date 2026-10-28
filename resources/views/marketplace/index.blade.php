<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NUME | {{ __('Vídeos que movem negócios') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/app-shell.css') }}">
    @include('partials.favicons')
</head>
<body class="bg-zinc-950 text-zinc-100 antialiased selection:bg-orange-500 selection:text-white">
    <header class="border-b border-zinc-800 bg-zinc-950/95 backdrop-blur sticky top-0 z-50">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
            <a href="{{ route('marketplace.index') }}" class="flex items-center gap-3 transition hover:opacity-90">
                <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-8 w-auto object-contain">
            </a>
            <nav class="flex items-center gap-5 text-sm font-semibold">
                <a href="#pacotes" class="hidden text-zinc-400 hover:text-white sm:block transition">{{ __('Pacotes') }}</a>
                <a href="{{ route('login') }}" class="text-zinc-400 hover:text-white transition">{{ __('Entrar') }}</a>
                
                <!-- Language Switcher -->
                <div class="flex items-center gap-1.5 border-l border-zinc-800 pl-4 ml-1">
                    <a href="{{ route('lang.switch', 'pt_BR') }}" class="px-2 py-0.5 rounded-md text-xs font-bold transition {{ session('locale', 'pt_BR') === 'pt_BR' ? 'bg-orange-500/20 border border-orange-500/30 text-orange-400' : 'text-zinc-400 hover:text-white hover:bg-zinc-800' }}" title="Português">BR</a>
                    <a href="{{ route('lang.switch', 'es') }}" class="px-2 py-0.5 rounded-md text-xs font-bold transition {{ session('locale') === 'es' ? 'bg-orange-500/20 border border-orange-500/30 text-orange-400' : 'text-zinc-400 hover:text-white hover:bg-zinc-800' }}" title="Español">ES</a>
                </div>

                <a href="#pacotes" class="rounded-xl bg-orange-600 px-5 py-2.5 text-white hover:bg-orange-500 shadow-lg shadow-orange-900/20 transition ml-2">{{ __('Começar projeto') }}</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_80%_10%,rgba(249,115,22,.15),transparent_27%),radial-gradient(circle_at_15%_45%,rgba(234,88,12,.1),transparent_32%)]"></div>
            <div class="mx-auto grid max-w-7xl gap-12 px-6 py-24 lg:grid-cols-[1.2fr_.8fr] lg:py-32">
                <div>
                    <p class="mb-5 text-sm font-black uppercase tracking-[.22em] text-orange-500">{{ __('Vídeo com direção de negócio') }}</p>
                    <h1 class="max-w-3xl text-5xl font-extrabold leading-[1.05] tracking-tight sm:text-6xl lg:text-7xl">{{ __('Sua próxima venda começa com um vídeo memorável.') }}</h1>
                    <p class="mt-7 max-w-2xl text-lg leading-relaxed text-zinc-400">{{ __('A NUME transforma estratégia, roteiro e produção em conteúdo que sua audiência quer assistir — e sua marca precisa para crescer. Alta conversão e retenção B2B.') }}</p>
                    <div class="mt-10 flex flex-wrap gap-4">
                        <a href="#pacotes" class="rounded-xl bg-orange-600 px-7 py-3.5 font-bold text-white shadow-lg shadow-orange-900/30 transition hover:bg-orange-500 transform hover:-translate-y-0.5">{{ __('Ver pacotes de vídeo') }}</a>
                        <a href="#portifolio" class="rounded-xl border border-zinc-700 px-7 py-3.5 font-bold text-zinc-300 transition hover:border-zinc-500 hover:bg-zinc-800 hover:text-white">{{ __('Ver portfólio') }}</a>
                    </div>
                    <div class="mt-14 flex gap-8 border-t border-zinc-800 pt-8 text-sm text-zinc-500">
                        <p><strong class="block text-2xl text-white font-extrabold mb-1">{{ __('Estratégia') }}</strong> {{ __('antes da câmera') }}</p>
                        <p><strong class="block text-2xl text-white font-extrabold mb-1">{{ __('Roteiro') }}</strong> {{ __('que prende') }}</p>
                        <p><strong class="block text-2xl text-white font-extrabold mb-1">{{ __('Entrega') }}</strong> {{ __('com clareza') }}</p>
                    </div>
                </div>
                <div class="relative rounded-3xl border border-zinc-800 bg-zinc-900/50 p-1.5 shadow-2xl backdrop-blur-sm">
                    <div class="flex min-h-[400px] flex-col justify-between rounded-[1.25rem] bg-gradient-to-br from-zinc-900 via-zinc-950 to-orange-950/40 p-8 border border-zinc-800/50 relative overflow-hidden">
                        <div class="absolute right-0 top-0 w-64 h-64 bg-orange-600/10 rounded-full blur-3xl"></div>
                        <span class="grid h-16 w-16 place-items-center rounded-full bg-orange-500 text-2xl text-white shadow-lg shadow-orange-500/20 z-10">▶</span>
                        <div class="z-10">
                            <p class="text-xs font-bold uppercase tracking-[.2em] text-orange-400">{{ __('NUME Originals') }}</p>
                            <p class="mt-3 text-3xl font-extrabold leading-tight text-white">{{ __('Conteúdo que não passa despercebido no feed.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="portifolio" class="border-y border-zinc-800 bg-zinc-900/30 py-24">
            <div class="mx-auto max-w-7xl px-6">
                <div>
                    <p class="text-sm font-black uppercase tracking-[.2em] text-orange-500">{{ __('O jeito NUME') }}</p>
                    <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl text-white">{{ __('Vídeos feitos para ganhar atenção e gerar resultado.') }}</h2>
                </div>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <article class="rounded-2xl border border-zinc-800 bg-zinc-950/50 p-8 transition hover:border-zinc-700 hover:bg-zinc-900/80">
                        <span class="text-4xl font-extrabold text-orange-500/20">01</span>
                        <h3 class="mt-6 text-2xl font-bold text-white">{{ __('Estratégia') }}</h3>
                        <p class="mt-4 leading-relaxed text-zinc-400">{{ __('Entendemos objetivo, público e oferta antes de ligar a câmera. Alinhamento total com vendas.') }}</p>
                    </article>
                    <article class="rounded-2xl border border-zinc-800 bg-zinc-950/50 p-8 transition hover:border-zinc-700 hover:bg-zinc-900/80">
                        <span class="text-4xl font-extrabold text-orange-500/20">02</span>
                        <h3 class="mt-6 text-2xl font-bold text-white">{{ __('Narrativa') }}</h3>
                        <p class="mt-4 leading-relaxed text-zinc-400">{{ __('Criamos roteiros dinâmicos que comunicam com clareza e garantem alta taxa de retenção.') }}</p>
                    </article>
                    <article class="rounded-2xl border border-zinc-800 bg-zinc-950/50 p-8 transition hover:border-zinc-700 hover:bg-zinc-900/80">
                        <span class="text-4xl font-extrabold text-orange-500/20">03</span>
                        <h3 class="mt-6 text-2xl font-bold text-white">{{ __('Produção') }}</h3>
                        <p class="mt-4 leading-relaxed text-zinc-400">{{ __('Entregamos vídeos finalizados no padrão internacional, prontos para sua marca faturar.') }}</p>
                    </article>
                </div>
            </div>
        </section>
        
        <section id="pacotes" class="mx-auto max-w-7xl px-6 py-24">
            <div class="text-center max-w-2xl mx-auto">
                <p class="text-sm font-black uppercase tracking-[.2em] text-orange-500">{{ __('Pacotes NUME') }}</p>
                <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl text-white">{{ __('Escolha o próximo movimento da sua marca.') }}</h2>
            </div>
            <div class="mt-12 rounded-3xl border border-dashed border-zinc-700 bg-zinc-900/20 p-12 text-center max-w-3xl mx-auto">
                <p class="text-xl font-bold text-white">{{ __('Os primeiros pacotes B2B estão sendo configurados.') }}</p>
                <p class="mt-3 text-zinc-400">{{ __('Em breve você poderá contratar sua produção estratégica diretamente por aqui com checkout expresso.') }}</p>
            </div>
        </section>
    </main>

    <footer class="border-t border-zinc-800 bg-zinc-950">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm font-medium text-zinc-500">
            <div class="flex items-center gap-3">
                <img src="{{ asset('assests/img/logo.png') }}" alt="NUME" class="h-6 w-auto object-contain opacity-50 grayscale hover:grayscale-0 transition">
                <span>© {{ date('Y') }} {{ __('NUME Creative. Produção Estratégica.') }}</span>
            </div>
            <div class="flex items-center gap-4 text-xs">
                <a href="{{ route('legal.privacy') }}" class="text-zinc-400 hover:text-white underline">{{ __('Privacidade & Cookies (RGPD)') }}</a>
                <span>·</span>
                <a href="{{ route('login') }}" class="hover:text-orange-500 transition">{{ __('Acesso Produtora') }}</a>
            </div>
        </div>
    </footer>

    <!-- Banner de Consentimento de Cookies & Privacidade (RGPD / LOPDGDD) -->
    <div id="cookie-consent-banner" 
         class="fixed bottom-0 inset-x-0 z-50 bg-zinc-950/95 backdrop-blur-md border-t border-zinc-800 p-4 sm:p-5 shadow-2xl transition-all duration-300 transform translate-y-full">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-1 max-w-3xl">
                <div class="flex items-center gap-2">
                    <span class="text-sm"></span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-white">Privacidade & Uso de Cookies</h3>
                </div>
                <p class="text-xs text-zinc-400 leading-relaxed">
                    Utilizamos cookies para assegurar a autenticação segura do portal e aprimorar a experiência de navegação conforme as exigências do <strong>RGPD (UE)</strong> e da <strong>LOPDGDD (Espanha)</strong>. Você pode ler nossa 
                    <a href="{{ route('legal.privacy') }}" class="text-orange-400 hover:underline font-bold">Política de Privacidade e Proteção de Dados</a>.
                </p>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto shrink-0">
                <button type="button" onclick="acceptCookies('essential')"
                        class="flex-1 md:flex-none px-4 py-2.5 rounded-xl border border-zinc-700 hover:border-zinc-500 text-zinc-300 hover:text-white text-xs font-bold transition">
                    Apenas Essenciais
                </button>
                <button type="button" onclick="acceptCookies('all')"
                        class="flex-1 md:flex-none px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-95 text-white text-xs font-bold uppercase tracking-wider shadow-lg shadow-orange-950/30 transition">
                    Aceitar Todos
                </button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (!localStorage.getItem('nume_cookie_consent')) {
            setTimeout(() => {
                const banner = document.getElementById('cookie-consent-banner');
                if (banner) {
                    banner.classList.remove('translate-y-full');
                }
            }, 800);
        }
    });

    function acceptCookies(level) {
        localStorage.setItem('nume_cookie_consent', JSON.stringify({
            level: level,
            timestamp: new Date().toISOString()
        }));
        const banner = document.getElementById('cookie-consent-banner');
        if (banner) {
            banner.classList.add('translate-y-full');
        }
    }
    </script>
</body>
</html>
