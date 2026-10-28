@extends('layouts.app')

@section('title', __('NUME | Configurações de Pagamento & Faturamento'))
@section('breadcrumb', __('Gateways & Fiscal'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Configurações de Pagamento & Login OAuth') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">
                {{ __('Gerencie o gateway global Stripe (assinaturas e pacotes), login com Google e acesse os métodos exclusivos da sua produtora.') }}
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold flex items-center gap-2">
            <span>✓</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs font-semibold flex items-center gap-2">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs font-semibold space-y-1">
            <div class="font-bold flex items-center gap-1.5">
                <span>{{ __('Não foi possível salvar as configurações. Verifique os campos:') }}</span>
            </div>
            <ul class="list-disc list-inside pl-1 text-[11px] text-red-300 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Connection Status Banner -->
    <div class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl {{ $stripeStatus['connected'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }} flex items-center justify-center font-bold text-lg shrink-0">
                {{ $stripeStatus['connected'] ? '✓' : '' }}
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-white">{{ __('Status da Integração Stripe') }}</h3>
                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md {{ $stripeStatus['connected'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400' }}">
                        {{ $stripeStatus['connected'] ? __('Conectado & Operacional') : __('Aguardando Configuração') }}
                    </span>
                </div>
                <p class="text-xs text-zinc-400 mt-0.5">{{ $stripeStatus['message'] }}</p>
            </div>
        </div>

        <div class="text-xs text-zinc-500 font-mono">
            Modo: <strong class="text-orange-400 uppercase">{{ $stripeMode }}</strong>
        </div>
    </div>

    <!-- Navegação por Abas -->
    <x-settings-tabs active="gateways" />

    <!-- Atalhos Rápidos para Configurações Financeiras da Produtora (Tenant) -->
    <div class="p-5 rounded-2xl bg-zinc-900/70 border border-zinc-800 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-zinc-300">{{ __('Configurações Financeiras & Fiscais da sua Produtora') }}</h2>
                <p class="text-[11px] text-zinc-500">{{ __('TPV Redsys/Bizum, IBAN e dados fiscais de faturação (AEAT) são isolados por produtora em suas respectivas abas.') }}</p>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <a href="{{ route('admin.settings.fiscal') }}" class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-orange-500/50 transition flex items-center justify-between group">
                <div>
                    <p class="text-xs font-bold text-white group-hover:text-orange-400 transition">{{ __('Perfil Fiscal & IVA/IRPF') }}</p>
                    <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('NIF/CIF, série e alíquotas') }}</p>
                </div>
                <span class="text-zinc-500 group-hover:text-orange-400 text-xs font-bold">→</span>
            </a>
            <a href="{{ route('admin.settings.redsys') }}" class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-blue-500/50 transition flex items-center justify-between group">
                <div>
                    <p class="text-xs font-bold text-white group-hover:text-blue-400 transition">{{ __('TPV Redsys & Bizum') }}</p>
                    <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('FUC, terminal e chave SHA-256') }}</p>
                </div>
                <span class="text-zinc-500 group-hover:text-blue-400 text-xs font-bold">→</span>
            </a>
            <a href="{{ route('admin.settings.bank-transfer') }}" class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-emerald-500/50 transition flex items-center justify-between group">
                <div>
                    <p class="text-xs font-bold text-white group-hover:text-emerald-400 transition">{{ __('Transferência (IBAN)') }}</p>
                    <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Conta bancária SEPA e titular') }}</p>
                </div>
                <span class="text-zinc-500 group-hover:text-emerald-400 text-xs font-bold">→</span>
            </a>
        </div>
    </div>

    <!-- Gateway Settings Form (Stripe + Google OAuth) -->
    <form action="{{ route('admin.settings.gateways.update') }}" method="POST" id="settings-unified-form" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-8">
        @csrf

        <!-- Bloco 1: Stripe -->
        <div class="space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-zinc-800">
                <div>
                    <h2 class="text-base font-extrabold text-white flex items-center gap-2">
{{ __('Gateway Stripe (Global / Apple Pay / Cards)') }}
                    </h2>
                    <p class="text-xs text-zinc-400 mt-0.5">
                        {{ __('Ativa ou desativa o recebimento via Stripe nos portais e vitrines da plataforma.') }}
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="stripe_enabled" value="1" @checked(old('stripe_enabled', $stripeEnabled)) class="sr-only peer">
                    <div class="w-11 h-6 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-600"></div>
                    <span class="ml-2 text-xs font-semibold text-zinc-300">{{ __('Ativo') }}</span>
                </label>
            </div>

            <!-- Modo de Operação -->
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Ambiente do Gateway') }} <span class="text-orange-500">*</span>
                </label>
                <div class="grid grid-cols-2 gap-3 max-w-md">
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-zinc-800 bg-zinc-950 cursor-pointer hover:border-orange-500/50 transition">
                        <input type="radio" name="stripe_mode" value="test" @checked(old('stripe_mode', $stripeMode) === 'test')
                               class="text-orange-600 focus:ring-orange-500">
                        <div>
                            <p class="text-xs font-bold text-white">{{ __('Modo Teste / Sandbox') }}</p>
                            <p class="text-[10px] text-zinc-500">pk_test / sk_test</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-zinc-800 bg-zinc-950 cursor-pointer hover:border-orange-500/50 transition">
                        <input type="radio" name="stripe_mode" value="live" @checked(old('stripe_mode', $stripeMode) === 'live')
                               class="text-orange-600 focus:ring-orange-500">
                        <div>
                            <p class="text-xs font-bold text-white">{{ __('Modo Produção (Live)') }}</p>
                            <p class="text-[10px] text-zinc-500">pk_live / sk_live</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Publishable Key -->
            <div class="space-y-1.5">
                <label for="stripe_key" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Stripe Publishable Key (Chave Pública)') }}
                </label>
                <input type="text" name="stripe_key" id="stripe_key"
                       placeholder="pk_test_... ou pk_live_..."
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600"
                       value="{{ old('stripe_key', $stripeKey) }}">
                <p class="text-[11px] text-zinc-500">{{ __('Utilizada na interface do cliente e componentes de checkout.') }}</p>
            </div>

            <!-- Secret Key -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <label for="stripe_secret" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Stripe Secret Key (Chave Secreta)') }}
                    </label>
                    @if(!empty($stripeSecret))
                        <span class="text-[11px] font-bold text-emerald-400">{{ __('Chave configurada') }}</span>
                    @endif
                </div>
                <div class="relative">
                    <input type="password" name="stripe_secret" id="stripe_secret"
                           placeholder="{{ !empty($stripeSecret) ? '••••••••••••••••••••••••••••••••' : 'sk_test_... ou sk_live_...' }}"
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">
                    <button type="button" onclick="togglePasswordVisibility('stripe_secret')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-zinc-500 hover:text-zinc-300 p-1"
                            title="{{ __('Alternar visibilidade') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                <p class="text-[11px] text-zinc-500">{{ __('Deixe em branco caso deseje manter a chave atual.') }}</p>
            </div>

            <!-- Webhook Secret -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <label for="stripe_webhook_secret" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Stripe Webhook Signing Secret') }}
                    </label>
                    @if(!empty($stripeWebhook))
                        <span class="text-[11px] font-bold text-emerald-400">{{ __('Secret configurado') }}</span>
                    @endif
                </div>
                <div class="relative">
                    <input type="password" name="stripe_webhook_secret" id="stripe_webhook_secret"
                           placeholder="{{ !empty($stripeWebhook) ? '••••••••••••••••••••••••' : 'whsec_...' }}"
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">
                    <button type="button" onclick="togglePasswordVisibility('stripe_webhook_secret')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-zinc-500 hover:text-zinc-300 p-1"
                            title="{{ __('Alternar visibilidade') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                <p class="text-[11px] text-zinc-500">{{ __('URL do Webhook:') }} <code class="text-orange-400 bg-zinc-950 px-1.5 py-0.5 rounded font-mono">{{ url('/stripe/webhook') }}</code></p>
            </div>

            <!-- Grid 2 colunas: Price ID & Fee -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div class="space-y-1.5">
                    <label for="stripe_price_id" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Stripe Price ID (Assinatura)') }}
                    </label>
                    <input type="text" name="stripe_price_id" id="stripe_price_id"
                           placeholder="price_1N..."
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600"
                           value="{{ old('stripe_price_id', $stripePriceId) }}">
                </div>

                <div class="space-y-1.5">
                    <label for="stripe_platform_fee_percent" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Taxa da Plataforma NUME (%)') }}
                    </label>
                    <input type="number" step="0.1" min="0" max="100" name="stripe_platform_fee_percent" id="stripe_platform_fee_percent"
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600"
                           value="{{ old('stripe_platform_fee_percent', $stripeFee) }}">
                    <p class="text-[11px] text-zinc-500">{{ __('Dedução retida automaticamente nas vendas dos pacotes.') }}</p>
                </div>
            </div>
        </div>

        <!-- Bloco 2: Google OAuth 2.0 (Login com 1 clique) -->
        <div class="pt-8 border-t border-zinc-800 space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-zinc-800">
                <div>
                    <h3 class="text-sm font-extrabold uppercase tracking-wider text-white flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        <span>{{ __('Autenticação Google (OAuth 2.0 — Login Social)') }}</span>
                    </h3>
                    <p class="text-xs text-zinc-400 mt-0.5">
                        {{ __('Permite login com 1 clique para clientes e colaboradores através de contas Google.') }}
                    </p>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ !empty($googleClientId) ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'bg-zinc-800 text-zinc-400' }}">
                    {{ !empty($googleClientId) ? __('Configurado') : __('Não configurado') }}
                </span>
            </div>

            <!-- URIs para cadastrar no Google Cloud Console -->
            <div class="p-4 rounded-xl bg-zinc-950 border border-zinc-800 text-xs text-zinc-400 space-y-2">
                <div class="font-bold text-zinc-300 uppercase tracking-wider text-[10px]">
                    {{ __('Dados para cadastrar no Google Cloud Console (APIs & Services > Credentials)') }}:
                </div>
                <div class="space-y-1 font-mono text-[11px]">
                    <div>
                        <span class="text-zinc-500">{{ __('Origens JavaScript autorizadas:') }}</span>
                        <code class="text-orange-400 ml-1">{{ url('/') }}</code>
                    </div>
                    <div>
                        <span class="text-zinc-500">{{ __('URI de redirecionamento (Produtora):') }}</span>
                        <code class="text-blue-400 ml-1">{{ route('auth.google.callback') }}</code>
                    </div>
                    <div>
                        <span class="text-zinc-500">{{ __('URI de redirecionamento (Portal Cliente):') }}</span>
                        <code class="text-emerald-400 ml-1">{{ route('portal.auth.google.callback') }}</code>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Client ID -->
                <div class="space-y-1.5">
                    <label for="google_client_id" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Google Client ID') }}
                    </label>
                    <input type="text" name="google_client_id" id="google_client_id"
                           placeholder="xxxx.apps.googleusercontent.com"
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition placeholder:text-zinc-600"
                           value="{{ old('google_client_id', $googleClientId) }}">
                </div>

                <!-- Client Secret -->
                <div class="space-y-1.5">
                    <label for="google_client_secret" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Google Client Secret') }}
                    </label>
                    <div class="relative">
                        <input type="password" name="google_client_secret" id="google_client_secret"
                               placeholder="{{ !empty($googleClientSecret) ? '••••••••••••••••' : 'GOCSPX-xxxx' }}"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition placeholder:text-zinc-600">
                        <button type="button" onclick="togglePasswordVisibility('google_client_secret')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-white text-xs p-1"
                                title="{{ __('Alternar visibilidade') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                    <p class="text-[11px] text-zinc-500">{{ __('Criptografado com AES-256 no banco de dados.') }}</p>
                </div>
            </div>
        </div>

        <!-- Botão Salvar Configurações -->
        <div class="flex items-center justify-between pt-6 border-t border-zinc-800">
            <span class="text-xs text-zinc-500">
                {{ __('Todas as credenciais são salvas de forma criptografada com AES-256.') }}
            </span>
            <button type="submit"
                    class="bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-extrabold text-xs uppercase tracking-wider px-6 py-3 rounded-xl shadow-lg shadow-orange-900/20 transition-all flex items-center gap-2 cursor-pointer">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                <span>{{ __('Salvar Configurações') }}</span>
            </button>
        </div>
    </form>
</div>

<script>
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
@endsection
