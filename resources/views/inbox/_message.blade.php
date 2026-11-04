{{-- Um balão da conversa (página e atualização automática do painel). Precisa de $m e $activeConversation. --}}
@php
    $isHuman = !empty($m->metadata['is_human']);
@endphp
@if($m->sender === 'user')
    <!-- Mensagem do Lead/Cliente (Esquerda) -->
    <div class="flex items-start gap-2 max-w-[85%]">
        <div class="w-6 h-6 rounded-full bg-zinc-800 text-zinc-400 flex items-center justify-center text-[10px] font-bold shrink-0 mt-1">
            {{ mb_strtoupper(mb_substr($activeConversation->cliente?->name ?? 'U', 0, 1)) }}
        </div>
        <div class="p-3 rounded-2xl rounded-tl-sm bg-zinc-800/90 text-white text-xs border border-zinc-700/60 shadow-sm space-y-1">
            <p class="whitespace-pre-wrap leading-relaxed">{{ $m->content }}</p>
            <div class="text-[9px] text-zinc-400 text-right">{{ $m->created_at->format('H:i') }}</div>
        </div>
    </div>
@elseif($isHuman)
    <!-- Mensagem Manual da Equipe da Produtora (Direita) -->
    <div class="flex items-start justify-end gap-2 max-w-[85%] ml-auto">
        <div class="p-3 rounded-2xl rounded-tr-sm bg-emerald-950/40 border border-emerald-500/30 text-zinc-100 text-xs shadow-sm space-y-1.5">
            <div class="flex items-center justify-between gap-2 border-b border-emerald-500/20 pb-1">
                <span class="text-[10px] font-bold text-emerald-400 flex items-center gap-1">
                    <span>👤</span>
                    <span>{{ $m->metadata['user_name'] ?? __('Sua Equipe') }}</span>
                </span>
                <span class="text-[9px] text-zinc-400">{{ $m->created_at->format('H:i') }}</span>
            </div>
            <p class="whitespace-pre-wrap leading-relaxed">{{ $m->content }}</p>
        </div>
        <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[9px] font-bold shrink-0 mt-1">
            EQ
        </div>
    </div>
@else
    <!-- Resposta Automática da Sofi IA (Direita) -->
    <div class="flex items-start justify-end gap-2 max-w-[85%] ml-auto">
        <div class="p-3 rounded-2xl rounded-tr-sm bg-gradient-to-br from-orange-950/40 to-zinc-900 border border-orange-500/30 text-zinc-100 text-xs shadow-sm space-y-1.5">
            <div class="flex items-center justify-between gap-2 border-b border-orange-500/20 pb-1">
                <span class="text-[10px] font-bold text-orange-400 flex items-center gap-1">
                    <span>🤖</span>
                    <span>{{ __('Sofi IA') }}</span>
                </span>
                <span class="text-[9px] text-zinc-400">{{ $m->created_at->format('H:i') }}</span>
            </div>
            <p class="whitespace-pre-wrap leading-relaxed">{{ $m->content }}</p>
        </div>
        <div class="w-6 h-6 rounded-full bg-orange-600 text-white flex items-center justify-center text-[9px] font-black shrink-0 mt-1">
            S
        </div>
    </div>
@endif
