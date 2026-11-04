{{-- Lista lateral de conversas (página e atualização automática do painel). --}}
@forelse($conversations as $c)
    @php
        $isActive = $activeConversation && $activeConversation->id === $c->id;
        $displayName = $c->cliente?->name ?? ($c->contact_phone ? "+{$c->contact_phone}" : $c->title);
        $snippet = $c->latestMessage?->content ?? __('Sem mensagens');
    @endphp
    <a href="{{ route('inbox.index', ['conversation_id' => $c->id]) }}" 
       class="conversation-item block p-3 transition {{ $isActive ? 'bg-zinc-800/90 border-l-4 border-orange-500' : 'hover:bg-zinc-800/40' }}">
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center text-white text-xs font-extrabold shrink-0">
                    {{ mb_strtoupper(mb_substr($displayName, 0, 2)) }}
                </div>
                <div class="min-w-0">
                    <h4 class="text-xs font-bold text-white truncate">{{ $displayName }}</h4>
                    <p class="text-[11px] text-zinc-400 font-mono truncate">
                        {{ $c->contact_phone ? "+{$c->contact_phone}" : __('WhatsApp') }}
                    </p>
                </div>
            </div>
            <span class="text-[10px] text-zinc-500 whitespace-nowrap shrink-0">
                {{ $c->latestMessage?->created_at?->diffForHumans(null, true) }}
            </span>
        </div>
        @if($c->status === 'needs_human')
            <span class="inline-block mt-1.5 ml-11 px-2 py-0.5 rounded text-[10px] font-bold bg-red-500/10 text-red-400 border border-red-500/20">
                {{ __('Precisa de você') }}
            </span>
        @endif
        @if($c->status === 'human' && $c->latestMessage?->sender === 'user')
            <span class="inline-block mt-1.5 ml-11 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                {{ __('Cliente respondeu') }}
            </span>
        @endif
        <p class="text-xs text-zinc-400 line-clamp-1 mt-1.5 pl-11">
            @if($c->latestMessage?->sender === 'sofi')
                @if(!empty($c->latestMessage->metadata['is_human']))
                    <span class="text-emerald-400 font-semibold">{{ __('Você:') }}</span>
                @else
                    <span class="text-orange-400 font-semibold">{{ __('Sofi:') }}</span>
                @endif
            @endif
            {{ $snippet }}
        </p>
    </a>
@empty
    <div class="p-8 text-center text-zinc-500 text-xs">
        <svg class="w-8 h-8 mx-auto text-zinc-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <p>{{ __('Nenhuma conversa no WhatsApp ainda.') }}</p>
        <p class="text-[11px] text-zinc-600 mt-1">{{ __('Quando um lead mandar mensagem no WhatsApp oficial, a conversa aparecerá aqui automaticamente.') }}</p>
    </div>
@endforelse
