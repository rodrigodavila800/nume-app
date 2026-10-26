{{-- Cópia da estrutura gravada no roteiro (não muda se o admin editar o tipo depois). --}}
@if(! empty($roteiro->structure['blocks']))
    <div class="space-y-2">
        <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">
            {{ __('Tipo de vídeo') }}: <span class="text-orange-400">{{ $roteiro->structure['name'] ?? '' }}</span>
            · {{ $roteiro->structure['duration_seconds'] ?? '' }} s
        </p>
        <ol class="flex flex-wrap gap-2">
            @foreach($roteiro->structure['blocks'] as $block)
                <li class="px-2.5 py-1 rounded-lg text-[11px] bg-zinc-800 text-zinc-200 border border-zinc-700" title="{{ $block['purpose'] ?? '' }}">
                    <span class="font-bold">{{ $block['name'] }}</span>
                    <span class="text-zinc-400">{{ $block['start'] }}–{{ $block['end'] }} s</span>
                </li>
            @endforeach
        </ol>
    </div>
@endif
