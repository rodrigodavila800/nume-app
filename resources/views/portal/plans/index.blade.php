@extends('layouts.portal')

@section('title', __('Planos de Continuidade Audiovisual (Retainers) | NUME Portal'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
    
    <!-- Hero / Headline de Retenção & Continuidade -->
    <div class="text-center max-w-3xl mx-auto space-y-3">
        <span class="inline-flex items-center px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-100 text-orange-800 border border-orange-200">
            🔄 {{ __('Retainers & Esteira Contínua') }}
        </span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-zinc-900 tracking-tight">
            {{ __('Planos de Conteúdo com Escala & Previsibilidade') }}
        </h1>
        <p class="text-sm sm:text-base text-zinc-600 leading-relaxed">
            {{ __('Garanta presença diária e autoridade máxima no feed. Tenha uma cota renovada mensalmente de Reels, TikTok e Shorts com roteiros de alta retenção da Sofi IA e captação 4K.') }}
        </p>
    </div>

    @if (session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3.5 rounded-xl text-sm font-semibold max-w-2xl mx-auto flex items-center gap-2">
            <span>⚠️</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if (!$tenant->canReceivePayments())
        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3.5 rounded-xl text-sm font-semibold max-w-2xl mx-auto text-center">
            {{ __('Esta produtora está finalizando a configuração de novos checkouts. Em breve os planos estarão liberados para contratação imediata.') }}
        </div>
    @endif

    <!-- Banner de Benefícios dos Planos de Continuidade -->
    <div class="bg-gradient-to-r from-orange-500/10 via-amber-500/10 to-orange-500/10 border border-orange-200/80 rounded-2xl p-4 sm:p-6 text-center max-w-4xl mx-auto">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs font-semibold text-zinc-800">
            <div class="flex items-center justify-center gap-2">
                <span class="text-base text-orange-600">📈</span>
                <span>{{ __('Previsibilidade de Produção e Postagens') }}</span>
            </div>
            <div class="flex items-center justify-center gap-2">
                <span class="text-base text-orange-600">🤖</span>
                <span>{{ __('Sofi Copilot Integrado nos Roteiros') }}</span>
            </div>
            <div class="flex items-center justify-center gap-2">
                <span class="text-base text-orange-600">🔒</span>
                <span>{{ __('Contratos com Renovação de Cota a cada 30 Dias') }}</span>
            </div>
        </div>
    </div>

    <!-- GRADE DE PLANOS RECORRENTES (SUBSCRIPTIONS) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 pt-2">
        @forelse ($plans as $pack)
            @php
                $currencySymbol = strtolower($pack->currency) === 'eur' ? '€' : 'R$';
            @endphp
            <div class="bg-white rounded-2xl p-6 sm:p-8 border {{ $loop->iteration === 2 ? 'border-orange-500 shadow-xl relative ring-2 ring-orange-500/20' : 'border-zinc-200/80 shadow-sm' }} flex flex-col justify-between hover:border-orange-300 hover:shadow-lg transition group">
                
                @if($loop->iteration === 2)
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-orange-600 text-white text-[10px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-full shadow-md">
                        {{ __('Mais Recomendado') }}
                    </div>
                @endif

                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-orange-600">🔄 {{ __('Plano Recorrente') }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            {{ __('Contrato :months M', ['months' => $pack->commitment_months ?: 3]) }}
                        </span>
                    </div>

                    <div>
                        <h2 class="text-lg font-extrabold text-zinc-900 mt-0.5">{{ __($pack->name) }}</h2>
                        <p class="text-xs text-zinc-600 leading-relaxed mt-1 min-h-[3.5rem]">
                            {{ __($pack->description) }}
                        </p>
                    </div>

                    <div class="pt-2 border-t border-zinc-100">
                        <span class="text-xs text-zinc-500 font-medium">{{ __('Investimento Mensal') }}</span>
                        <div class="flex items-baseline gap-1 mt-1">
                            <span class="text-2xl sm:text-3xl font-black text-zinc-900">
                                {{ $currencySymbol }} {{ number_format($pack->price, 2, ',', '.') }}
                            </span>
                            <span class="text-xs font-semibold text-zinc-500">/ {{ $pack->billing_interval === 'quarter' ? __('trimestre') : __('mês') }}</span>
                        </div>
                        <p class="text-xs font-semibold text-orange-600 mt-1">
                            📦 {{ __('Cota inclusa:') }} {{ $pack->video_quantity }} {{ __('vídeos/mês') }}
                        </p>
                        <p class="text-[10px] text-emerald-700 font-medium mt-1">
                            🔄 {{ __('Cota renovada a cada 30 dias durante :months meses', ['months' => $pack->commitment_months ?: 3]) }}
                        </p>
                    </div>

                    <!-- Checklist de Entregáveis Estratégicos -->
                    <ul class="space-y-2 text-xs text-zinc-600 pt-3 border-t border-zinc-100">
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span>{{ __('Roteiros Persuasivos com Sofi Copilot') }}</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span>{{ __('Ganchos de Quebra de Padrão (0-3s)') }}</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span>{{ __('Edição 4K HDR & Trilha Sonora') }}</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span>{{ __('Aprovação Centralizada no Portal') }}</span>
                        </li>
                    </ul>

                    @if($pack->showcaseVideo)
                        <div class="pt-3 border-t border-zinc-100">
                            <button type="button" 
                                    onclick="openVideoModal('{{ $pack->showcaseVideo->video_url }}', '{{ addslashes($pack->showcaseVideo->title) }}', {{ $pack->showcaseVideo->is_vertical ? 'true' : 'false' }}, '{{ $pack->showcaseVideo->embed_url }}')"
                                    class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-orange-600 hover:text-white bg-orange-50 hover:bg-orange-600 border border-orange-200 transition-all shadow-2xs cursor-pointer">
                                <span>🎬</span>
                                <span>{{ __('Ver Padrão de Entrega (4K)') }}</span>
                            </button>
                        </div>
                    @endif
                </div>

                <div class="pt-6 mt-6 border-t border-zinc-100 space-y-4">
                    @php
                        $packAddons = $pack->effective_addons;
                    @endphp
                    @if(!empty($packAddons))
                        <div class="space-y-2 pt-2 border-t border-zinc-100">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-700">{{ __('Adicionais Opcionais') }}</span>
                                <span class="text-[10px] font-semibold text-orange-600 addon-count-badge">0 {{ __('selecionados') }}</span>
                            </div>
                            <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                @foreach($packAddons as $addon)
                                    @php
                                        $aName = is_array($addon) ? ($addon['name'] ?? '') : (string) $addon;
                                        $aPrice = is_array($addon) ? ($addon['price'] ?? 0) : 0;
                                    @endphp
                                    <label class="flex items-center justify-between p-2 rounded-lg bg-zinc-50 hover:bg-orange-50/50 border border-zinc-200 hover:border-orange-200 cursor-pointer text-xs transition">
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" name="selected_addons[]" value="{{ $aName }}" 
                                                   data-addon-price="{{ (float) $aPrice }}"
                                                   class="portal-addon-checkbox rounded border-zinc-300 text-orange-600 focus:ring-orange-500">
                                            <span class="text-zinc-800 font-medium">+ {{ $aName }}</span>
                                        </div>
                                        @if($aPrice > 0)
                                            <span class="font-mono font-bold text-zinc-900 text-[11px]">+€{{ number_format($aPrice, 0) }}</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @php
                        $ivaRate = (float) ($fiscalSettings['iva_rate'] ?? 21.00);
                        $irpfRate = (float) ($fiscalSettings['irpf_rate'] ?? 15.00);
                        $isAutonomo = ($fiscalSettings['regime'] ?? 'autonomo') === 'autonomo';
                    @endphp

                    <!-- Resumo Visual do Pacote no Card -->
                    <div class="portal-price-box p-3.5 rounded-xl bg-zinc-50 border border-zinc-200/80 space-y-1.5 text-xs"
                         data-base-price="{{ (float) $pack->price }}"
                         data-iva-rate="{{ $ivaRate }}"
                         data-irpf-rate="{{ $irpfRate }}"
                         data-is-autonomo="{{ $isAutonomo ? '1' : '0' }}">
                        <div class="flex items-center justify-between text-zinc-600">
                            <span>{{ __('Base Mensal:') }}</span>
                            <span class="font-mono font-medium breakdown-base-val">€ {{ number_format($pack->price, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex items-center justify-between text-zinc-600">
                            <span>{{ __('IVA') }} ({{ number_format($ivaRate, 0) }}%):</span>
                            <span class="font-mono font-medium breakdown-vat-val">+ € {{ number_format($pack->price * ($ivaRate / 100), 2, ',', '.') }}</span>
                        </div>
                        <div class="pt-1.5 border-t border-zinc-200 flex items-center justify-between text-zinc-900 font-bold">
                            <span>{{ __('Total Estimado / Mês:') }}</span>
                            <span class="font-mono text-sm total-with-vat">
                                {{ $currencySymbol }} {{ number_format($pack->price * (1 + $ivaRate / 100), 2, ',', '.') }}
                            </span>
                        </div>
                        @if($isAutonomo)
                            <p class="text-[10px] text-orange-600 font-medium pt-1 flex items-center gap-1">
                                <span>⚖️</span>
                                <span>{{ __('Dedução IRPF 15% disponível para empresas/autônomos espanhóis.') }}</span>
                            </p>
                        @endif
                    </div>

                    <!-- Aviso de Novas Assinaturas Indisponíveis Temporariamente -->
                    <div class="w-full py-3 px-4 rounded-xl text-center text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                        🔒 {{ __('Novas assinaturas temporariamente indisponíveis') }}
                    </div>
                </div>

            </div>
        @empty
            <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-dashed border-zinc-300">
                <div class="mx-auto w-12 h-12 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-xl mb-3">
                    🔄
                </div>
                <h3 class="text-base font-bold text-zinc-900">{{ __('Nenhum plano recorrente disponível no momento') }}</h3>
                <p class="mt-1 text-xs text-zinc-500 max-w-sm mx-auto">
                    {{ __('Consulte nossas produções avulsas ou entre em contato com a equipe para formatar um retainer sob medida.') }}
                </p>
                <a href="{{ route('portal.packs.index') }}"
                   class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-zinc-900 hover:bg-zinc-800 text-white transition">
                    <span>📦 {{ __('Ver Pacotes Avulsos') }}</span>
                </a>
            </div>
        @endforelse
    </div>
</div>

<!-- Modal Universal de Vídeo 4K (100% Tailwind, zero CSS inline) -->
<div id="portal-video-modal" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="relative w-full max-w-2xl bg-zinc-900 border border-zinc-800 rounded-2xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-zinc-800 bg-zinc-950/70">
            <div class="flex items-center gap-2">
                <span id="portal-modal-format" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-500/20 text-orange-400 border border-orange-500/30">📱 9:16</span>
                <h3 id="portal-modal-title" class="text-sm font-bold text-white truncate max-w-md"></h3>
            </div>
            <button type="button" onclick="closePortalVideoModal()" class="w-8 h-8 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white flex items-center justify-center transition text-sm cursor-pointer" aria-label="Fechar">✕</button>
        </div>
        <div class="relative w-full aspect-video bg-black flex items-center justify-center">
            <iframe id="portal-modal-iframe" class="w-full h-full border-0" src="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL DE CHECKOUT ESPAÇOSO (MAX-W-5XL) COM STRIPE EMBEDDED INTEGRADO      -->
<!-- ========================================================================= -->
<script src="https://js.stripe.com/v3/"></script>

<div id="portal-checkout-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-3 sm:p-6 overflow-y-auto hidden">
    <div class="relative w-full max-w-4xl lg:max-w-5xl bg-white rounded-3xl border border-zinc-200 shadow-2xl overflow-hidden my-4 sm:my-8 transition-all flex flex-col max-h-[94vh]">
        
        <!-- Header Corporativo da NUME -->
        <div class="px-6 sm:px-8 py-5 bg-zinc-950 text-white flex items-center justify-between border-b border-zinc-800 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-600 flex items-center justify-center text-white font-black text-base shadow-md shadow-orange-950/50">
                    N
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span id="modal-pack-type-badge" class="text-[10px] font-extrabold uppercase tracking-widest text-orange-400">{{ __('Contratação Oficial de Produção') }}</span>
                        <span id="modal-pack-commitment-badge" class="hidden px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-950 text-emerald-300 border border-emerald-800">🔄 {{ __('Contrato Recorrente') }}</span>
                        <span class="hidden sm:inline-block px-2 py-0.5 rounded-full text-[9px] font-bold bg-zinc-800 text-zinc-300 border border-zinc-700">🔒 {{ __('SSL 256-Bit') }}</span>
                    </div>
                    <h3 id="modal-pack-title" class="text-base sm:text-lg font-black text-white mt-0.5"></h3>
                    <p id="modal-pack-subtitle-hint" class="text-xs text-zinc-400 font-medium"></p>
                </div>
            </div>
            <button type="button" onclick="closePortalCheckoutModal()"
                    class="w-9 h-9 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white flex items-center justify-center text-sm transition cursor-pointer"
                    aria-label="{{ __('Fechar') }}">
                ✕
            </button>
        </div>

        <!-- Abas do Modal -->
        <div class="flex border-b border-zinc-200 bg-zinc-50/80 px-6 sm:px-8 py-2.5 gap-2 shrink-0">
            <button type="button" id="portal-tab-btn-fiscal" onclick="switchPortalModalTab('fiscal')"
                    class="flex-1 py-2.5 px-4 rounded-xl text-xs font-extrabold transition-all flex items-center justify-center gap-2 bg-orange-600 text-white shadow-sm cursor-pointer">
                <span>1.</span>
                <span>{{ __('Dados Fiscais & Resumo') }}</span>
            </button>
            <button type="button" id="portal-tab-btn-payment" onclick="switchPortalModalTab('payment')"
                    class="flex-1 py-2.5 px-4 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 text-zinc-600 hover:text-zinc-900 cursor-pointer">
                <span>2.</span>
                <span>{{ __('Método de Pagamento') }}</span>
            </button>
            <button type="button" id="portal-tab-btn-embedded" onclick="switchPortalModalTab('embedded')"
                    class="hidden flex-1 py-2.5 px-4 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 text-zinc-600 hover:text-zinc-900 cursor-pointer">
                <span>3.</span>
                <span>{{ __('Checkout Seguro Integrado') }}</span>
            </button>
        </div>

        <!-- Conteúdo Rolável do Modal -->
        <div class="overflow-y-auto flex-1 p-6 sm:p-8">
            <form id="portal-modal-checkout-form" method="POST" action="">
                @csrf
                <div id="modal-hidden-addons-container"></div>

                <!-- ============================================== -->
                <!-- ABA 1: DADOS FISCAIS & RESUMO TÉCNICO          -->
                <!-- ============================================== -->
                <div id="portal-tab-content-fiscal" class="space-y-6">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                        
                        <!-- Coluna Esquerda: Formulário de Faturamento -->
                        <div class="lg:col-span-7 space-y-4">
                            <div class="border-b border-zinc-100 pb-2">
                                <h4 class="text-sm font-bold text-zinc-900 flex items-center gap-2">
                                    <span>🏢</span>
                                    <span>{{ __('Identificação Fiscal para Factura Oficial (AEAT / Receita)') }}</span>
                                </h4>
                                <p class="text-xs text-zinc-500 mt-0.5">
                                    {{ __('Dados necessários para a emissão correta da fatura com desglose de IVA/IRPF.') }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 mb-1">{{ __('Razão Social / Nome Completo') }} *</label>
                                    <input type="text" name="customer_name" id="modal-cust-name" required
                                           value="{{ auth('cliente')->user()?->name }}"
                                           placeholder="Ex: Minha Empresa S.L."
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-zinc-200 text-xs text-zinc-900 focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 mb-1">{{ __('NIF / CIF / NIF-IVA / CNPJ') }} *</label>
                                    <input type="text" name="tax_id" id="modal-cust-tax-id" required
                                           value="{{ auth('cliente')->user()?->tax_id ?? '' }}"
                                           placeholder="Ex: B50987654 ou 00.000.000/0001-00"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-zinc-200 text-xs text-zinc-900 uppercase focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 mb-1">{{ __('E-mail de Notificação') }} *</label>
                                    <input type="email" name="customer_email" id="modal-cust-email" required
                                           value="{{ auth('cliente')->user()?->email }}"
                                           placeholder="financeiro@empresa.com"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-zinc-200 text-xs text-zinc-900 focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 mb-1">{{ __('País de Residência Fiscal') }} *</label>
                                    <select name="tax_country" id="modal-cust-country" onchange="recalculateModalFiscal()"
                                            class="w-full px-3.5 py-2.5 rounded-xl border border-zinc-200 text-xs text-zinc-900 focus:outline-none focus:ring-2 focus:ring-orange-500">
                                        <option value="ES" selected>🇪🇸 España (IVA 21% / IRPF)</option>
                                        <option value="BR">🇧🇷 Brasil (B2B)</option>
                                        <option value="FR">🇫🇷 France (EU VAT)</option>
                                        <option value="DE">🇩🇪 Deutschland (EU VAT)</option>
                                        <option value="PT">🇵🇹 Portugal (EU VAT)</option>
                                        <option value="OTHER">🌐 Outro País / Exportação</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-zinc-700 mb-1">{{ __('Endereço Completo') }}</label>
                                    <input type="text" name="customer_address" id="modal-cust-address"
                                           placeholder="Calle Mayor 12, Planta 2"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-zinc-200 text-xs text-zinc-900 focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 mb-1">{{ __('Código Postal') }}</label>
                                    <input type="text" name="customer_postal_code" id="modal-cust-zip"
                                           placeholder="50001"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-zinc-200 text-xs text-zinc-900 focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                            </div>

                            <!-- Seleção de Tipo de Contratante Espanhol para IRPF -->
                            <div class="p-3 rounded-xl bg-orange-50/60 border border-orange-200/80 space-y-2">
                                <span class="text-xs font-bold text-orange-950 flex items-center gap-1.5">
                                    <span>⚖️</span>
                                    <span>{{ __('Natureza da Entidade (Regra Fiscal Espanhola)') }}</span>
                                </span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                    <label class="flex items-center gap-2 p-2 rounded-lg bg-white border border-orange-200 cursor-pointer hover:bg-orange-50 transition">
                                        <input type="radio" name="is_spanish_company" value="1" checked onchange="recalculateModalFiscal()" class="text-orange-600 focus:ring-orange-500">
                                        <span class="text-zinc-800 font-semibold">{{ __('Empresa (SL/SA) ou Autônomo') }}</span>
                                    </label>
                                    <label class="flex items-center gap-2 p-2 rounded-lg bg-white border border-orange-200 cursor-pointer hover:bg-orange-50 transition">
                                        <input type="radio" name="is_spanish_company" value="0" onchange="recalculateModalFiscal()" class="text-orange-600 focus:ring-orange-500">
                                        <span class="text-zinc-800 font-semibold">{{ __('Pessoa Física / Particular') }}</span>
                                    </label>
                                </div>
                                <p class="text-[11px] text-orange-800 leading-tight">
                                    {{ __('Se contratado por empresa/autônomo espanhol e a produtora for autônoma, aplica-se a retenção legal de 15% de IRPF na fatura.') }}
                                </p>
                            </div>
                        </div>

                        <!-- Coluna Direita: Box Resumo Fiscal e Totalizador Líquido -->
                        <div class="lg:col-span-5 flex flex-col justify-between space-y-4">
                            <div class="bg-zinc-50 border border-zinc-200 rounded-2xl p-5 space-y-3.5">
                                <div class="flex items-center justify-between pb-2 border-b border-zinc-200">
                                    <span class="text-xs font-bold text-zinc-900 uppercase tracking-wider">{{ __('Desglose da Contratação') }}</span>
                                    <span id="modal-summary-billing-badge" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        {{ __('Assinatura Recorrente') }}
                                    </span>
                                </div>

                                <div class="space-y-2 text-xs text-zinc-600">
                                    <div class="flex justify-between items-center">
                                        <span id="modal-summary-base-label">{{ __('Base Imponível (por mês):') }}</span>
                                        <span id="modal-summary-base-val" class="font-mono font-bold text-zinc-900">€ 0,00</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span>{{ __('Adicionais (Add-ons):') }}</span>
                                        <span id="modal-summary-addons-val" class="font-mono font-medium text-zinc-800">+ € 0,00</span>
                                    </div>
                                    <div class="flex justify-between items-center text-zinc-500">
                                        <span>{{ __('Subtotal Tributável:') }}</span>
                                        <span id="modal-summary-subtotal-val" class="font-mono font-medium">€ 0,00</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span id="modal-summary-iva-label">{{ __('IVA Espanhol (21%):') }}</span>
                                        <span id="modal-summary-iva-val" class="font-mono font-bold text-emerald-700">+ € 0,00</span>
                                    </div>
                                    <div id="modal-summary-irpf-row" class="flex justify-between items-center text-orange-700 hidden">
                                        <span id="modal-summary-irpf-label">{{ __('Retenção IRPF (15%):') }}</span>
                                        <span id="modal-summary-irpf-val" class="font-mono font-bold">- € 0,00</span>
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-zinc-200 flex justify-between items-baseline">
                                    <span id="modal-summary-total-label" class="text-sm font-extrabold text-zinc-900">{{ __('Total Líquido (por mês):') }}</span>
                                    <span id="modal-summary-total" class="text-2xl font-black text-orange-600 font-mono">€ 0,00</span>
                                </div>

                                <div id="modal-summary-commitment-note" class="p-2.5 rounded-xl bg-emerald-50/80 border border-emerald-200 text-[11px] text-emerald-800 space-y-1">
                                    <div class="font-bold flex items-center gap-1">
                                        <span>🔄</span>
                                        <span>{{ __('Compromisso Contratual') }}</span>
                                    </div>
                                    <p id="modal-summary-commitment-text" class="leading-relaxed">
                                        {{ __('Assinatura recorrente com renovação periódica de cota a cada 30 dias.') }}
                                    </p>
                                </div>

                                <div class="text-[11px] text-zinc-400 space-y-1 pt-1">
                                    <p>🛡️ {{ __('A fatura em PDF será gerada com os dados acima.') }}</p>
                                    <p>💳 {{ __('Você poderá cancelar ou pausar após o período mínimo.') }}</p>
                                </div>
                            </div>

                            <button type="button" onclick="switchPortalModalTab('payment')"
                                    class="w-full py-3.5 px-4 rounded-xl text-xs font-extrabold tracking-wider uppercase bg-orange-600 hover:bg-orange-500 text-white shadow-lg shadow-orange-900/20 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer">
                                <span>{{ __('Avançar para Pagamento') }}</span>
                                <span>→</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- ABA 2: ESCOLHA DO MÉTODO DE PAGAMENTO          -->
                <!-- ============================================== -->
                <div id="portal-tab-content-payment" class="space-y-6 hidden">
                    <div class="max-w-2xl mx-auto space-y-5">
                        
                        <div class="text-center space-y-1">
                            <h4 class="text-base font-black text-zinc-900">{{ __('Selecione a Forma de Pagamento') }}</h4>
                            <p class="portal-payment-hint text-xs text-zinc-500">{{ __('Transação protegida por criptografia bancária de ponta a ponta.') }}</p>
                        </div>

                        <!-- Opções de Pagamento Ativas -->
                        <div class="payment-method-selector grid grid-cols-1 gap-3">
                            
                            <!-- Opção 1: Stripe (Cartão Internacional / Apple Pay / Google Pay) -->
                            @if(!empty($activePaymentMethods['stripe']))
                                <label class="payment-method-card flex items-center justify-between p-4 rounded-2xl border-2 border-zinc-200 hover:border-orange-500 hover:bg-orange-50/20 cursor-pointer transition group">
                                    <div class="flex items-center gap-3.5">
                                        <input type="radio" name="payment_method" value="stripe" checked onchange="updateModalPaymentUI('stripe')"
                                               class="payment-method-radio w-4 h-4 text-orange-600 focus:ring-orange-500">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-extrabold text-zinc-900">{{ __('Cartão de Crédito / Débito (Stripe)') }}</span>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700">{{ __('Checkout Integrado') }}</span>
                                            </div>
                                            <p class="text-xs text-zinc-500 mt-0.5">{{ __('Visa, Mastercard, American Express, Apple Pay e Google Pay.') }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 opacity-80 group-hover:opacity-100 transition">
                                        <span class="px-2 py-1 bg-zinc-100 rounded text-[10px] font-black tracking-wider text-zinc-600">STRIPE</span>
                                    </div>
                                </label>
                            @endif

                            <!-- Opção 2: Bizum (BBVA Redsys Espanha) -->
                            @if(!empty($activePaymentMethods['bizum']))
                                <label class="payment-method-card flex items-center justify-between p-4 rounded-2xl border-2 border-zinc-200 hover:border-orange-500 hover:bg-orange-50/20 cursor-pointer transition group">
                                    <div class="flex items-center gap-3.5">
                                        <input type="radio" name="payment_method" value="bizum" {{ empty($activePaymentMethods['stripe']) ? 'checked' : '' }} onchange="updateModalPaymentUI('bizum')"
                                               class="payment-method-radio w-4 h-4 text-orange-600 focus:ring-orange-500">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-extrabold text-zinc-900">Bizum (BBVA Redsys)</span>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700">Instantâneo</span>
                                            </div>
                                            <p class="text-xs text-zinc-500 mt-0.5">{{ __('Pagamento móvel direto em Espanha através de número de telefone.') }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 opacity-80 group-hover:opacity-100 transition">
                                        <span class="px-2 py-1 bg-sky-50 text-sky-800 border border-sky-200 rounded text-[10px] font-black">BIZUM</span>
                                    </div>
                                </label>
                            @endif

                            <!-- Opção 3: Tarjeta Redsys BBVA -->
                            @if(!empty($activePaymentMethods['redsys_card']))
                                <label class="payment-method-card flex items-center justify-between p-4 rounded-2xl border-2 border-zinc-200 hover:border-orange-500 hover:bg-orange-50/20 cursor-pointer transition group">
                                    <div class="flex items-center gap-3.5">
                                        <input type="radio" name="payment_method" value="redsys_card" {{ (empty($activePaymentMethods['stripe']) && empty($activePaymentMethods['bizum'])) ? 'checked' : '' }} onchange="updateModalPaymentUI('redsys_card')"
                                               class="payment-method-radio w-4 h-4 text-orange-600 focus:ring-orange-500">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-extrabold text-zinc-900">{{ __('Tarjeta Bancaria (TPV BBVA Redsys)') }}</span>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700">3D Secure</span>
                                            </div>
                                            <p class="text-xs text-zinc-500 mt-0.5">{{ __('Ambiente bancário oficial BBVA com autenticação de dois fatores no telemóvel.') }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 opacity-80 group-hover:opacity-100 transition">
                                        <span class="px-2 py-1 bg-blue-50 text-blue-800 border border-blue-200 rounded text-[10px] font-black">REDSYS</span>
                                    </div>
                                </label>
                            @endif
                        </div>

                        <!-- Botão de Ação do Pagamento -->
                        <div class="pt-4 flex items-center justify-between gap-4">
                            <button type="button" onclick="switchPortalModalTab('fiscal')"
                                    class="py-3 px-5 rounded-xl text-xs font-bold text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 transition cursor-pointer">
                                ← {{ __('Voltar aos Dados Fiscais') }}
                            </button>

                            <button type="submit" id="portal-modal-submit-btn"
                                    class="portal-checkout-submit-btn flex-1 py-3.5 px-6 rounded-xl text-xs font-extrabold uppercase tracking-wider bg-orange-600 hover:bg-orange-500 text-white shadow-lg shadow-orange-900/20 active:scale-95 transition flex items-center justify-center gap-2 cursor-pointer">
                                <span>🔒</span>
                                <span id="portal-modal-submit-label">{{ __('Carregar Checkout Seguro') }}</span>
                                <span>→</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- ABA 3: STRIPE EMBEDDED INTEGRADO               -->
                <!-- ============================================== -->
                <div id="portal-tab-content-embedded" class="space-y-4 hidden">
                    <div class="max-w-2xl mx-auto">
                        <div class="flex items-center justify-between mb-3">
                            <button type="button" onclick="switchPortalModalTab('payment')" class="text-xs font-bold text-zinc-500 hover:text-zinc-800 flex items-center gap-1">
                                ← {{ __('Trocar Método') }}
                            </button>
                            <span class="text-xs text-emerald-600 font-bold flex items-center gap-1">
                                <span>🔒</span>
                                <span>Stripe Embedded Checkout (256-Bit SSL)</span>
                            </span>
                        </div>

                        <!-- Indicador de Carregamento -->
                        <div id="stripe-loading-indicator" class="py-12 text-center space-y-3">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-orange-500 border-t-transparent"></div>
                            <p class="text-xs font-semibold text-zinc-600">{{ __('Carregando o formulário oficial da Stripe...') }}</p>
                        </div>

                        <!-- Container Oficial do Stripe Elements / Embedded -->
                        <div id="stripe-embedded-checkout-mount" class="w-full min-h-[380px] bg-white rounded-2xl"></div>
                    </div>
                </div>

            </form>
        </div>

    </div>
</div>

<script>
    // Configurações fiscais padrão do tenant
    const fiscalSettings = {
        ivaRate: {{ (float) ($fiscalSettings['iva_rate'] ?? 21.00) }},
        irpfRate: {{ (float) ($fiscalSettings['irpf_rate'] ?? 15.00) }},
        regime: '{{ $fiscalSettings['regime'] ?? 'autonomo' }}'
    };

    // Estado do pacote atual no modal
    let currentModalPack = {
        id: null,
        name: '',
        basePrice: 0,
        currencySymbol: '€',
        checkoutUrl: '',
        addonsTotal: 0,
        selectedAddons: [],
        billingType: 'subscription',
        commitmentMonths: 3,
        billingInterval: 'month'
    };

    let stripeEmbeddedInstance = null;

    // Atualização de Add-ons nos cards
    document.querySelectorAll('.portal-addon-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const card = this.closest('.group');
            if (!card) return;

            const priceBox = card.querySelector('.portal-price-box');
            if (!priceBox) return;

            const basePrice = parseFloat(priceBox.dataset.basePrice || 0);
            const ivaRate = parseFloat(priceBox.dataset.ivaRate || 21);
            
            let addonsSum = 0;
            let checkedCount = 0;
            card.querySelectorAll('.portal-addon-checkbox:checked').forEach(c => {
                addonsSum += parseFloat(c.dataset.addonPrice || 0);
                checkedCount++;
            });

            // Atualiza badge de contagem
            const badge = card.querySelector('.addon-count-badge');
            if (badge) {
                badge.textContent = `${checkedCount} {{ __("selecionados") }}`;
            }

            // Atualiza resumo no card
            const subtotal = basePrice + addonsSum;
            const vatVal = subtotal * (ivaRate / 100);
            const totalWithVat = subtotal + vatVal;

            const baseEl = priceBox.querySelector('.breakdown-base-val');
            const vatEl = priceBox.querySelector('.breakdown-vat-val');
            const totalEl = priceBox.querySelector('.total-with-vat');

            if (baseEl) baseEl.textContent = `€ ${subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            if (vatEl) vatEl.textContent = `+ € ${vatVal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            if (totalEl) totalEl.textContent = `€ ${totalWithVat.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        });
    });

    // Abertura do Modal de Checkout Espaçoso
    function openPortalCheckoutModal(packId, packName, basePrice, checkoutUrl, currencySymbol, btnEl, billingType = 'subscription', commitmentMonths = 3, billingInterval = 'month') {
        currentModalPack.id = packId;
        currentModalPack.name = packName;
        currentModalPack.basePrice = parseFloat(basePrice);
        currentModalPack.checkoutUrl = checkoutUrl;
        currentModalPack.currencySymbol = currencySymbol || '€';
        currentModalPack.billingType = billingType;
        currentModalPack.commitmentMonths = commitmentMonths;
        currentModalPack.billingInterval = billingInterval;

        const intervalLabel = (billingInterval === 'quarter') ? '{{ __("trimestre") }}' : '{{ __("mês") }}';

        const typeBadge = document.getElementById('modal-pack-type-badge');
        const commitmentBadge = document.getElementById('modal-pack-commitment-badge');
        const subtitleHint = document.getElementById('modal-pack-subtitle-hint');
        const summaryBadge = document.getElementById('modal-summary-billing-badge');
        const commitmentNote = document.getElementById('modal-summary-commitment-note');
        const commitmentText = document.getElementById('modal-summary-commitment-text');
        const baseLabel = document.getElementById('modal-summary-base-label');
        const totalLabel = document.getElementById('modal-summary-total-label');

        if (typeBadge) typeBadge.textContent = '{{ __("CONTRATO DE ASSINATURA AUDIOVISUAL") }}';
        if (commitmentBadge) {
            commitmentBadge.classList.remove('hidden');
            commitmentBadge.textContent = `🔄 {{ __("Contrato") }} ${commitmentMonths} {{ __("Meses") }}`;
        }
        if (subtitleHint) subtitleHint.textContent = `{{ __("Cobrança recorrente de") }} ${currentModalPack.currencySymbol} ${basePrice.toFixed(2)} / ${intervalLabel} {{ __("durante") }} ${commitmentMonths} {{ __("meses com renovação automática de cota.") }}`;
        if (summaryBadge) {
            summaryBadge.textContent = '{{ __("Assinatura Recorrente") }}';
            summaryBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200';
        }
        if (commitmentNote) commitmentNote.classList.remove('hidden');
        if (commitmentText) commitmentText.textContent = `{{ __("Assinatura recorrente com renovação periódica de cota a cada 30 dias durante") }} ${commitmentMonths} {{ __("meses.") }}`;
        if (baseLabel) baseLabel.textContent = `{{ __("Base Imponível (por") }} ${intervalLabel}):`;
        if (totalLabel) totalLabel.textContent = `{{ __("Total Líquido (por") }} ${intervalLabel}):`;

        // Destrói instância anterior do Stripe se houver
        if (stripeEmbeddedInstance) {
            try { stripeEmbeddedInstance.destroy(); } catch(e){}
            stripeEmbeddedInstance = null;
        }

        // Coleta os add-ons selecionados no card onde o botão foi clicado
        const card = btnEl.closest('.group');
        const checkedAddons = card.querySelectorAll('.portal-addon-checkbox:checked');
        
        currentModalPack.addonsTotal = 0;
        currentModalPack.selectedAddons = [];

        const hiddenContainer = document.getElementById('modal-hidden-addons-container');
        hiddenContainer.innerHTML = '';

        checkedAddons.forEach(cb => {
            const price = parseFloat(cb.dataset.addonPrice || 0);
            currentModalPack.addonsTotal += price;
            currentModalPack.selectedAddons.push({
                name: cb.value,
                price: price
            });

            // Cria input hidden para submeter no checkout do modal
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'selected_addons[]';
            hiddenInput.value = cb.value;
            hiddenContainer.appendChild(hiddenInput);
        });

        // Configura form action
        const form = document.getElementById('portal-modal-checkout-form');
        form.action = checkoutUrl;

        // Atualiza cabeçalho do modal
        document.getElementById('modal-pack-title').textContent = packName;

        // Recalcula valores do modal
        recalculateModalFiscal();

        // Abre na Aba Fiscal inicialmente
        switchPortalModalTab('fiscal');

        // Exibe o modal
        const modal = document.getElementById('portal-checkout-modal');
        modal.classList.remove('hidden');
    }

    function closePortalCheckoutModal() {
        const modal = document.getElementById('portal-checkout-modal');
        modal.classList.add('hidden');
    }

    // Fechar ao clicar fora da caixa do modal
    document.getElementById('portal-checkout-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closePortalCheckoutModal();
        }
    });

    // Alternador de Abas do Modal
    function switchPortalModalTab(tabKey) {
        const btnFiscal = document.getElementById('portal-tab-btn-fiscal');
        const btnPayment = document.getElementById('portal-tab-btn-payment');
        const btnEmbedded = document.getElementById('portal-tab-btn-embedded');
        const contentFiscal = document.getElementById('portal-tab-content-fiscal');
        const contentPayment = document.getElementById('portal-tab-content-payment');
        const contentEmbedded = document.getElementById('portal-tab-content-embedded');

        // Se tentar mudar para Pagamento ou Checkout sem preencher a identificação fiscal, redireciona e foca no campo
        if (tabKey === 'payment' || tabKey === 'embedded') {
            const nameEl = document.getElementById('modal-cust-name');
            const taxEl = document.getElementById('modal-cust-tax-id');
            const emailEl = document.getElementById('modal-cust-email');

            if (nameEl && !nameEl.checkValidity()) {
                tabKey = 'fiscal';
                contentFiscal.classList.remove('hidden');
                nameEl.reportValidity();
                nameEl.focus();
                return;
            }
            if (taxEl && !taxEl.checkValidity()) {
                tabKey = 'fiscal';
                contentFiscal.classList.remove('hidden');
                taxEl.reportValidity();
                taxEl.focus();
                return;
            }
            if (emailEl && !emailEl.checkValidity()) {
                tabKey = 'fiscal';
                contentFiscal.classList.remove('hidden');
                emailEl.reportValidity();
                emailEl.focus();
                return;
            }
        }

        // Reseta classes básicas dos botões
        [btnFiscal, btnPayment, btnEmbedded].forEach(btn => {
            if (btn) btn.className = "flex-1 py-2.5 px-4 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 text-zinc-600 hover:text-zinc-900 cursor-pointer";
        });

        contentFiscal.classList.add('hidden');
        contentPayment.classList.add('hidden');
        contentEmbedded.classList.add('hidden');

        if (tabKey === 'fiscal') {
            btnFiscal.className = "flex-1 py-2.5 px-4 rounded-xl text-xs font-extrabold transition-all flex items-center justify-center gap-2 bg-orange-600 text-white shadow-sm cursor-pointer";
            contentFiscal.classList.remove('hidden');
        } else if (tabKey === 'payment') {
            btnPayment.className = "flex-1 py-2.5 px-4 rounded-xl text-xs font-extrabold transition-all flex items-center justify-center gap-2 bg-orange-600 text-white shadow-sm cursor-pointer";
            contentPayment.classList.remove('hidden');
        } else if (tabKey === 'embedded') {
            btnEmbedded.classList.remove('hidden');
            btnEmbedded.className = "flex-1 py-2.5 px-4 rounded-xl text-xs font-extrabold transition-all flex items-center justify-center gap-2 bg-indigo-600 text-white shadow-sm cursor-pointer";
            contentEmbedded.classList.remove('hidden');
        }
    }

    // Recálculo do Resumo Fiscal do Modal
    function recalculateModalFiscal() {
        const country = document.getElementById('modal-cust-country')?.value || 'ES';
        const isSpanishCompany = document.querySelector('input[name="is_spanish_company"]:checked')?.value === '1';

        const base = currentModalPack.basePrice;
        const addons = currentModalPack.addonsTotal;
        const subtotal = base + addons;

        let appliedIvaRate = 0;
        let appliedIrpfRate = 0;

        // Regras Fiscais:
        // Espanha: IVA 21% sempre.
        // Se Autônomo e cliente for Empresa/Autônomo Espanhol -> Retenção de 15% de IRPF.
        if (country === 'ES') {
            appliedIvaRate = fiscalSettings.ivaRate;
            if (fiscalSettings.regime === 'autonomo' && isSpanishCompany) {
                appliedIrpfRate = fiscalSettings.irpfRate;
            }
        } else if (country === 'BR' || country === 'OTHER') {
            // Exportação de serviços para fora da UE -> Isento de IVA (Inversión del sujeto pasivo / Exportación)
            appliedIvaRate = 0;
            appliedIrpfRate = 0;
        } else {
            // Outros países da UE -> Isento se B2B com VIES (regra padrão de IVA comunitário)
            appliedIvaRate = 0;
            appliedIrpfRate = 0;
        }

        const ivaAmount = subtotal * (appliedIvaRate / 100);
        const irpfAmount = subtotal * (appliedIrpfRate / 100);
        const totalNet = subtotal + ivaAmount - irpfAmount;

        // Atualiza campos da UI
        document.getElementById('modal-summary-base-val').textContent = `€ ${base.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('modal-summary-addons-val').textContent = `+ € ${addons.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('modal-summary-subtotal-val').textContent = `€ ${subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        
        const ivaRowLabel = document.getElementById('modal-summary-iva-label');
        if (ivaRowLabel) {
            ivaRowLabel.textContent = appliedIvaRate > 0 ? `{{ __('IVA Espanhol') }} (${appliedIvaRate}%):` : `{{ __('IVA / VAT Isento:') }}`;
        }
        document.getElementById('modal-summary-iva-val').textContent = `+ € ${ivaAmount.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

        const irpfRow = document.getElementById('modal-summary-irpf-row');
        if (appliedIrpfRate > 0) {
            irpfRow.classList.remove('hidden');
            document.getElementById('modal-summary-irpf-val').textContent = `- € ${irpfAmount.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        } else {
            irpfRow.classList.add('hidden');
        }

        const totalFormatted = `€ ${totalNet.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('modal-summary-total').textContent = totalFormatted;

        // Atualiza botão de pagamento
        const activeMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'stripe';
        updateModalPaymentUI(activeMethod, totalFormatted);
    }

    function updateModalPaymentUI(method, totalFormatted) {
        const total = totalFormatted || document.getElementById('modal-summary-total')?.textContent || '€ 0,00';
        const submitLabel = document.getElementById('portal-modal-submit-label');
        if (!submitLabel) return;

        if (method === 'stripe') {
            submitLabel.textContent = `{{ __('Pagar') }} ${total} {{ __('via Stripe Seguro') }}`;
        } else if (method === 'bizum') {
            submitLabel.textContent = `{{ __('Pagar') }} ${total} {{ __('com Bizum BBVA') }}`;
        } else if (method === 'redsys_card') {
            submitLabel.textContent = `{{ __('Conectar com Tarjeta BBVA Redsys') }} (${total})`;
        }
    }

    // Submissão do Formulário do Modal de Checkout
    document.getElementById('portal-modal-checkout-form')?.addEventListener('submit', async function(e) {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'stripe';
        const submitBtn = document.getElementById('portal-modal-submit-btn');

        if (selectedMethod === 'stripe') {
            e.preventDefault(); // Impede POST comum para montar o iframe integrado via AJAX
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                submitBtn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ __("Iniciando Stripe Checkout...") }}</span>
                `;
            }

            try {
                const form = this;
                const formData = new FormData(form);
                formData.append('embedded', '1');

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success' && data.client_secret && data.publishable_key) {
                    // Alterna para a Aba 3 (Stripe Integrado)
                    switchPortalModalTab('embedded');
                    document.getElementById('stripe-loading-indicator').classList.remove('hidden');

                    const stripe = Stripe(data.publishable_key);
                    const mountContainer = document.getElementById('stripe-embedded-checkout-mount');
                    mountContainer.innerHTML = '';

                    stripeEmbeddedInstance = await stripe.initEmbeddedCheckout({
                        clientSecret: data.client_secret,
                    });

                    stripeEmbeddedInstance.mount('#stripe-embedded-checkout-mount');
                    document.getElementById('stripe-loading-indicator').classList.add('hidden');
                } else if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    alert(data.message || 'Erro ao carregar o checkout seguro da Stripe.');
                }
            } catch (err) {
                console.error(err);
                alert('Ocorreu uma falha ao conectar com o gateway Stripe. Tente novamente.');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                    const totalFormatted = document.getElementById('modal-summary-total')?.textContent || '€ 0,00';
                    updateModalPaymentUI('stripe', totalFormatted);
                }
            }
        } else {
            // Bizum ou Tarjeta BBVA: submissão POST padrão
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                submitBtn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ __("Conectando com o ambiente seguro...") }}</span>
                `;
            }
        }
    });

    // Funções do Modal de Vídeo Adaptativo
    function openVideoModal(url, title, isVertical, explicitEmbedUrl) {
        const modal = document.getElementById('portal-video-modal');
        const iframe = document.getElementById('portal-modal-iframe');
        const titleEl = document.getElementById('portal-modal-title');
        const formatBadge = document.getElementById('portal-modal-format');

        let embedUrl = (explicitEmbedUrl && explicitEmbedUrl.trim()) ? explicitEmbedUrl : url;

        const ytMatch = embedUrl.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i);
        if (ytMatch && ytMatch[1]) {
            embedUrl = 'https://www.youtube-nocookie.com/embed/' + ytMatch[1] + '?autoplay=1&playsinline=1&rel=0&enablejsapi=1';
        }

        iframe.src = embedUrl;
        titleEl.textContent = title || 'Vídeo de Exemplo';
        formatBadge.textContent = isVertical ? '📱 9:16' : '🖥️ 16:9';

        modal.classList.remove('hidden');
    }

    function closePortalVideoModal() {
        const modal = document.getElementById('portal-video-modal');
        const iframe = document.getElementById('portal-modal-iframe');
        iframe.src = '';
        modal.classList.add('hidden');
    }

    document.getElementById('portal-video-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closePortalVideoModal();
        }
    });
</script>
@endsection
