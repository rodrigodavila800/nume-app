<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nume Social Engine API — Documentação para Desenvolvedores</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                },
            },
        }
    </script>
    @include('partials.favicons')
</head>
<body class="min-h-full flex flex-col font-sans selection:bg-rose-500 selection:text-white">
    <!-- Navbar Superior -->
    <header class="sticky top-0 z-50 border-b border-slate-800 bg-slate-950/80 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-2xl font-black tracking-tight text-white">NUME</span>
                <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold uppercase tracking-wider bg-rose-500/10 text-rose-400 border border-rose-500/20">Social Engine API v1</span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('api.v1.docs.json') }}" target="_blank" class="text-xs font-mono px-3 py-1.5 rounded-lg bg-slate-900 text-slate-300 hover:text-white border border-slate-800 hover:border-slate-700 transition">
                    OpenAPI 3.0 (JSON)
                </a>
                <a href="{{ route('login') }}" class="text-xs font-semibold px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white shadow-lg shadow-rose-950/50 transition">
                    Acessar Painel
                </a>
            </div>
        </div>
    </header>

    <div class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10 grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Navegação Lateral -->
        <aside class="lg:col-span-3 space-y-6">
            <div class="sticky top-24 space-y-4">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Visão Geral</p>
                <nav class="space-y-1 text-sm font-medium">
                    <a href="#autenticacao" class="block px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-900 transition">Autenticação & Chaves</a>
                    <a href="#nocode-templates" class="block px-3 py-2 rounded-lg text-rose-400 hover:text-rose-300 hover:bg-slate-900 transition font-semibold">Templates No-Code (Make / n8n)</a>
                    <a href="#rate-limits" class="block px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-900 transition">Rate Limits & Quota</a>
                    <a href="#plataformas" class="block px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-900 transition">Redes Suportadas</a>
                </nav>

                <p class="text-xs font-bold uppercase tracking-wider text-slate-500 pt-4">Endpoints REST</p>
                <nav class="space-y-1 text-sm font-medium">
                    <a href="#endpoint-connect" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-900 transition">
                        <span class="text-xs font-bold px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-400">POST</span>
                        <span>/social/connect-url</span>
                    </a>
                    <a href="#endpoint-publish" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-900 transition">
                        <span class="text-xs font-bold px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400">POST</span>
                        <span>/social/publish</span>
                    </a>
                    <a href="#endpoint-status" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-900 transition">
                        <span class="text-xs font-bold px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400">GET</span>
                        <span>/social/publications/{id}</span>
                    </a>
                    <a href="#endpoint-profile" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-900 transition">
                        <span class="text-xs font-bold px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400">GET</span>
                        <span>/social/profiles/{id}</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Conteúdo Principal -->
        <main class="lg:col-span-9 space-y-12">
            <!-- Hero / Intro -->
            <section class="space-y-4">
                <h1 class="text-3xl font-extrabold text-white tracking-tight">Nume Social Engine Developer API</h1>
                <p class="text-base text-slate-400 leading-relaxed">
                    API corporativa para publicação, sincronização e automação multi-rede de vídeos verticais (Instagram Reels, TikTok, YouTube Shorts, Facebook, LinkedIn, X, Threads, Pinterest).
                    Projetada para que produtoras, agências e softwares externos publiquem em massa com zero fricção.
                </p>
                <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 text-sm flex items-center justify-between">
                    <div>
                        <span class="text-slate-400">Base URL:</span>
                        <code class="ml-2 px-2 py-1 rounded bg-slate-950 font-mono text-rose-400">{{ $baseUrl }}</code>
                    </div>
                    <span class="text-xs text-slate-500">JSON REST • HTTPS Obrigatório</span>
                </div>
            </section>

            <!-- Autenticação -->
            <section id="autenticacao" class="space-y-4 pt-6 border-t border-slate-800">
                <h2 class="text-xl font-bold text-white">Autenticação</h2>
                <p class="text-sm text-slate-400 leading-relaxed">
                    Todas as requisições autenticadas exigem uma Chave de API da produtora emitida no painel. O token deve ser enviado no cabeçalho <code class="font-mono text-slate-200">Authorization: Bearer nume_live_...</code> ou <code class="font-mono text-slate-200">X-API-Key: nume_live_...</code>.
                </p>

                <div class="rounded-xl overflow-hidden bg-slate-900 border border-slate-800">
                    <div class="px-4 py-2 bg-slate-950/60 border-b border-slate-800 flex items-center justify-between text-xs text-slate-400 font-mono">
                        <span>Exemplo de Cabeçalho HTTP</span>
                    </div>
                    <pre class="p-4 text-xs font-mono text-slate-300 overflow-x-auto leading-relaxed"><code>Authorization: Bearer nume_live_e7a8f912b3c4d5e6...
