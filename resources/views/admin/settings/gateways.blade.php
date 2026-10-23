@extends('layouts.app')

@section('title', __('NUME | Configurações de Pagamento & Faturamento'))
@section('breadcrumb', __('Gateways & Fiscal'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Configurações de Pagamento & Perfil Fiscal') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">
                {{ __('Gerencie gateways de pagamento, alíquotas fiscais (IVA/IRPF), dados da emissora e integrações.') }}
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
            <span>⚠️</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs font-semibold space-y-1">
            <div class="font-bold flex items-center gap-1.5">
                <span>⚠️</span>
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
                {{ $stripeStatus['connected'] ? '✓' : '⚡' }}
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

    <!-- Navegação por Abas (Tabs) 100% Tailwind -->
    <x-settings-tabs active="gateways" />

    <!-- Gateway Settings Form -->
    <form action="{{ route('admin.settings.gateways.update') }}" method="POST" id="settings-unified-form" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-6">
        @csrf

        <!-- ========================================== -->
        <!-- ABA 1: GATEWAYS DE PAGAMENTO (STRIPE & BBVA) -->
        <!-- ========================================== -->
        <div id="tab-content-gateways" class="settings-tab-content space-y-8">
            
            <!-- Bloco 1: Stripe -->
            <div class="space-y-6">
                <!-- Header Stripe com Toggle de Ativação -->
                <div class="flex items-center justify-between pb-4 border-b border-zinc-800">
                    <div>
                        <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                            <span>🌐</span> {{ __('Gateway Stripe (Global / Apple Pay / Cards)') }}
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
                            <span class="text-[11px] font-bold text-emerald-400">🔒 {{ __('Chave configurada') }}</span>
                        @endif
                    </div>
                    <div class="relative">
                        <input type="password" name="stripe_secret" id="stripe_secret"
                               placeholder="{{ !empty($stripeSecret) ? '••••••••••••••••••••••••••••••••' : 'sk_test_... ou sk_live_...' }}"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">
                        <button type="button" onclick="togglePasswordVisibility('stripe_secret')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-zinc-500 hover:text-zinc-300 p-1">
                            👁️
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
                            <span class="text-[11px] font-bold text-emerald-400">🔒 {{ __('Secret configurado') }}</span>
                        @endif
                    </div>
                    <div class="relative">
                        <input type="password" name="stripe_webhook_secret" id="stripe_webhook_secret"
                               placeholder="{{ !empty($stripeWebhook) ? '••••••••••••••••••••••••' : 'whsec_...' }}"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">
                        <button type="button" onclick="togglePasswordVisibility('stripe_webhook_secret')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-zinc-500 hover:text-zinc-300 p-1">
                            👁️
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

            <!-- Bloco 2: BBVA Redsys / Bizum -->
            <div class="pt-8 border-t border-zinc-800/80 space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                                <span>🏦</span> {{ __('TPV Virtual BBVA (Redsys / Bizum / Tarjetas)') }}
                            </h2>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                PCI-DSS SAQ-A
                            </span>
                        </div>
                        <p class="text-xs text-zinc-400 mt-0.5">
                            {{ __('Pagamento instantâneo via Bizum e cartões na Espanha com taxas interbancárias ultrabaixas.') }}
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="redsys_enabled" value="1" @checked(old('redsys_enabled', $redsysEnabled)) class="sr-only peer">
                        <div class="w-11 h-6 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-2 text-xs font-semibold text-zinc-300">{{ __('Ativo') }}</span>
                    </label>
                </div>

                <!-- Sub-toggles específicos de Bizum e Cartão BBVA -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl bg-zinc-950 border border-zinc-800/80">
                    <!-- Toggle Bizum -->
                    <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-900 border border-zinc-800">
                        <div class="flex items-center gap-2.5">
                            <span class="text-lg">⚡</span>
                            <div>
                                <span class="text-xs font-bold text-white block">Bizum (BBVA)</span>
                                <span class="text-[10px] text-zinc-400 block">{{ __('Pagamento instantâneo por celular espanhol') }}</span>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="redsys_bizum_enabled" value="1" @checked(old('redsys_bizum_enabled', $redsysBizumEnabled)) class="sr-only peer">
                            <div class="w-9 h-5 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
                        </label>
                    </div>

                    <!-- Toggle Cartão BBVA Redsys -->
                    <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-900 border border-zinc-800">
                        <div class="flex items-center gap-2.5">
                            <span class="text-lg">💳</span>
                            <div>
                                <span class="text-xs font-bold text-white block">{{ __('Tarjeta Segura (BBVA)') }}</span>
                                <span class="text-[10px] text-zinc-400 block">{{ __('Cartões com autenticação 3D Secure') }}</span>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="redsys_card_enabled" value="1" @checked(old('redsys_card_enabled', $redsysCardEnabled)) class="sr-only peer">
                            <div class="w-9 h-5 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>
                </div>

                <!-- Ambiente Redsys -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Ambiente do TPV Redsys') }} <span class="text-blue-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-3 max-w-md">
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-zinc-800 bg-zinc-950 cursor-pointer hover:border-blue-500/50 transition">
                            <input type="radio" name="redsys_environment" value="test" @checked(old('redsys_environment', $redsysEnvironment) === 'test')
                                   class="text-blue-600 focus:ring-blue-500">
                            <div>
                                <p class="text-xs font-bold text-white">{{ __('Sandbox / Pruebas') }}</p>
                                <p class="text-[10px] text-zinc-500">sis-t.redsys.es:25443</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-zinc-800 bg-zinc-950 cursor-pointer hover:border-blue-500/50 transition">
                            <input type="radio" name="redsys_environment" value="live" @checked(old('redsys_environment', $redsysEnvironment) === 'live')
                                   class="text-blue-600 focus:ring-blue-500">
                            <div>
                                <p class="text-xs font-bold text-white">{{ __('Produção Real (BBVA)') }}</p>
                                <p class="text-[10px] text-zinc-500">sis.redsys.es</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Grid FUC e Terminal -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="redsys_merchant_code" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                            {{ __('Número de Comercio (FUC)') }}
                        </label>
                        <input type="text" name="redsys_merchant_code" id="redsys_merchant_code"
                               placeholder="999008881"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition placeholder:text-zinc-600"
                               value="{{ old('redsys_merchant_code', $redsysMerchantCode) }}">
                        <p class="text-[11px] text-zinc-500">{{ __('Código de 9 dígitos fornecido pelo BBVA.') }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="redsys_terminal" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                            {{ __('Terminal') }}
                        </label>
                        <input type="text" name="redsys_terminal" id="redsys_terminal"
                               placeholder="001"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition placeholder:text-zinc-600"
                               value="{{ old('redsys_terminal', $redsysTerminal) }}">
                        <p class="text-[11px] text-zinc-500">{{ __('Geralmente 001 ou 1.') }}</p>
                    </div>
                </div>

                <!-- Secret Key Redsys -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label for="redsys_secret_key" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                            {{ __('Clave Secreta SHA-256 (Clave de Encriptación)') }}
                        </label>
                        @if(!empty($redsysSecretKey))
                            <span class="text-[11px] font-bold text-emerald-400">🔒 {{ __('Clave configurada') }}</span>
                        @endif
                    </div>
                    <div class="relative">
                        <input type="password" name="redsys_secret_key" id="redsys_secret_key"
                               placeholder="{{ !empty($redsysSecretKey) ? '••••••••••••••••••••••••' : 'Chave SHA-256 do Portal Canales Redsys' }}"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition placeholder:text-zinc-600">
                        <button type="button" onclick="togglePasswordVisibility('redsys_secret_key')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-zinc-500 hover:text-zinc-300 p-1">
                            👁️
                        </button>
                    </div>
                    <p class="text-[11px] text-zinc-500">
                        {{ __('URL de Notificação (Webhook Redsys):') }}
                        <code class="text-blue-400 bg-zinc-950 px-1.5 py-0.5 rounded font-mono">{{ url('/webhooks/redsys') }}</code>
                    </p>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- ABA 2: PERFIL FISCAL & FATURAMENTO LEGAL  -->
        <!-- ========================================== -->
        <div id="tab-content-fiscal" class="settings-tab-content space-y-6 hidden">
            
            <div class="pb-4 border-b border-zinc-800">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                        <span>⚖️</span> {{ __('Perfil Fiscal da Produtora & Tributação (AEAT España)') }}
                    </h2>
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        Hacienda / Real Decreto 1619/2012
                    </span>
                </div>
                <p class="text-xs text-zinc-400 mt-0.5">
                    {{ __('Configure se você fatura como Trabalhador Autônomo ou Sociedade Mercantil para aplicação exata de retenções IRPF e IVA.') }}
                </p>
            </div>

            <!-- Seleção de Regime Tributário do Emissor -->
            <div class="space-y-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Regime Jurídico / Tributário do Titular') }} <span class="text-emerald-500">*</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-zinc-800 bg-zinc-950 cursor-pointer hover:border-emerald-500/50 transition">
                        <input type="radio" name="fiscal_regime" value="autonomo" @checked(old('fiscal_regime', $fiscalRegime) === 'autonomo')
                               class="text-emerald-600 focus:ring-emerald-500 mt-1" onchange="updateIrpfVisibility()">
                        <div>
                            <p class="text-xs font-extrabold text-white flex items-center gap-1.5">
                                <span>👤</span> {{ __('Autónomo Profissional (Persona Física)') }}
                            </p>
                            <p class="text-[11px] text-zinc-400 mt-0.5">
                                {{ __('Aplica retenção de IRPF automaticamente quando o comprador for Empresa ou outro Autônomo na Espanha.') }}
                            </p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-zinc-800 bg-zinc-950 cursor-pointer hover:border-emerald-500/50 transition">
                        <input type="radio" name="fiscal_regime" value="empresa_sl" @checked(old('fiscal_regime', $fiscalRegime) === 'empresa_sl')
                               class="text-emerald-600 focus:ring-emerald-500 mt-1" onchange="updateIrpfVisibility()">
                        <div>
                            <p class="text-xs font-extrabold text-white flex items-center gap-1.5">
                                <span>🏢</span> {{ __('Sociedad Mercantil (S.L. / S.A.)') }}
                            </p>
                            <p class="text-[11px] text-zinc-400 mt-0.5">
                                {{ __('Faturamento padrão Base + 21% IVA sem retenção de IRPF em serviços audiovisuais.') }}
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Alíquota de IRPF e IVA -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <!-- Alíquota IRPF -->
                <div id="box-irpf-rate" class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label for="fiscal_irpf_rate" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                            {{ __('Alíquota de Retenção IRPF (%)') }} <span class="text-orange-500">*</span>
                        </label>
                        <span class="text-[10px] text-orange-400 font-mono font-bold">{{ __('Dedução em Faturas B2B') }}</span>
                    </div>
                    <select name="fiscal_irpf_rate" id="fiscal_irpf_rate"
                            class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-sans focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                        <option value="15.00" @selected((float) old('fiscal_irpf_rate', $fiscalIrpfRate) == 15.00)>15,00% — {{ __('Alíquota Geral de Autônomo na Espanha') }}</option>
                        <option value="7.00" @selected((float) old('fiscal_irpf_rate', $fiscalIrpfRate) == 7.00)>7,00% — {{ __('Novo Autônomo (Primeiros 3 Anos)') }}</option>
                        <option value="0.00" @selected((float) old('fiscal_irpf_rate', $fiscalIrpfRate) == 0.00)>0,00% — {{ __('Isento de Retenção') }}</option>
                    </select>
                    <p class="text-[11px] text-zinc-500">
                        {{ __('Abatido do total a pagar quando a empresa compradora for espanhola para repasse ao Modelo 111 da AEAT.') }}
                    </p>
                </div>

                <!-- Alíquota padrão de IVA -->
                <div class="space-y-1.5">
                    <label for="tax_default_iva_rate" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ __('Alíquota Geral de IVA (%)') }} <span class="text-emerald-500">*</span>
                    </label>
                    <input type="number" step="0.01" min="0" max="100" name="tax_default_iva_rate" id="tax_default_iva_rate"
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder:text-zinc-600"
                           value="{{ old('tax_default_iva_rate', $taxDefaultIvaRate) }}">
                    <p class="text-[11px] text-zinc-500">{{ __('Alíquota de 21% para produções audiovisuais na Espanha Peninsular.') }}</p>
                </div>
            </div>

            <!-- Dados Cadastrais da Emissora (Fatura Oficial) -->
            <div class="pt-4 border-t border-zinc-800/80 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                    📋 {{ __('Identificação Cadastral da Produtora para Emissão de Faturas') }}
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Razão Social / Nome do Titular -->
                    <div class="sm:col-span-2 space-y-1.5">
                        <label for="fiscal_company_name" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                            {{ __('Nome do Titular / Razão Social') }} <span class="text-emerald-500">*</span>
                        </label>
                        <input type="text" name="fiscal_company_name" id="fiscal_company_name"
                               placeholder="{{ __('Ex: Rodrigo Souza ou Nume Creative') }}"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-sans focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder:text-zinc-600"
                               value="{{ old('fiscal_company_name', $fiscalCompanyName) }}">
                    </div>

                    <!-- CIF / NIF Fiscal -->
                    <div class="space-y-1.5">
                        <label for="fiscal_company_cif" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                            {{ __('NIF / DNI / NIE / CIF') }} <span class="text-emerald-500">*</span>
                        </label>
                        <input type="text" name="fiscal_company_cif" id="fiscal_company_cif"
                               placeholder="12345678Z ou B50987654"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder:text-zinc-600"
                               value="{{ old('fiscal_company_cif', $fiscalCompanyCif) }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Domicílio Fiscal -->
                    <div class="sm:col-span-2 space-y-1.5">
                        <label for="fiscal_company_address" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                            {{ __('Domicílio Fiscal / Endereço da Sede') }}
                        </label>
                        <input type="text" name="fiscal_company_address" id="fiscal_company_address"
                               placeholder="Calle Alfonso I, 12, Planta 3"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-sans focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder:text-zinc-600"
                               value="{{ old('fiscal_company_address', $fiscalCompanyAddress) }}">
                    </div>

                    <!-- Código Postal -->
                    <div class="space-y-1.5">
                        <label for="fiscal_company_postal_code" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                            {{ __('Código Postal') }}
                        </label>
                        <input type="text" name="fiscal_company_postal_code" id="fiscal_company_postal_code"
                               placeholder="50003"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder:text-zinc-600"
                               value="{{ old('fiscal_company_postal_code', $fiscalCompanyPostalCode) }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Cidade -->
                    <div class="space-y-1.5">
                        <label for="fiscal_company_city" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                            {{ __('Cidade / Província') }}
                        </label>
                        <input type="text" name="fiscal_company_city" id="fiscal_company_city"
                               placeholder="Zaragoza"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-sans focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder:text-zinc-600"
                               value="{{ old('fiscal_company_city', $fiscalCompanyCity) }}">
                    </div>

                    <!-- E-mail de Faturamento -->
                    <div class="space-y-1.5">
                        <label for="fiscal_company_email" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                            {{ __('E-mail de Faturamento Oficial') }}
                        </label>
                        <input type="email" name="fiscal_company_email" id="fiscal_company_email"
                               placeholder="facturacion@numecreative.es"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-sans focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder:text-zinc-600"
                               value="{{ old('fiscal_company_email', $fiscalCompanyEmail) }}">
                    </div>

                    <!-- Série Correlativa -->
                    <div class="space-y-1.5">
                        <label for="fiscal_invoice_series" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                            {{ __('Série de Faturamento') }}
                        </label>
                        <input type="text" name="fiscal_invoice_series" id="fiscal_invoice_series"
                               placeholder="NUM-2026-"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder:text-zinc-600"
                               value="{{ old('fiscal_invoice_series', $fiscalInvoiceSeries) }}">
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- ABA 3: GOOGLE OAUTH & APIS                 -->
        <!-- ========================================== -->
        <div id="tab-content-oauth" class="settings-tab-content space-y-6 hidden">
            <div class="flex items-center justify-between pb-4 border-b border-zinc-800">
                <div>
                    <h3 class="text-sm font-extrabold uppercase tracking-wider text-white flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        <span>{{ __('Autenticação Google (OAuth 2.0)') }}</span>
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
                    📋 {{ __('Dados para cadastrar no Google Cloud Console (APIs & Services > Credentials)') }}:
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
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-white text-xs">
                            👁️
                        </button>
                    </div>
                    <p class="text-[11px] text-zinc-500">{{ __('Criptografado com AES-256 no banco de dados.') }}</p>
                </div>
            </div>
        </div>

        <!-- Botão Salvar Todas as Configurações -->
        <div class="flex items-center justify-between pt-6 border-t border-zinc-800">
            <span class="text-xs text-zinc-500">
                🔒 {{ __('Todas as credenciais e parâmetros fiscais são salvos de forma criptografada.') }}
            </span>
            <button type="submit"
                    class="bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-extrabold text-xs uppercase tracking-wider px-6 py-3 rounded-xl shadow-lg shadow-orange-900/20 transition-all flex items-center gap-2 cursor-pointer">
                <span>💾</span>
                <span>{{ __('Salvar Todas as Configurações') }}</span>
            </button>
        </div>
    </form>
</div>

<script>
function switchSettingTab(tabKey) {
    const tabs = ['gateways', 'fiscal', 'oauth'];

    tabs.forEach(key => {
        const btn = document.getElementById('tab-btn-' + key);
        const content = document.getElementById('tab-content-' + key);

        if (key === tabKey) {
            btn.classList.add('bg-orange-600', 'text-white', 'shadow-sm');
            btn.classList.remove('text-zinc-400', 'hover:text-white', 'hover:bg-zinc-800/60');
            content.classList.remove('hidden');
        } else {
            btn.classList.remove('bg-orange-600', 'text-white', 'shadow-sm');
            btn.classList.add('text-zinc-400', 'hover:text-white', 'hover:bg-zinc-800/60');
            content.classList.add('hidden');
        }
    });

    if (history.replaceState) {
        history.replaceState(null, null, '#' + tabKey);
    }
}

// Inicializa a aba baseada no hash da URL (ex: #fiscal ou #oauth)
window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#', '');
    if (['gateways', 'fiscal', 'oauth'].includes(hash)) {
        switchSettingTab(hash);
    }
});

function togglePasswordVisibility(fieldId) {
    const input = document.getElementById(fieldId);
    if (input.type === "password") {
        input.type = "text";
    } else {
        input.type = "password";
    }
}
</script>
@endsection
