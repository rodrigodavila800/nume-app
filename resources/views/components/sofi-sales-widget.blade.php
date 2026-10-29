@php
    $isEs = str_starts_with(session('locale', app()->getLocale() ?: 'pt_BR'), 'es');
    $greeting = $isEs 
        ? '¡Hola! Soy Sofi, consultora audiovisual de ' . $tenant->name . '. ¿Qué objetivo deseas alcanzar con tus vídeos este mes?' 
        : 'Olá! Sou a Sofi, consultora audiovisual da ' . $tenant->name . '. Qual objetivo você quer acelerar com seus vídeos este mês?';
    $bubbleText = $isEs 
        ? '¿Qué tipo de vídeo aumentará las ventas de tu negocio?' 
        : 'Qual formato de vídeo vai atrair mais clientes para a sua empresa?';
    $tenantWa = isset($tenant) ? ($tenant->social_whatsapp ?? null) : null;
    $waPhone = preg_replace('/\D/', '', $tenantWa ?: config('services.whatsapp.contact_number', '34664274151'));
    $waText = urlencode(__('Olá Sofi! Gostaria de saber mais sobre a produção de vídeos da NUME.'));
    $waUrl = "https://wa.me/{$waPhone}?text={$waText}";
@endphp

<!-- Sofi Sales Copilot Floating Widget -->
<div id="sofiSalesWidget" class="sofi-sales-widget">

    <!-- Micro-balão persuasivo de abertura (teaser) -->
    <div id="sofiTeaserBubble" class="sofi-teaser-bubble" onclick="toggleSofiChat(true)">
        <div class="sofi-teaser-content">
            <div class="sofi-teaser-icon">
                
            </div>
            <div>
                <div class="sofi-teaser-header">
                    <span class="sofi-teaser-name">Sofi</span>
                    <span class="sofi-status-pulse"></span>
                    <span class="sofi-teaser-status-label">{{ __('online') }}</span>
                </div>
                <p class="sofi-teaser-text">
                    {{ $bubbleText }}
                </p>
            </div>
            <button type="button" onclick="event.stopPropagation(); dismissSofiTeaser();" class="sofi-teaser-close">
                ✕
            </button>
        </div>
    </div>

    <!-- Janela de Chat Expansível (Prism Flux Glassmorphism) -->
    <div id="sofiChatWindow" class="sofi-chat-window hidden">
        
        <!-- Header da Janela -->
        <div class="sofi-chat-header">
            <div class="sofi-chat-profile">
                <div class="sofi-avatar-wrapper">
                    <div class="sofi-avatar-inner">
                        
                    </div>
                    <span class="sofi-avatar-dot"></span>
                </div>
                <div>
                    <h3 class="sofi-profile-title">
                        <span>Sofi Sales Copilot</span>
                        <span class="sofi-badge-ia">IA</span>
                    </h3>
                    <p class="sofi-profile-sub">
                        {{ $isEs ? 'Estratega Audiovisual · ' . $tenant->name : 'Estrategista Audiovisual · ' . $tenant->name }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" onclick="resetSofiChatSession()" class="sofi-action-header-btn" title="{{ $isEs ? 'Reiniciar consulta y elegir otro nicho' : 'Reiniciar consulta e escolher outro nicho' }}">
                    <span>{{ $isEs ? 'Nuevo Nicho' : 'Novo Nicho' }}</span>
                </button>
                <button type="button" onclick="toggleSofiChat(false)" class="sofi-close-btn" title="{{ __('Fechar') }}">
                    ✕
                </button>
            </div>
        </div>

        <!-- Atalho direto para WhatsApp com Sofi -->
        <div class="sofi-chat-wa-bar">
            <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="sofi-chat-wa-link">
                <span class="sofi-chat-wa-content">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                    </svg>
                    <span>{{ $isEs ? 'Prefiero conversar por WhatsApp' : 'Prefiro conversar pelo WhatsApp' }}</span>
                </span>
                <span class="sofi-wa-arrow">→</span>
            </a>
        </div>

        <!-- Área de Conversa / Mensagens -->
        <div id="sofiMessagesList" class="sofi-messages-list">
            
            <!-- Mensagem de Boas-vindas da Sofi -->
            <div class="sofi-msg-row">
                <div class="sofi-bot-icon-small">
                    
                </div>
                <div class="sofi-bubble-bot">
                    <p class="m-0">{{ $greeting }}</p>
                </div>
            </div>

            <!-- Chips de Sugestões Iniciais -->
            <div id="sofiInitialChips" class="sofi-quick-chips-wrapper">
                <button type="button" onclick="sendQuickPrompt('{{ $isEs ? '¿Qué paquete me recomiendas para mi negocio?' : 'Qual pacote é ideal para o meu negócio?' }}')" 
                        class="sofi-quick-chip-btn">
                    {{ $isEs ? 'Recomiéndame un paquete' : 'Recomende um pacote' }}
                </button>
                <button type="button" onclick="sendQuickPrompt('{{ $isEs ? 'Quiero un guion y calendario de 30 días para mi empresa' : 'Quero um roteiro e calendário de 30 dias para a minha empresa' }}')" 
                        class="sofi-quick-chip-btn">
                    {{ $isEs ? 'Guiones y Plan 30 Días' : 'Roteiros e Grade 30 Dias' }}
                </button>
                <button type="button" onclick="sendQuickPrompt('{{ $isEs ? '¿Cuáles son las formas de pago y facturación?' : 'Quais são as formas de pagamento e fatura?' }}')" 
                        class="sofi-quick-chip-btn">
                    {{ $isEs ? 'Bizum, Redsys y Factura' : 'Bizum, Cartão e Fatura' }}
                </button>
                <button type="button" onclick="resetSofiChatSession()" 
                        class="sofi-quick-chip-btn">
                    {{ $isEs ? 'Elegir Nicho' : 'Escolher Nicho' }}
                </button>
            </div>

            <!-- Indicador de Digitação -->
            <div id="sofiTypingIndicator" class="sofi-typing-indicator hidden">
                <div class="sofi-bot-icon-small">
                    
                </div>
                <div class="sofi-typing-dots">
                    <span class="sofi-dot"></span>
                    <span class="sofi-dot"></span>
                    <span class="sofi-dot"></span>
                </div>
            </div>

        </div>

        <!-- Rodapé / Input de Mensagem -->
        <div class="sofi-chat-footer">
            <form id="sofiChatForm" onsubmit="event.preventDefault(); submitSofiMessage();" class="sofi-chat-form">
                <input type="text" id="sofiUserInput" 
                       placeholder="{{ $isEs ? 'Escribe tu duda o cuéntame tu nicho...' : 'Digite sua dúvida ou conte seu nicho...' }}" 
                       class="sofi-chat-input">
                <button type="submit" id="sofiSubmitBtn" class="sofi-chat-submit-btn" title="{{ __('Enviar') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>
            <p class="sofi-footer-tagline">
                {{ $isEs ? 'Potenciado por Sofi IA · Respuestas inmediatas' : 'Potencializado por Sofi IA · Atendimento instantâneo' }}
            </p>
        </div>

    </div>

    <!-- Botão Flutuante Principal (Launcher) -->
    <button type="button" id="sofiLauncherBtn" onclick="toggleSofiChat()" class="sofi-launcher-btn">
        <span class="sofi-launcher-dot-wrapper">
            <span class="sofi-launcher-dot-ping"></span>
            <span class="sofi-launcher-dot-solid"></span>
        </span>
        <span>{{ $isEs ? 'Consultar con Sofi' : 'Consultar com Sofi' }}</span>
    </button>

</div>

<!-- Modal de Fechamento Focado de Pacote (Sofi Checkout Modal) -->
<div id="sofiPackFocusedModal" class="sofi-pack-modal-backdrop" onclick="if(event.target === this) closePackModal()">
    <div class="sofi-pack-modal-card">
        <div class="sofi-pack-modal-header">
            <div>
                <h3 id="modalPackTitle" class="sofi-pack-modal-title">Pacote de Vídeos</h3>
                <p id="modalPackQty" class="sofi-pack-modal-subtitle">4 vídeos em 4K HDR</p>
            </div>
            <button type="button" onclick="closePackModal()" class="sofi-close-btn">✕</button>
        </div>

        <div class="sofi-pack-modal-body">
            <!-- Bloco de Preço -->
            <div class="sofi-pack-modal-price-box">
                <div>
                    <span id="modalPackPricePrefix" class="sofi-teaser-status-label">{{ __('Preço Base:') }}</span>
                    <div id="modalPackPriceDisplay" class="sofi-pack-modal-price-val">€ 0,00</div>
                    <div id="modalPackVatDisplay" class="sofi-pack-modal-vat-note">+ IVA (21%) = € 0,00</div>
                </div>
                <div class="text-right">
                    <span class="sofi-badge-ia">{{ __('Recomendação Sofi') }}</span>
                </div>
            </div>

            <!-- Entregáveis Inclusos -->
            <div>
                <p class="sofi-pack-modal-addons-title">{{ __('O que está incluso:') }}</p>
                <ul class="sofi-pack-modal-deliverables">
                    <li class="sofi-pack-modal-deliverable-item">
                        <span class="sofi-pack-modal-check">✓</span>
                        <span>{{ __('Roteiros de Alta Retenção (0-3s) calibrados com Sofi IA') }}</span>
                    </li>
                    <li class="sofi-pack-modal-deliverable-item">
                        <span class="sofi-pack-modal-check">✓</span>
                        <span>{{ __('Captação Cinematográfica 4K HDR e Edição Dinâmica') }}</span>
                    </li>
                    <li class="sofi-pack-modal-deliverable-item">
                        <span class="sofi-pack-modal-check">✓</span>
                        <span>{{ __('Aprovação ágil com 1 clique no Portal do Cliente') }}</span>
                    </li>
                    <li class="sofi-pack-modal-deliverable-item">
                        <span class="sofi-pack-modal-check">✓</span>
                        <span>{{ __('Factura oficial com IVA dedutível (Bizum, Redsys ou Stripe)') }}</span>
                    </li>
                </ul>
            </div>

            <!-- Add-ons Opcionais -->
            <div id="modalAddonsSection" class="hidden">
                <p class="sofi-pack-modal-addons-title">{{ __('Opcionais / Add-ons:') }}</p>
                <div id="modalAddonsList" class="sofi-pack-modal-addons-list">
                    <!-- Gerado dinamicamente -->
                </div>
            </div>
        </div>

        <div class="sofi-pack-modal-footer">
            <a href="{{ route('portal.register') }}" id="modalRegisterBtn" class="sofi-pack-modal-btn-primary">
                {{ $isEs ? 'Crear Cuenta e Iniciar Producción' : 'Criar Conta e Iniciar Produção' }}
            </a>
            <a href="{{ route('portal.login') }}" id="modalLoginBtn" class="sofi-pack-modal-btn-secondary">
                {{ $isEs ? 'Ya tengo cuenta · Iniciar sesión' : 'Já sou cliente · Entrar no Portal' }}
            </a>
        </div>
    </div>
</div>

<script>
    let isSofiChatOpen = false;
    let currentPackData = null;
    let selectedAddonNames = [];
    const isClientAuthenticated = {{ auth('cliente')->check() ? 'true' : 'false' }};
    const portalPacksUrl = "{{ route('portal.packs.index') }}";
    const sofiChatEndpoint = "{{ route('storefront.sofi-chat', $tenant) }}";
    const sofiResetEndpoint = "{{ route('storefront.sofi-chat.reset', $tenant) }}";
    const sofiCsrfToken = "{{ csrf_token() }}";
    const baseRegisterUrl = "{{ route('portal.register') }}";
    const baseLoginUrl = "{{ route('portal.login') }}";
    const greetingText = @json($greeting);

    function dismissSofiTeaser() {
        const bubble = document.getElementById('sofiTeaserBubble');
        if (bubble) {
            bubble.classList.remove('show');
            setTimeout(() => { bubble.classList.add('hidden'); }, 300);
        }
    }

    function toggleSofiChat(forceOpen = null) {
        const windowEl = document.getElementById('sofiChatWindow');
        dismissSofiTeaser();

        if (forceOpen !== null) {
            isSofiChatOpen = forceOpen;
        } else {
            isSofiChatOpen = !isSofiChatOpen;
        }

        if (isSofiChatOpen) {
            windowEl.classList.remove('hidden');
            setTimeout(() => {
                const input = document.getElementById('sofiUserInput');
                if (input) input.focus();
            }, 100);
        } else {
            windowEl.classList.add('hidden');
        }
    }

    async function resetSofiChatSession() {
        try {
            await fetch(sofiResetEndpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': sofiCsrfToken,
                    'Accept': 'application/json'
                }
            });
        } catch(e) {
            console.error('Reset error:', e);
        }

        const list = document.getElementById('sofiMessagesList');
        const typing = document.getElementById('sofiTypingIndicator');

        list.innerHTML = `
            <div class="sofi-msg-row">
                <div class="sofi-bot-icon-small">S</div>
                <div class="sofi-bubble-bot">
                    <p class="m-0">${escapeHtml(greetingText)}</p>
                </div>
            </div>
            <div id="sofiInitialChips" class="sofi-quick-chips-wrapper">
                <button type="button" onclick="sendQuickPrompt('{{ $isEs ? 'Soy una clínica o profesional de la salud' : 'Sou de uma clínica ou profissional da saúde' }}')" 
                        class="sofi-quick-chip-btn">
                    {{ $isEs ? 'Clínica & Salud' : 'Clínica & Saúde' }}
                </button>
                <button type="button" onclick="sendQuickPrompt('{{ $isEs ? 'Soy del sector inmobiliario o reformas' : 'Sou do setor imobiliário ou reformas' }}')" 
                        class="sofi-quick-chip-btn">
                    {{ $isEs ? 'Inmobiliaria' : 'Imóveis & Construção' }}
                </button>
                <button type="button" onclick="sendQuickPrompt('{{ $isEs ? 'Tengo una tienda de moda o e-commerce' : 'Tenho uma loja de moda ou e-commerce' }}')" 
                        class="sofi-quick-chip-btn">
                    {{ $isEs ? 'Moda & E-commerce' : 'Moda & Varejo' }}
                </button>
                <button type="button" onclick="sendQuickPrompt('{{ $isEs ? 'Ofrezco consultoría o servicios B2B' : 'Ofereço consultoria ou serviços B2B' }}')" 
                        class="sofi-quick-chip-btn">
                    {{ $isEs ? 'Servicios B2B' : 'Serviços B2B' }}
                </button>
            </div>
        `;
        list.appendChild(typing);
        typing.classList.add('hidden');

        const input = document.getElementById('sofiUserInput');
        if (input) {
            input.value = '';
            input.placeholder = "{{ $isEs ? 'Dime tu nuevo nicho o producto...' : 'Diga seu novo nicho ou produto...' }}";
            input.focus();
        }
    }

    function sendQuickPrompt(promptText) {
        if (promptText.includes('Trocar de nicho') || promptText.includes('Cambiar de nicho') || promptText.includes('Escolher Nicho') || promptText.includes('Elegir Nicho')) {
            resetSofiChatSession();
            return;
        }
        document.getElementById('sofiUserInput').value = promptText;
        submitSofiMessage();
    }

    async function submitSofiMessage() {
        const input = document.getElementById('sofiUserInput');
        const message = input.value.trim();
        if (!message) return;

        input.value = '';
        appendUserMessage(message);

        // Esconde chips iniciais
        const initialChips = document.getElementById('sofiInitialChips');
        if (initialChips) initialChips.classList.add('hidden');

        // Mostra indicador de digitação
        showTypingIndicator(true);

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 8000);

        try {
            const response = await fetch(sofiChatEndpoint, {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': sofiCsrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message })
            });

            clearTimeout(timeoutId);
            const data = await response.json();
            showTypingIndicator(false);

            if (data.status === 'success') {
                appendSofiMessage(data.reply, data.recommended_pack, data.call_to_action, data.quick_replies);
            } else {
                appendSofiMessage("{{ $isEs ? 'Disculpa, no pude procesar tu mensaje. ¿Puedes intentarlo de nuevo?' : 'Desculpe, tive uma instabilidade momentânea. Pode tentar novamente?' }}");
            }
        } catch (e) {
            clearTimeout(timeoutId);
            console.error('Sofi Sales Chat Error:', e);
            showTypingIndicator(false);
            appendSofiMessage("{{ $isEs ? 'Tuvimos una pausa en la conexión. Puedes contratar directamente en la sección de paquetes.' : 'Tivemos uma oscilação de conexão. Você pode selecionar seu pacote diretamente na tabela abaixo!' }}");
        }
    }

    function appendUserMessage(text) {
        const list = document.getElementById('sofiMessagesList');
        const typing = document.getElementById('sofiTypingIndicator');

        const msgRow = document.createElement('div');
        msgRow.className = 'sofi-msg-row user-row';
        msgRow.innerHTML = `
            <div class="sofi-bubble-user">
                <p class="m-0">${escapeHtml(text)}</p>
            </div>
        `;

        list.insertBefore(msgRow, typing);
        scrollChatToBottom();
    }

    function appendSofiMessage(replyText, recommendedPack = null, callToAction = null, quickReplies = []) {
        const list = document.getElementById('sofiMessagesList');
        const typing = document.getElementById('sofiTypingIndicator');

        const wrapper = document.createElement('div');
        wrapper.className = 'sofi-msg-wrapper';

        let actionCardHtml = '';

        if (callToAction === 'register_dna') {
            actionCardHtml = `
                <div class="sofi-dna-card-in-chat">
                    <span class="sofi-dna-card-badge">{{ $isEs ? 'ADN de Marca en el Portal' : 'DNA de Marca no Portal' }}</span>
                    <h4 class="sofi-dna-card-title">{{ $isEs ? 'Calibración Estratégica Gratuita' : 'Calibração Estratégica Gratuita' }}</h4>
                    <p class="sofi-dna-card-desc">{{ $isEs ? 'Crea tu acceso en 30 segundos para definir tu nicho, público ideal y generar tu Grade Editorial de 30 días con Sofi IA.' : 'Crie seu acesso em 30 segundos para definir nicho, público-alvo e gerar sua Grade Editorial de 30 dias com a Sofi IA.' }}</p>
                    <a href="${baseRegisterUrl}" class="sofi-dna-card-btn">
                        {{ $isEs ? 'Crear Cuenta y Diagnosticar ADN' : 'Criar Conta e Calibrar DNA' }}
                    </a>
                </div>
            `;
        } else if (recommendedPack) {
            window.lastRecommendedPack = recommendedPack;
            actionCardHtml = `
                <div class="sofi-pack-card-in-chat">
                    <div class="sofi-pack-card-header">
                        <span class="sofi-pack-card-badge">
                            ${escapeHtml(recommendedPack.badge)}
                        </span>
                        <span class="sofi-pack-card-price">${escapeHtml(recommendedPack.price)} ${escapeHtml(recommendedPack.currency)}</span>
                    </div>
                    <div>
                        <h4 class="sofi-pack-card-title">${escapeHtml(recommendedPack.title)}</h4>
                        <p class="sofi-pack-card-desc">${escapeHtml(recommendedPack.description)}</p>
                    </div>
                    <button type="button" onclick="openPackModal(window.lastRecommendedPack)" 
                            class="sofi-pack-card-btn">
                        {{ $isEs ? 'Ver Detalles y Contratar' : 'Ver Detalhes e Contratar' }}
                    </button>
                </div>
            `;
        }

        let quickChipsHtml = '';
        if (quickReplies && quickReplies.length > 0) {
            quickChipsHtml = `<div class="sofi-quick-chips-wrapper">` +
                quickReplies.map(r => `
                    <button type="button" onclick="sendQuickPrompt('${escapeHtml(r)}')" 
                            class="sofi-quick-chip-btn">
                        ${escapeHtml(r)}
                    </button>
                `).join('') + `</div>`;
        }

        wrapper.innerHTML = `
            <div class="sofi-msg-row">
                <div class="sofi-bot-icon-small">
                    
                </div>
                <div class="sofi-bubble-bot">
                    <p class="m-0">${escapeHtml(replyText)}</p>
                    ${actionCardHtml}
                </div>
            </div>
            ${quickChipsHtml}
        `;

        list.insertBefore(wrapper, typing);
        scrollChatToBottom();
    }

    function showTypingIndicator(show) {
        const typing = document.getElementById('sofiTypingIndicator');
        if (show) {
            typing.classList.remove('hidden');
        } else {
            typing.classList.add('hidden');
        }
        scrollChatToBottom();
    }

    function scrollChatToBottom() {
        const list = document.getElementById('sofiMessagesList');
        setTimeout(() => {
            list.scrollTop = list.scrollHeight;
        }, 50);
    }

    // Modal de Fechamento Focado
    function openPackModal(pack) {
        if (!pack) return;
        currentPackData = pack;
        selectedAddonNames = [];

        document.getElementById('modalPackTitle').innerText = pack.title;
        document.getElementById('modalPackQty').innerText = `${pack.video_quantity} {{ __('vídeos em 4K HDR') }}`;

        const addonsSection = document.getElementById('modalAddonsSection');
        const addonsList = document.getElementById('modalAddonsList');
        addonsList.innerHTML = '';

        if (pack.addons && pack.addons.length > 0) {
            addonsSection.classList.remove('hidden');
            pack.addons.forEach(addon => {
                const item = document.createElement('div');
                item.className = 'sofi-pack-modal-addon-item';
                item.innerHTML = `
                    <label class="flex items-center gap-2 cursor-pointer w-full justify-between">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" value="${escapeHtml(addon.name)}" data-price="${addon.price}" onchange="toggleModalAddon(this)" class="accent-orange-600">
                            <span>${escapeHtml(addon.name)}</span>
                        </div>
                        <span class="font-mono text-orange-400 font-bold">+€ ${addon.price}</span>
                    </label>
                `;
                addonsList.appendChild(item);
            });
        } else {
            addonsSection.classList.add('hidden');
        }

        updateModalPrice();
        document.getElementById('sofiPackFocusedModal').classList.add('show');
    }

    function toggleModalAddon(checkbox) {
        const addonName = checkbox.value;
        const parent = checkbox.closest('.sofi-pack-modal-addon-item');

        if (checkbox.checked) {
            if (!selectedAddonNames.includes(addonName)) selectedAddonNames.push(addonName);
            if (parent) parent.classList.add('selected');
        } else {
            selectedAddonNames = selectedAddonNames.filter(n => n !== addonName);
            if (parent) parent.classList.remove('selected');
        }

        updateModalPrice();
    }

    function updateModalPrice() {
        if (!currentPackData) return;

        let totalBase = currentPackData.raw_price || 0;
        const checkboxes = document.querySelectorAll('#modalAddonsList input[type="checkbox"]:checked');
        checkboxes.forEach(cb => {
            totalBase += parseFloat(cb.dataset.price) || 0;
        });

        const totalWithIva = totalBase * 1.21;
        const currency = currentPackData.currency || 'EUR';

        document.getElementById('modalPackPriceDisplay').innerText = `€ ${formatMoney(totalBase)}`;
        document.getElementById('modalPackVatDisplay').innerText = `+ IVA (21%) = € ${formatMoney(totalWithIva)}`;

        const addonsParam = selectedAddonNames.length > 0 ? `&addons=${encodeURIComponent(selectedAddonNames.join(','))}` : '';

        if (isClientAuthenticated) {
            document.getElementById('modalRegisterBtn').innerText = "{{ $isEs ? 'Contratar en mi Portal' : 'Contratar no meu Portal' }}";
            document.getElementById('modalRegisterBtn').href = portalPacksUrl;
            document.getElementById('modalLoginBtn').classList.add('hidden');
        } else {
            document.getElementById('modalRegisterBtn').innerText = "{{ $isEs ? 'Crear Cuenta e Iniciar Producción' : 'Criar Conta e Iniciar Produção' }}";
            document.getElementById('modalRegisterBtn').href = `${baseRegisterUrl}?pack=${encodeURIComponent(currentPackData.slug)}${addonsParam}`;
            document.getElementById('modalLoginBtn').classList.remove('hidden');
            document.getElementById('modalLoginBtn').href = `${baseLoginUrl}?intended_pack_slug=${encodeURIComponent(currentPackData.slug)}${addonsParam}`;
        }
    }

    function closePackModal() {
        document.getElementById('sofiPackFocusedModal').classList.remove('show');
    }

    function formatMoney(amount) {
        return new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
    }

    function escapeHtml(string) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(string).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Auto-exibe o teaser persuasivo após 2 segundos
    window.addEventListener('load', () => {
        setTimeout(() => {
            const bubble = document.getElementById('sofiTeaserBubble');
            if (bubble && !isSofiChatOpen) {
                bubble.classList.add('show');
            }
        }, 2000);
    });
</script>
