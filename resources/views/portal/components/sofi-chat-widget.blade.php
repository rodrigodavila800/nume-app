<!-- Sofi Copilot Floating Widget -->
<div id="sofi-copilot-root" class="fixed bottom-6 right-6 z-50">
    
    <!-- Botão Flutuante Circular -->
    <button type="button" 
            id="sofi-toggle-btn"
            onclick="toggleSofiChat()"
            class="group flex items-center gap-2.5 px-4 py-3 rounded-full bg-gradient-to-r from-orange-600 to-amber-600 text-white shadow-2xl hover:shadow-orange-500/30 hover:scale-105 active:scale-95 transition-all duration-200">
        <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-300 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-white"></span>
        </span>
        <span class="font-extrabold text-sm tracking-wide">
            Sofi Copilot
        </span>
        <span class="text-xs bg-white/20 px-1.5 py-0.5 rounded-full font-bold">
            IA
        </span>
    </button>

    <!-- Drawer / Janela de Chat -->
    <div id="sofi-chat-window" class="hidden flex-col w-[380px] max-w-[calc(100vw-2rem)] h-[560px] max-h-[calc(100vh-6rem)] bg-white rounded-2xl shadow-2xl border border-zinc-200 overflow-hidden absolute bottom-16 right-0 transition-all duration-300">
        
        <!-- Header do Chat -->
        <div class="px-5 py-4 bg-zinc-900 text-white flex items-center justify-between border-b border-zinc-800">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-orange-500 to-amber-500 flex items-center justify-center font-extrabold text-sm shadow-md">
                    ⚡
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="font-bold text-sm text-zinc-100">Sofi Copilot</h4>
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    </div>
                    <p class="text-[11px] text-zinc-400 font-medium">
                        {{ __('Consultora Audiovisual & Roteirista') }}
                    </p>
                </div>
            </div>

            <button type="button" onclick="toggleSofiChat()" class="text-zinc-400 hover:text-white p-1 rounded-lg transition" aria-label="Fechar">
                ✕
            </button>
        </div>

        <!-- Área de Mensagens -->
        <div id="sofi-messages-container" class="flex-1 p-4 overflow-y-auto space-y-3 bg-zinc-50/50">
            
            <!-- Mensagem Inicial de Boas-Vindas -->
            <div class="flex items-start gap-2.5">
                <div class="w-7 h-7 rounded-full bg-orange-600 text-white text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">
                    S
                </div>
                <div class="bg-white border border-zinc-200 p-3.5 rounded-2xl rounded-tl-sm text-xs text-zinc-800 shadow-sm leading-relaxed max-w-[85%]">
                    <p>
                        {{ __('Olá') }} <strong>{{ auth('cliente')->user()?->name ?? 'Cliente' }}</strong>! 👋
                    </p>
                    <p class="mt-1">
                        {{ __('Sou a Sofi, sua consultora audiovisual na NUME. Posso sugerir ganchos visuais para seus vídeos, ajustar roteiros ou tirar dúvidas de produção. Em que posso te ajudar hoje?') }}
                    </p>
                </div>
            </div>

            <!-- Sugestões de Atalhos Rápidos -->
            <div id="sofi-quick-chips" class="pt-2 flex flex-wrap gap-1.5">
                <button type="button" onclick="sendQuickPrompt('{{ __('Como criar um gancho de 3 segundos para reter clientes?') }}')" class="text-[11px] font-semibold bg-white border border-orange-200 text-orange-800 hover:bg-orange-50 px-2.5 py-1.5 rounded-lg transition">
                    ⚡ {{ __('Gancho 0-3s') }}
                </button>
                <button type="button" onclick="sendQuickPrompt('{{ __('Qual o status da minha produção e prazos de entrega?') }}')" class="text-[11px] font-semibold bg-white border border-orange-200 text-orange-800 hover:bg-orange-50 px-2.5 py-1.5 rounded-lg transition">
                    🎥 {{ __('Status e Prazos') }}
                </button>
                <button type="button" onclick="sendQuickPrompt('{{ __('Quero refinar o público-alvo dos meus roteiros.') }}')" class="text-[11px] font-semibold bg-white border border-orange-200 text-orange-800 hover:bg-orange-50 px-2.5 py-1.5 rounded-lg transition">
                    🎯 {{ __('Público-Alvo') }}
                </button>
            </div>

        </div>

        <!-- Indicador de Digitação -->
        <div id="sofi-typing-indicator" class="hidden px-4 py-2 bg-zinc-50 border-t border-zinc-100 text-xs text-zinc-500 items-center gap-2">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-orange-600 animate-pulse"></span>
            <span>{{ __('Sofi está formulando sua resposta...') }}</span>
        </div>

        <!-- Form de Envio -->
        <form id="sofi-chat-form" onsubmit="handleSofiSubmit(event)" class="p-3 bg-white border-t border-zinc-200 flex items-center gap-2">
            <input type="text" 
                   id="sofi-chat-input"
                   placeholder="{{ __('Pergunte à Sofi...') }}"
                   class="flex-1 text-xs px-3.5 py-2.5 bg-zinc-100 rounded-xl border border-transparent focus:border-orange-500 focus:bg-white focus:outline-none transition">
            
            <button type="submit" 
                    id="sofi-send-btn"
                    class="px-3.5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs shadow transition">
                <span>➤</span>
            </button>
        </form>

    </div>
