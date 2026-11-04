@extends('layouts.app')

@section('title', __('Conversas & WhatsApp | NUME'))
@section('breadcrumb', __('Conversas & WhatsApp'))

@php
    $stages = \App\Models\Lead::stageLabels();
@endphp

@section('content')
<div class="space-y-4">
    <!-- Header da Central de Mensagens -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <span>{{ __('Conversas & WhatsApp') }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    {{ __('Meta Cloud API Conectada') }}
                </span>
            </h1>
            <p class="text-xs text-zinc-400">
                {{ __('Acompanhe as conversas atendidas pela Sofi no WhatsApp e responda diretamente pelo painel quando desejar.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.leads.index') }}" class="px-3 py-1.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-bold border border-zinc-700 transition">
                {{ __('Ver Funil no CRM') }}
            </a>
            @if($activeConversation && $activeConversation->contact_phone)
                <a href="https://web.whatsapp.com/send?phone={{ preg_replace('/\D/', '', $activeConversation->contact_phone) }}" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="px-3 py-1.5 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-400 text-xs font-bold border border-emerald-500/30 transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    <span>{{ __('Abrir Web') }}</span>
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-semibold">
            {{ session('success') }}
        </div>
    @endif
    @if(session('warning'))
        <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs font-semibold">
            {{ session('warning') }}
        </div>
    @endif

    <!-- Container Principal do Inbox -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 h-[calc(100vh-210px)] min-h-[580px]">
        
        <!-- Coluna 1: Lista de Conversas (Esquerda) -->
        <div class="lg:col-span-4 bg-zinc-900 border border-zinc-800 rounded-2xl flex flex-col overflow-hidden">
            <div class="p-3 border-b border-zinc-800">
                <div class="relative">
                    <input type="text" 
                           id="searchConversation" 
                           placeholder="{{ __('Buscar contato ou telefone...') }}" 
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl pl-8 pr-3 py-1.5 text-xs focus:outline-none focus:border-orange-500">
                    <svg class="w-3.5 h-3.5 text-zinc-500 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                    </svg>
                </div>
            </div>

            <!-- Lista Rolável -->
            <div class="flex-1 overflow-y-auto divide-y divide-zinc-800/60" id="conversationList">
                @include('inbox._conversation_list')
            </div>
        </div>

        <!-- Coluna 2: Janela do Chat (Centro) -->
        <div class="lg:col-span-5 bg-zinc-900 border border-zinc-800 rounded-2xl flex flex-col overflow-hidden">
            @if($activeConversation)
                <!-- Header do Chat Ativo -->
                <div class="p-3.5 border-b border-zinc-800 bg-zinc-950/40 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-600/30 border border-emerald-500/40 text-emerald-400 flex items-center justify-center text-xs font-bold">
                            WA
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-white">
                                {{ $activeConversation->cliente?->name ?? ($activeConversation->contact_phone ? "+{$activeConversation->contact_phone}" : $activeConversation->title) }}
                            </h3>
                            <p class="text-[10px] text-emerald-400 font-medium">
                                {{ __('WhatsApp Direto · Atendimento 24/7 com Sofi') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if(in_array($activeConversation->status, ['human', 'needs_human'], true))
                            <form method="POST" action="{{ route('inbox.hand-back', $activeConversation) }}">
                                @csrf
                                <button type="submit" class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-600/20 text-emerald-300 border border-emerald-500/30 hover:bg-emerald-600/30 transition">
                                    {{ __('Devolver para a Sofi') }}
                                </button>
                            </form>
                        @endif
                        @if($associatedLead)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-orange-500/10 text-orange-400 border border-orange-500/20">
                                {{ $stages[$associatedLead->stage] ?? $associatedLead->stage }}
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Lista de Balões de Mensagem -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3.5 bg-zinc-950/20" id="messagesContainer">
                    @forelse($activeConversation->messages as $m)
                        @include('inbox._message', ['m' => $m])
                    @empty
                        <div id="messagesEmpty" class="text-center text-zinc-500 text-xs py-8">
                            {{ __('Nenhuma mensagem nesta conversa.') }}
                        </div>
                    @endforelse
                </div>

                <!-- Formulário de Envio Manual para o WhatsApp -->
                <form action="{{ route('inbox.reply', $activeConversation) }}" method="POST" class="p-3 border-t border-zinc-800 bg-zinc-950/60">
                    @csrf
                    <div class="flex gap-2">
                        <textarea name="message" 
                                  rows="2" 
                                  placeholder="{{ __('Digite uma mensagem para responder direto no WhatsApp do cliente...') }}" 
                                  required
                                  class="flex-1 bg-zinc-950 border border-zinc-800 text-white rounded-xl p-2.5 text-xs focus:outline-none focus:border-orange-500 resize-none"></textarea>
                        <button type="submit" 
                                class="px-4 bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 text-white font-bold rounded-xl text-xs flex items-center justify-center transition shrink-0">
                            <span>{{ __('Enviar') }}</span>
                        </button>
                    </div>
                </form>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-zinc-500 text-xs p-6">
                    <svg class="w-10 h-10 text-zinc-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    <p>{{ __('Selecione uma conversa ao lado para visualizar os detalhes.') }}</p>
                </div>
            @endif
        </div>

        <!-- Coluna 3: Ficha Resumida do Contato / CRM (Direita) -->
        <div class="lg:col-span-3 bg-zinc-900 border border-zinc-800 rounded-2xl p-4 overflow-y-auto space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                {{ __('Dados do Contato & CRM') }}
            </h3>

            @if($associatedLead)
                <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800/80 space-y-2">
                    <div>
                        <span class="text-[10px] text-zinc-500 font-bold uppercase">{{ __('Empresa / Negócio') }}</span>
                        <h4 class="text-xs font-bold text-white">{{ $associatedLead->company ?: __('A confirmar') }}</h4>
                    </div>
                    <div>
                        <span class="text-[10px] text-zinc-500 font-bold uppercase">{{ __('Etapa no Funil') }}</span>
                        <div class="mt-0.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-orange-500/10 text-orange-400 border border-orange-500/20">
                                {{ $stages[$associatedLead->stage] ?? $associatedLead->stage }}
                            </span>
                        </div>
                    </div>
                    @if($associatedLead->estimated_value)
                        <div>
                            <span class="text-[10px] text-zinc-500 font-bold uppercase">{{ __('Valor Estimado') }}</span>
                            <p class="text-xs font-bold text-emerald-400 font-mono">
                                € {{ number_format($associatedLead->estimated_value, 2, ',', '.') }}
                            </p>
                        </div>
                    @endif
                    @if($associatedLead->notes)
                        <div>
                            <span class="text-[10px] text-zinc-500 font-bold uppercase">{{ __('Resumo / Notas') }}</span>
                            <p class="text-[11px] text-zinc-300 whitespace-pre-wrap leading-relaxed mt-0.5">
                                {{ $associatedLead->notes }}
                            </p>
                        </div>
                    @endif
                    <div class="pt-2">
                        <a href="{{ route('crm.leads.show', $associatedLead) }}" 
                           class="block text-center px-3 py-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-bold border border-zinc-700 transition">
                            {{ __('Ver Ficha 360° no CRM') }} →
                        </a>
                    </div>
                </div>
            @else
                <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800/80 text-center text-xs text-zinc-500">
                    <p>{{ __('Contato ainda sem lead aberto no CRM.') }}</p>
                </div>
            @endif

            <!-- Produções Ativas -->
            @if($associatedProductions->isNotEmpty())
                <div class="space-y-2">
                    <span class="text-[10px] text-zinc-500 font-bold uppercase">{{ __('Produções em Andamento') }}</span>
                    @foreach($associatedProductions as $prod)
                        <a href="{{ route('productions.show', $prod) }}" 
                           class="block p-2.5 rounded-xl bg-zinc-950 border border-zinc-800/70 hover:border-zinc-700 text-xs transition">
                            <h5 class="font-bold text-white truncate">{{ $prod->title }}</h5>
                            <span class="text-[10px] text-zinc-400 capitalize">{{ $prod->status }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            <!-- Dica de IA -->
            <div class="p-3 rounded-xl bg-orange-950/20 border border-orange-500/20 text-orange-300 text-[11px] space-y-1">
                <p class="font-bold">💡 {{ __('Memória Cognitiva Sofi') }}</p>
                <p class="text-zinc-400 text-[10px] leading-relaxed">
                    {{ __('Todas as mensagens desta conversa alimentam o aprendizado da Sofi para refinar roteiros, propostas e o atendimento dos próximos clientes.') }}
                </p>
            </div>
        </div>

    </div>
</div>

<script>
    // Rolagem automática para a última mensagem ao carregar a conversa
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('messagesContainer');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }

        // Filtro em tempo real de conversas
        const searchInput = document.getElementById('searchConversation');
        function applySearch() {
            const term = (searchInput?.value || '').toLowerCase().trim();
            document.querySelectorAll('.conversation-item').forEach(function(item) {
                item.classList.toggle('hidden', term !== '' && !item.textContent.toLowerCase().includes(term));
            });
        }
        searchInput?.addEventListener('input', applySearch);

        // Atualização automática: a cada 4 s (só com a aba visível) busca balões novos da conversa
        // aberta e redesenha a lista lateral quando algo mudou. Mudança de status recarrega a página.
        const list = document.getElementById('conversationList');
        const state = {
            conversationId: @json($activeConversation?->id),
            lastId: @json($activeConversation?->messages->last()?->id ?? 0),
            status: @json($activeConversation?->status),
            signature: null,
            busy: false,
        };

        async function refreshInbox() {
            if (document.hidden || state.busy) {
                return;
            }
            state.busy = true;

            try {
                const params = new URLSearchParams({ after: state.lastId });
                if (state.conversationId) params.set('conversation_id', state.conversationId);
                if (state.signature) params.set('signature', state.signature);

                const response = await fetch(@json(route('inbox.poll')) + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) return;
                const data = await response.json();

                if (state.status && data.status && data.status !== state.status) {
                    window.location.reload();
                    return;
                }

                if (container && data.messages_html) {
                    const nearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 160;
                    document.getElementById('messagesEmpty')?.remove();
                    container.insertAdjacentHTML('beforeend', data.messages_html);
                    if (nearBottom) container.scrollTop = container.scrollHeight;
                }
                state.lastId = data.last_id;

                if (list && state.signature !== null && data.list_html !== null) {
                    list.innerHTML = data.list_html;
                    applySearch();
                }
                state.signature = data.signature;
            } catch (error) {
                // Rede instável: tenta de novo na próxima volta.
            } finally {
                state.busy = false;
            }
        }

        setInterval(refreshInbox, 4000);
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) refreshInbox();
        });
    });
</script>
@endsection
