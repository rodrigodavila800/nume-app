@php
    $isEs = str_starts_with(session('locale', app()->getLocale() ?: 'pt_BR'), 'es');
    $greeting = $isEs
        ? '¡Hola! Soy Sofi, la asistente de ventas de ' . $tenant->name . '. Cuéntame tu negocio o elige una opción y te muestro el paquete ideal con precio.'
        : 'Olá! Sou a Sofi, assistente de vendas da ' . $tenant->name . '. Me conta o seu negócio ou escolha uma opção, que eu já te mostro o pacote ideal com preço.';
    $teaserText = $isEs ? '¿Te ayudo a elegir el paquete de vídeos ideal?' : 'Quer ajuda para escolher o pacote de vídeos ideal?';
    $startChips = $isEs
        ? ['¿Qué paquete es ideal para mi negocio?', '¿Cuáles son las formas de pago?', '¿Cómo funciona la producción?']
        : ['Qual pacote é ideal para o meu negócio?', 'Quais são as formas de pagamento?', 'Como funciona a produção?'];
    $nicheChips = $isEs
        ? ['Soy una clínica o profesional de la salud', 'Soy del sector inmobiliario o reformas', 'Tengo una tienda de moda o e-commerce', 'Ofrezco consultoría o servicios B2B']
        : ['Sou de uma clínica ou profissional da saúde', 'Sou do setor imobiliário ou reformas', 'Tenho uma loja de moda ou e-commerce', 'Ofereço consultoria ou serviços B2B'];
    $tenantWa = isset($tenant) ? ($tenant->social_whatsapp ?? null) : null;
    $waPhone = preg_replace('/\D/', '', $tenantWa ?: config('services.whatsapp.contact_number', '34664274151'));
    $waUrl = 'https://wa.me/' . $waPhone . '?text=' . urlencode(__('Olá Sofi! Gostaria de saber mais sobre a produção de vídeos da NUME.'));
    $loggedCliente = auth('cliente')->user();
    $isClientOfTenant = $loggedCliente && (int) $loggedCliente->tenant_id === (int) $tenant->id;
@endphp

