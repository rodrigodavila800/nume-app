{{-- Tipo de vídeo do roteiro. Espera $videoTypes e $selectedVideoType (id ou null); sincroniza com o hidden #videoTypeId do formulário. --}}
<div class="space-y-1">
    <label for="sofiVideoType" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Tipo de vídeo') }}</label>
    <select id="sofiVideoType" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-orange-500 transition">
        @foreach($videoTypes as $videoType)
            <option value="{{ $videoType->id }}" data-blocks="{{ collect($videoType->blocks)->map(fn ($b) => $b['name'].' '.$b['start'].'–'.$b['end'].'s')->implode(' · ') }}" @selected((int) $selectedVideoType === $videoType->id)>
                🎬 {{ $videoType->name }}
            </option>
        @endforeach
        <option value="" @selected(! $selectedVideoType)>{{ __('Sem tipo (framework clássico)') }}</option>
    </select>
    <p id="sofiVideoTypeBlocks" class="text-[10px] text-zinc-500 font-mono"></p>
</div>

<script>
(function () {
    const select = document.getElementById('sofiVideoType');
    const blocks = document.getElementById('sofiVideoTypeBlocks');

    function sync() {
        const option = select.options[select.selectedIndex];
        const hidden = document.getElementById('videoTypeId');
        const framework = document.getElementById('sofiFramework');
        if (hidden) hidden.value = select.value;
        if (framework) framework.disabled = select.value !== '';
        blocks.textContent = option ? (option.dataset.blocks || '') : '';
    }

    select.addEventListener('change', sync);
    document.addEventListener('DOMContentLoaded', sync);
})();
</script>