</div>

<script>
    let isSofiOpen = false;
    let sofiConversationId = null;

    function toggleSofiChat() {
        const win = document.getElementById('sofi-chat-window');
        const root = document.getElementById('sofi-copilot-root');
        if (!win) return;

        isSofiOpen = !isSofiOpen;
        if (isSofiOpen) {
            win.classList.remove('hidden');
            win.classList.add('flex');
            setTimeout(() => {
                const input = document.getElementById('sofi-chat-input');
                if (input) input.focus();
                scrollSofiToBottom();
            }, 100);
        } else {
            win.classList.add('hidden');
            win.classList.remove('flex');
        }
    }

    function scrollSofiToBottom() {
        const container = document.getElementById('sofi-messages-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    function sendQuickPrompt(text) {
        const input = document.getElementById('sofi-chat-input');
        if (input) {
            input.value = text;
            handleSofiSubmit(new Event('submit'));
        }
    }

    async function handleSofiSubmit(e) {
        if (e && e.preventDefault) e.preventDefault();
        const input = document.getElementById('sofi-chat-input');
        const container = document.getElementById('sofi-messages-container');
        const typingIndicator = document.getElementById('sofi-typing-indicator');
        const sendBtn = document.getElementById('sofi-send-btn');
        if (!input || !container) return;

        const messageText = input.value.trim();
        if (!messageText) return;

        // Renderiza mensagem do usuário
        const userHtml = `
            <div class="flex items-start justify-end gap-2.5">
                <div class="bg-orange-600 text-white p-3 rounded-2xl rounded-tr-sm text-xs leading-relaxed max-w-[85%] shadow-sm">
                    ${messageText.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', userHtml);
        input.value = '';
        scrollSofiToBottom();

        // Esconde chips de sugestão inicial
        const chips = document.getElementById('sofi-quick-chips');
        if (chips) chips.style.display = 'none';

        // Mostra digitando
        if (typingIndicator) {
            typingIndicator.classList.remove('hidden');
            typingIndicator.classList.add('flex');
        }
        if (sendBtn) sendBtn.disabled = true;

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('{{ route("portal.sofi.chat") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token || ''
                },
                body: JSON.stringify({
                    message: messageText,
                    conversation_id: sofiConversationId
                })
            });

            const data = await res.json();
            if (data.success && data.reply) {
                sofiConversationId = data.conversation_id;

                const formattedContent = data.reply.content
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/\n\n/g, '<br><br>')
                    .replace(/\n/g, '<br>');

                const sofiHtml = `
                    <div class="flex items-start gap-2.5">
                        <div class="w-7 h-7 rounded-full bg-orange-600 text-white text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">
                            S
                        </div>
                        <div class="bg-white border border-zinc-200 p-3.5 rounded-2xl rounded-tl-sm text-xs text-zinc-800 shadow-sm leading-relaxed max-w-[85%]">
                            ${formattedContent}
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', sofiHtml);
            } else {
                container.insertAdjacentHTML('beforeend', `
                    <div class="text-xs text-red-600 p-2 bg-red-50 rounded-lg">
                        ⚠️ ${data.message || 'Não foi possível processar a mensagem.'}
                    </div>
                `);
            }
        } catch (err) {
            container.insertAdjacentHTML('beforeend', `
                <div class="text-xs text-red-600 p-2 bg-red-50 rounded-lg">
                    ⚠️ Erro de conexão com o servidor.
                </div>
            `);
        } finally {
            if (typingIndicator) {
                typingIndicator.classList.add('hidden');
                typingIndicator.classList.remove('flex');
            }
            if (sendBtn) sendBtn.disabled = false;
            scrollSofiToBottom();
        }
    }
</script>