{{-- Sofi na vitrine: assistente de vendas. Texto vindo da IA entra só por textContent ou já escapado (nunca em onclick). --}}
<div id="sofiSalesWidget" class="sofi-sales-widget"
     data-chat-url="{{ route('storefront.sofi-chat', $tenant) }}"
     data-reset-url="{{ route('storefront.sofi-chat.reset', $tenant) }}"
     data-pack-url="{{ $isClientOfTenant ? route('portal.packs.index') : route('portal.register') }}"
     data-register-url="{{ route('portal.register') }}"
     data-greeting="{{ $greeting }}"
     data-start-chips='@json($startChips)'
     data-niche-chips='@json($nicheChips)'
     data-label-pack-cta="{{ $isEs ? 'Contratar este paquete' : 'Contratar este pacote' }}"
     data-label-vat="{{ $isEs ? '+ IVA' : '+ IVA' }}"
     data-label-dna-title="{{ $isEs ? 'Calibra el ADN de tu marca gratis' : 'Calibre o DNA da sua marca grátis' }}"
     data-label-dna-text="{{ $isEs ? 'Crea tu cuenta y la Sofi arma tu parrilla de 30 días.' : 'Crie sua conta e a Sofi monta sua grade de 30 dias.' }}"
     data-label-dna-cta="{{ $isEs ? 'Crear cuenta' : 'Criar conta' }}"
     data-label-error="{{ $isEs ? 'Tuve una pausa en la conexión. ¿Lo intentamos de nuevo?' : 'Tive uma oscilação na conexão. Pode tentar de novo?' }}"
     data-label-new-start="{{ $isEs ? 'Cuéntame tu negocio o producto...' : 'Me conta o seu negócio ou produto...' }}">

    <div id="sofiTeaserBubble" class="sofi-teaser" role="button" tabindex="0" aria-label="{{ $isEs ? 'Abrir el chat con Sofi' : 'Abrir o chat com a Sofi' }}">
        <span class="sofi-mark sofi-mark-sm" aria-hidden="true">S</span>
        <div class="sofi-teaser-body">
            <p class="sofi-teaser-name">Sofi <span class="sofi-online-dot" aria-hidden="true"></span><span class="sofi-online-label">{{ __('online') }}</span></p>
            <p class="sofi-teaser-text">{{ $teaserText }}</p>
        </div>
        <button type="button" class="sofi-icon-btn sofi-teaser-close" data-sofi-dismiss aria-label="{{ __('Fechar') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    <section id="sofiChatWindow" class="sofi-chat hidden" role="dialog" aria-modal="false" aria-labelledby="sofiChatTitle">
        <header class="sofi-chat-head">
            <div class="sofi-chat-identity">
                <span class="sofi-mark" aria-hidden="true">S<span class="sofi-mark-status"></span></span>
                <div class="sofi-chat-identity-text">
                    <h3 id="sofiChatTitle" class="sofi-chat-title">Sofi</h3>
                    <p class="sofi-chat-sub">{{ $isEs ? 'Asistente de ventas' : 'Assistente de vendas' }} · {{ $tenant->name }}</p>
                </div>
            </div>
            <div class="sofi-chat-actions">
                <button type="button" class="sofi-icon-btn" data-sofi-reset aria-label="{{ $isEs ? 'Empezar de nuevo' : 'Recomeçar a conversa' }}" title="{{ $isEs ? 'Empezar de nuevo' : 'Recomeçar a conversa' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5.5 15a7 7 0 0 0 12.2 2.5M18.5 9A7 7 0 0 0 6.3 6.5"/></svg>
                </button>
                <button type="button" class="sofi-icon-btn" data-sofi-close aria-label="{{ __('Fechar') }}" title="{{ __('Fechar') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
        </header>

        <div id="sofiMessagesList" class="sofi-chat-log" role="log" aria-live="polite" aria-relevant="additions">
            <div id="sofiTypingIndicator" class="sofi-typing hidden" aria-label="{{ $isEs ? 'Sofi está escribiendo' : 'A Sofi está digitando' }}">
                <span></span><span></span><span></span>
            </div>
        </div>

        <footer class="sofi-chat-foot">
            <form id="sofiChatForm" class="sofi-chat-form" novalidate>
                <label for="sofiUserInput" class="sr-only">{{ $isEs ? 'Tu mensaje' : 'Sua mensagem' }}</label>
                <input type="text" id="sofiUserInput" class="sofi-chat-input" maxlength="500" autocomplete="off"
                       placeholder="{{ $isEs ? 'Escribe tu pregunta...' : 'Escreva sua pergunta...' }}">
                <button type="submit" id="sofiSubmitBtn" class="sofi-chat-send" aria-label="{{ __('Enviar') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h13M13 6l6 6-6 6"/></svg>
                </button>
            </form>
            <div class="sofi-chat-foot-row">
                <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="sofi-wa-link">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    {{ $isEs ? 'Prefiero WhatsApp' : 'Prefiro o WhatsApp' }}
                </a>
                <span class="sofi-chat-disclaimer">
                    {{ $isEs ? 'Sofi es una IA' : 'A Sofi é uma IA' }} · <a href="{{ route('legal.privacy') }}">{{ $isEs ? 'Privacidad' : 'Privacidade' }}</a>
                </span>
            </div>
        </footer>
    </section>

    <button type="button" id="sofiLauncherBtn" class="sofi-launcher" aria-expanded="false" aria-controls="sofiChatWindow">
        <span class="sofi-mark sofi-mark-sm" aria-hidden="true">S</span>
        <span>{{ $isEs ? 'Hablar con Sofi' : 'Falar com a Sofi' }}</span>
    </button>
</div>

