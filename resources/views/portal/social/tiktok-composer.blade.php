@extends('layouts.portal')

@section('title', __('Publicar no TikTok') . ' | NUME')

@section('content')
@php
    $privacyLabels = [
        'PUBLIC_TO_EVERYONE' => __('Todos'),
        'MUTUAL_FOLLOW_FRIENDS' => __('Amigos (seguidores mútuos)'),
        'FOLLOWER_OF_CREATOR' => __('Seguidores'),
        'SELF_ONLY' => __('Somente eu'),
    ];
    $interactions = [
        'allow_comment' => ['label' => __('Permitir comentários'), 'disabled' => $creator['comment_disabled']],
        'allow_duet' => ['label' => __('Permitir dueto'), 'disabled' => $creator['duet_disabled']],
        'allow_stitch' => ['label' => __('Permitir stitch'), 'disabled' => $creator['stitch_disabled']],
    ];
@endphp
<div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Publicar no TikTok') }}</h1>
        <p class="text-xs sm:text-sm text-zinc-600 mt-1">
            {{ __('O vídeo será publicado na conta') }}
            <span class="font-bold text-zinc-900">{{ $creator['nickname'] ?? '' }}</span>
            @if($creator['username'])
                <span class="text-zinc-500">({{ '@'.ltrim($creator['username'], '@') }})</span>
            @endif
        </p>
    </div>

    <form id="tiktok-form" action="{{ route('portal.videos.tiktok.store', $video) }}" method="POST" class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        @csrf

        <div class="lg:col-span-2">
            <div class="bg-zinc-950 rounded-2xl overflow-hidden border border-zinc-200 shadow-sm aspect-[9/16] max-h-[70vh] mx-auto">
                <video src="{{ $video->playbackUrl() }}" controls playsinline controlsList="nodownload" class="w-full h-full object-contain"></video>
            </div>
            <p class="text-[11px] text-zinc-500 mt-2 text-center">{{ __('Prévia do vídeo que será enviado.') }}</p>
        </div>

        <div class="lg:col-span-3 space-y-5">
            @if($tooLong)
                <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 font-semibold">
                    {{ __('Este vídeo tem :duracao s e a sua conta do TikTok aceita até :max s. Não é possível publicá-lo nesta conta.', ['duracao' => $video->duration_seconds, 'max' => $creator['max_video_post_duration_sec']]) }}
                </div>
            @endif

            <div>
                <label for="title" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Legenda') }}</label>
                <textarea id="title" name="title" rows="5" maxlength="2200"
                    class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors">{{ old('title', $title) }}</textarea>
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Você pode editar a legenda e as hashtags sugeridas pela Sofi.') }}</p>
                @error('title')<p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="privacy_level" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Quem pode ver este vídeo') }}</label>
                <select id="privacy_level" name="privacy_level" required
                    class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 bg-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors">
                    <option value="" disabled @selected(! old('privacy_level'))>{{ __('Escolha uma opção') }}</option>
                    @foreach($creator['privacy_level_options'] as $option)
                        <option value="{{ $option }}" @selected(old('privacy_level') === $option)>{{ $privacyLabels[$option] ?? $option }}</option>
                    @endforeach
                </select>
                <p id="private-branded-hint" class="hidden text-[11px] text-amber-700 mt-1">{{ __('Conteúdo de marca não pode ser privado.') }}</p>
                @error('privacy_level')<p class="text-xs text-rose-600 font-medium mt-1">{{ $message }}</p>@enderror
            </div>

            <fieldset class="space-y-2">
                <legend class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">{{ __('Interações') }}</legend>
                @foreach($interactions as $name => $interaction)
                    <label class="flex items-center gap-2 text-sm {{ $interaction['disabled'] ? 'text-zinc-400 cursor-not-allowed' : 'text-zinc-800 cursor-pointer' }}">
                        <input type="hidden" name="{{ $name }}" value="0">
                        <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name)) @disabled($interaction['disabled'])
                            class="rounded border-zinc-300 text-orange-600 focus:ring-orange-500 disabled:opacity-50">
                        <span>{{ $interaction['label'] }}</span>
                        @if($interaction['disabled'])
                            <span class="text-[11px]">{{ __('(desativado nas configurações da sua conta do TikTok)') }}</span>
                        @endif
                    </label>
                @endforeach
            </fieldset>

            <fieldset class="p-4 rounded-2xl border border-zinc-200 bg-white space-y-3">
                <label class="flex items-center justify-between gap-3 cursor-pointer">
                    <span>
                        <span class="block text-sm font-bold text-zinc-900">{{ __('Divulgar conteúdo comercial') }}</span>
                        <span class="block text-[11px] text-zinc-500">{{ __('Ative se o vídeo promove a sua marca, um produto ou um parceiro.') }}</span>
                    </span>
                    <input type="hidden" name="commercial" value="0">
                    <input type="checkbox" id="commercial" name="commercial" value="1" @checked(old('commercial'))
                        class="rounded border-zinc-300 text-orange-600 focus:ring-orange-500">
                </label>

                <div id="commercial-options" class="hidden space-y-2 pt-2 border-t border-zinc-100">
                    <label class="flex items-start gap-2 text-sm text-zinc-800 cursor-pointer">
                        <input type="hidden" name="brand_organic" value="0">
                        <input type="checkbox" id="brand_organic" name="brand_organic" value="1" @checked(old('brand_organic'))
                            class="mt-0.5 rounded border-zinc-300 text-orange-600 focus:ring-orange-500">
                        <span><span class="font-semibold">{{ __('Sua marca') }}</span> <span class="block text-[11px] text-zinc-500">{{ __('Você está promovendo a si mesmo ou o seu negócio.') }}</span></span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-zinc-800 cursor-pointer">
                        <input type="hidden" name="brand_content" value="0">
                        <input type="checkbox" id="brand_content" name="brand_content" value="1" @checked(old('brand_content'))
                            class="mt-0.5 rounded border-zinc-300 text-orange-600 focus:ring-orange-500">
                        <span><span class="font-semibold">{{ __('Conteúdo de marca') }}</span> <span class="block text-[11px] text-zinc-500">{{ __('Você está promovendo outra marca ou um terceiro.') }}</span></span>
                    </label>
                    <p id="label-organic" class="hidden text-[11px] text-zinc-600">{{ __('Seu vídeo será marcado como "Conteúdo promocional".') }}</p>
                    <p id="label-branded" class="hidden text-[11px] text-zinc-600">{{ __('Seu vídeo será marcado como "Parceria paga".') }}</p>
                    @error('commercial')<p class="text-xs text-rose-600 font-medium">{{ $message }}</p>@enderror
                </div>
            </fieldset>

            <div class="text-[11px] text-zinc-600 space-y-1">
                <p id="consent-default">
                    {{ __('Ao publicar, você concorda com a') }}
                    <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" rel="noopener noreferrer" class="text-orange-600 font-semibold underline">{{ __('Confirmação de Uso de Música do TikTok') }}</a>.
                </p>
                <p id="consent-branded" class="hidden">
                    {{ __('Ao publicar, você concorda com a') }}
                    <a href="https://www.tiktok.com/legal/page/global/bc-policy/en" target="_blank" rel="noopener noreferrer" class="text-orange-600 font-semibold underline">{{ __('Política de Conteúdo de Marca') }}</a>
                    {{ __('e com a') }}
                    <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" rel="noopener noreferrer" class="text-orange-600 font-semibold underline">{{ __('Confirmação de Uso de Música do TikTok') }}</a>.
                </p>
                <p class="text-zinc-500">{{ __('Depois de publicar, o processamento pode levar alguns minutos até o vídeo aparecer no seu perfil.') }}</p>
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-zinc-100">
                <a href="{{ route('portal.productions.show', $video->production->uuid) }}"
                    class="inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-700 bg-white border border-zinc-200 hover:bg-zinc-50 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-zinc-400">
                    {{ __('Cancelar') }}
                </a>
                <span id="publish-wrapper" class="inline-flex" title="">
                    <button type="submit" id="publish-button" @disabled($tooLong)
                        class="w-full inline-flex justify-center items-center px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-zinc-950 hover:bg-orange-600 shadow-sm transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-zinc-950">
                        {{ __('Publicar no TikTok') }}
                    </button>
                </span>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const commercial = document.getElementById('commercial');
    const options = document.getElementById('commercial-options');
    const organic = document.getElementById('brand_organic');
    const branded = document.getElementById('brand_content');
    const privacy = document.getElementById('privacy_level');
    const privateOption = privacy.querySelector('option[value="SELF_ONLY"]');
    const button = document.getElementById('publish-button');
    const wrapper = document.getElementById('publish-wrapper');
    const tooLong = {{ $tooLong ? 'true' : 'false' }};
    const texts = {
        needSelection: @json(__('Você precisa indicar se o conteúdo promove você, um terceiro ou ambos.')),
        brandedPrivate: @json(__('A visibilidade de conteúdo de marca não pode ser "Somente eu".')),
    };

    function toggle(id, show) {
        document.getElementById(id).classList.toggle('hidden', !show);
    }

    function refresh() {
        const on = commercial.checked;
        const isBranded = on && branded.checked;
        const isOrganic = on && organic.checked;

        options.classList.toggle('hidden', !on);
        toggle('label-organic', isOrganic && !isBranded);
        toggle('label-branded', isBranded);
        toggle('consent-default', !isBranded);
        toggle('consent-branded', isBranded);

        // Conteúdo de marca não pode ser privado: "Somente eu" fica indisponível.
        if (privateOption) {
            privateOption.disabled = isBranded;
            privateOption.title = isBranded ? texts.brandedPrivate : '';
            if (isBranded && privacy.value === 'SELF_ONLY') {
                privacy.value = '';
            }
        }
        toggle('private-branded-hint', isBranded);

        const missingSelection = on && !organic.checked && !branded.checked;
        button.disabled = tooLong || missingSelection;
        wrapper.title = missingSelection ? texts.needSelection : '';
    }

    [commercial, organic, branded, privacy].forEach((el) => el.addEventListener('change', refresh));
    refresh();
})();
</script>
@endsection