Content-Type: application/json
Accept: application/json</code></pre>
                </div>
            </section>

            <!-- Templates No-Code (Make.com & n8n) -->
            <section id="nocode-templates" class="space-y-4 pt-6 border-t border-slate-800">
                <div class="flex items-center space-x-3">
                    <span class="text-xs font-bold px-2 py-0.5 rounded bg-rose-500/20 text-rose-400">NO-CODE</span>
                    <h2 class="text-xl font-bold text-white">Integrações No-Code Prontas</h2>
                </div>
                <p class="text-sm text-slate-400 leading-relaxed">
                    Baixe os blueprints e conecte a NUME Social Engine diretamente aos seus fluxos de trabalho no <strong>Make.com</strong>, <strong>n8n</strong> ou <strong>Zapier</strong> em menos de 2 minutos.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Make.com Card -->
                    <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3 flex flex-col justify-between">
                        <div class="space-y-2">
                            <div class="flex items-center space-x-2">
                                <span class="text-lg">🟣</span>
                                <h3 class="text-base font-bold text-white">Make.com (Integromat)</h3>
                            </div>
                            <p class="text-xs text-slate-400">
                                Blueprint oficial com trigger de Webhook e módulo HTTP configurado para publicar vídeos instantaneamente no Instagram, TikTok e YouTube.
                            </p>
                        </div>
                        <a href="{{ route('api.v1.integrations.make') }}" class="inline-flex items-center justify-center space-x-2 w-full py-2.5 px-4 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-semibold text-xs transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            <span>Baixar Blueprint Make (.json)</span>
                        </a>
                    </div>

                    <!-- n8n Card -->
                    <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3 flex flex-col justify-between">
                        <div class="space-y-2">
                            <div class="flex items-center space-x-2">
                                <span class="text-lg">🟠</span>
                                <h3 class="text-base font-bold text-white">n8n (Self-hosted / Cloud)</h3>
                            </div>
                            <p class="text-xs text-slate-400">
                                Workflow oficial pronto para importação via clipboard ou arquivo JSON com tratamento de payload, headers e resposta assíncrona.
                            </p>
                        </div>
                        <a href="{{ route('api.v1.integrations.n8n') }}" class="inline-flex items-center justify-center space-x-2 w-full py-2.5 px-4 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-semibold text-xs transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            <span>Baixar Workflow n8n (.json)</span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- Redes Suportadas -->
            <section id="plataformas" class="space-y-4 pt-6 border-t border-slate-800">
                <h2 class="text-xl font-bold text-white">Redes Sociais Suportadas</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach($platforms as $p)
                        <div class="p-3 rounded-lg bg-slate-900 border border-slate-800 flex items-center space-x-2">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            <span class="text-xs font-mono font-medium text-slate-200">{{ $p->value }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <!-- Endpoint: Connect URL -->
            <section id="endpoint-connect" class="space-y-4 pt-6 border-t border-slate-800">
                <div class="flex items-center space-x-3">
                    <span class="text-xs font-bold px-2 py-1 rounded bg-blue-500/20 text-blue-400">POST</span>
                    <h2 class="text-lg font-bold text-white font-mono">/social/connect-url</h2>
                </div>
                <p class="text-sm text-slate-400">
                    Gera uma URL de autorização White-label segura para o seu cliente final conectar as contas sociais dele sem expor senhas.
                </p>

                <div class="rounded-xl overflow-hidden bg-slate-900 border border-slate-800">
                    <div class="px-4 py-2 bg-slate-950/60 border-b border-slate-800 text-xs text-slate-400 font-mono">
                        Request Body (JSON)
                    </div>
                    <pre class="p-4 text-xs font-mono text-emerald-400 overflow-x-auto leading-relaxed"><code>{
  "email": "cliente@empresa.com",
  "name": "Alejandro Martínez",
  "redirect_url": "https://suaplataforma.com/social/callback"
}</code></pre>
                </div>

                <div class="rounded-xl overflow-hidden bg-slate-900 border border-slate-800">
                    <div class="px-4 py-2 bg-slate-950/60 border-b border-slate-800 text-xs text-slate-400 font-mono">
                        Response 200 OK
                    </div>
                    <pre class="p-4 text-xs font-mono text-slate-300 overflow-x-auto leading-relaxed"><code>{
  "status": "success",
  "data": {
    "client_id": 14,
    "email": "cliente@empresa.com",
    "connected": true,
    "platforms_connected": ["instagram", "tiktok"],
    "connect_url": "https://connect.nume.app/?token=jwt_..."
  }
}</code></pre>
                </div>
            </section>

            <!-- Endpoint: Publish -->
            <section id="endpoint-publish" class="space-y-4 pt-6 border-t border-slate-800">
                <div class="flex items-center space-x-3">
                    <span class="text-xs font-bold px-2 py-1 rounded bg-emerald-500/20 text-emerald-400">POST</span>
                    <h2 class="text-lg font-bold text-white font-mono">/social/publish</h2>
                </div>
                <p class="text-sm text-slate-400">
                    Dispara o upload e a publicação imediata de um vídeo vertical para múltiplas redes em simultâneo.
                </p>

                <div class="rounded-xl overflow-hidden bg-slate-900 border border-slate-800">
                    <div class="px-4 py-2 bg-slate-950/60 border-b border-slate-800 text-xs text-slate-400 font-mono">
                        Request Body (JSON)
                    </div>
                    <pre class="p-4 text-xs font-mono text-emerald-400 overflow-x-auto leading-relaxed"><code>{
  "email": "cliente@empresa.com",
  "video_id": 345,
  "title": "3 Passos para Vender Mais no Digital com a NUME #viral #estrategia",
  "platforms": ["instagram", "tiktok", "youtube"]
}</code></pre>
                </div>

                <div class="rounded-xl overflow-hidden bg-slate-900 border border-slate-800">
                    <div class="px-4 py-2 bg-slate-950/60 border-b border-slate-800 text-xs text-slate-400 font-mono">
                        Response 201 Created
                    </div>
                    <pre class="p-4 text-xs font-mono text-slate-300 overflow-x-auto leading-relaxed"><code>{
  "status": "success",
  "data": {
    "publication_id": 89,
    "request_id": "req_881a7d8e-32b0",
    "title": "3 Passos para Vender Mais no Digital...",
    "platforms": ["instagram", "tiktok", "youtube"],
    "status": "processando",
    "created_at": "2026-10-02T18:40:00+00:00"
  }
}</code></pre>
                </div>
            </section>

            <!-- Endpoint: Status -->
            <section id="endpoint-status" class="space-y-4 pt-6 border-t border-slate-800">
                <div class="flex items-center space-x-3">
                    <span class="text-xs font-bold px-2 py-1 rounded bg-amber-500/20 text-amber-400">GET</span>
                    <h2 class="text-lg font-bold text-white font-mono">/social/publications/{id}</h2>
                </div>
                <p class="text-sm text-slate-400">
                    Consulta em tempo real o estado de uma publicação. Sincroniza diretamente com as plataformas para confirmar a entrega.
                </p>

                <div class="rounded-xl overflow-hidden bg-slate-900 border border-slate-800">
                    <div class="px-4 py-2 bg-slate-950/60 border-b border-slate-800 text-xs text-slate-400 font-mono">
                        Response 200 OK
                    </div>
                    <pre class="p-4 text-xs font-mono text-slate-300 overflow-x-auto leading-relaxed"><code>{
  "status": "success",
  "data": {
    "publication_id": 89,
    "title": "3 Passos para Vender Mais no Digital...",
    "status": "publicado",
    "platforms": ["instagram", "tiktok", "youtube"],
    "request_id": "req_881a7d8e-32b0",
    "published_at": "2026-10-02T18:40:15+00:00"
  }
}</code></pre>
                </div>
            </section>
        </main>
    </div>

    <!-- Footer -->
    <footer class="border-t border-slate-800 bg-slate-950 py-6 text-center text-xs text-slate-500">
        <p>&copy; {{ date('Y') }} NUME SaaS Multi-tenant. Arquitetura DevSecOps & Sofi IA.</p>
    </footer>
</body>
</html>