<script>
    (function () {
        const root = document.getElementById('sofiSalesWidget');
        if (!root) return;

        const el = (id) => document.getElementById(id);
        const chat = el('sofiChatWindow');
        const log = el('sofiMessagesList');
        const typing = el('sofiTypingIndicator');
        const input = el('sofiUserInput');
        const teaser = el('sofiTeaserBubble');
        const launcher = el('sofiLauncherBtn');
        const csrf = @json(csrf_token());
        const data = root.dataset;
        const startChips = JSON.parse(data.startChips || '[]');
        const nicheChips = JSON.parse(data.nicheChips || '[]');
        let isOpen = false;
        let busy = false;

        function make(tag, className, text) {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined && text !== null) node.textContent = String(text);
            return node;
        }

        // Negrito do WhatsApp (*texto*) e Markdown (**texto**) e quebras de linha, sobre texto JÁ escapado.
        function formatReply(text) {
            const escaped = String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
            return escaped
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(?!\s)(.+?)\*/g, '<strong>$1</strong>')
                .split(/\n{2,}/)
                .map((paragraph) => '<p>' + paragraph.replace(/\n/g, '<br>') + '</p>')
                .join('');
        }

        function scrollToEnd() {
            requestAnimationFrame(() => { log.scrollTop = log.scrollHeight; });
        }

        function addChips(labels) {
            if (!labels || labels.length === 0) return;
            const row = make('div', 'sofi-chips');
            labels.slice(0, 4).forEach((label) => {
                const chip = make('button', 'sofi-chip', label);
                chip.type = 'button';
                chip.addEventListener('click', () => { row.remove(); send(label); });
                row.appendChild(chip);
            });
            log.insertBefore(row, typing);
        }

        function packCard(pack) {
            const card = make('div', 'sofi-pack');
            const head = make('div', 'sofi-pack-head');
            head.appendChild(make('span', 'sofi-pack-badge', pack.badge));
            const price = make('span', 'sofi-pack-price', pack.price + ' ' + (pack.currency === 'EUR' ? '€' : pack.currency));
            price.appendChild(make('small', null, ' ' + data.labelVat));
            head.appendChild(price);
            card.appendChild(head);
            card.appendChild(make('p', 'sofi-pack-title', pack.title));
            if (pack.description) card.appendChild(make('p', 'sofi-pack-desc', pack.description));
            const cta = make('a', 'sofi-pack-cta', data.labelPackCta);
            cta.href = data.packUrl + '?pack=' + encodeURIComponent(pack.slug);
            card.appendChild(cta);
            return card;
        }

        function dnaCard() {
            const card = make('div', 'sofi-pack');
            card.appendChild(make('p', 'sofi-pack-title', data.labelDnaTitle));
            card.appendChild(make('p', 'sofi-pack-desc', data.labelDnaText));
            const cta = make('a', 'sofi-pack-cta', data.labelDnaCta);
            cta.href = data.registerUrl;
            card.appendChild(cta);
            return card;
        }

        function addMessage(sender, text, extra) {
            const row = make('div', 'sofi-msg sofi-msg-' + sender);
            const bubble = make('div', 'sofi-bubble');
            if (sender === 'bot') {
                bubble.innerHTML = formatReply(text);
                if (extra) bubble.appendChild(extra);
            } else {
                bubble.textContent = text;
            }
            row.appendChild(bubble);
            log.insertBefore(row, typing);
            scrollToEnd();
        }

        function setTyping(on) {
            typing.classList.toggle('hidden', !on);
            if (on) log.appendChild(typing);
            scrollToEnd();
        }

        async function send(text) {
            const message = String(text || '').trim();
            if (!message || busy) return;
            busy = true;
            input.value = '';
            log.querySelectorAll('.sofi-chips').forEach((row) => row.remove());
            addMessage('user', message);
            setTyping(true);

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 20000);

            try {
                const response = await fetch(data.chatUrl, {
                    method: 'POST',
                    signal: controller.signal,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ message }),
                });
                const payload = await response.json();
                setTyping(false);

                if (payload.status === 'success') {
                    const extra = payload.call_to_action === 'register_dna'
                        ? dnaCard()
                        : (payload.recommended_pack ? packCard(payload.recommended_pack) : null);
                    addMessage('bot', payload.reply, extra);
                    addChips(payload.quick_replies || []);
                } else {
                    addMessage('bot', data.labelError);
                }
            } catch (error) {
                setTyping(false);
                addMessage('bot', data.labelError);
            } finally {
                clearTimeout(timeout);
                busy = false;
                input.focus();
            }
        }

        function start(chips) {
            log.querySelectorAll('.sofi-msg, .sofi-chips').forEach((node) => node.remove());
            addMessage('bot', data.greeting);
            addChips(chips);
        }

        function dismissTeaser() {
            teaser.classList.remove('show');
            teaser.classList.add('hidden');
        }

        function toggle(open) {
            isOpen = open === undefined ? !isOpen : open;
            dismissTeaser();
            chat.classList.toggle('hidden', !isOpen);
            root.classList.toggle('is-open', isOpen);
            launcher.setAttribute('aria-expanded', String(isOpen));
            if (isOpen) {
                if (!log.querySelector('.sofi-msg')) start(startChips);
                setTimeout(() => input.focus(), 60);
            } else {
                launcher.focus();
            }
        }

        async function reset() {
            try {
                await fetch(data.resetUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            } catch (error) {
                // Sem rede: recomeça só na tela.
            }
            start(nicheChips);
            input.placeholder = data.labelNewStart;
            input.focus();
        }

        launcher.addEventListener('click', () => toggle());
        teaser.addEventListener('click', () => toggle(true));
        teaser.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggle(true); } });
        root.querySelector('[data-sofi-dismiss]').addEventListener('click', (event) => { event.stopPropagation(); dismissTeaser(); });
        root.querySelector('[data-sofi-close]').addEventListener('click', () => toggle(false));
        root.querySelector('[data-sofi-reset]').addEventListener('click', reset);
        el('sofiChatForm').addEventListener('submit', (event) => { event.preventDefault(); send(input.value); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && isOpen) toggle(false); });

        window.addEventListener('load', () => {
            setTimeout(() => { if (!isOpen) teaser.classList.add('show'); }, 2000);
        });
    })();
</script>
