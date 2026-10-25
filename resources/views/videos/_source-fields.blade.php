{{-- Origem do vídeo: arquivo enviado para a NUME (recomendado) OU link externo. Uso: @include('videos._source-fields', ['video' => $video ?? null]) --}}
@php($video = $video ?? null)
@php($maxMb = (int) config('social.video.max_upload_mb'))

<div class="space-y-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-4">
    <p class="text-xs font-bold uppercase tracking-wider text-zinc-300">
        {{ __('Vídeo') }} <span class="text-orange-500">*</span>
        <span class="ml-1 font-medium normal-case tracking-normal text-zinc-500">{{ __('envie o arquivo ou informe um link') }}</span>
    </p>

    @if ($video?->hasSourceFile())
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-800/60 bg-emerald-950/30 px-3 py-2 text-xs text-emerald-300">
            <span>
                {{ __('Arquivo hospedado na NUME') }}
                @if ($video->source_size) · {{ number_format($video->source_size / 1048576, 1, ',', '.') }} MB @endif
                @if ($video->source_width && $video->source_height) · {{ $video->source_width }}×{{ $video->source_height }} @endif
            </span>
            <label for="remove_video_file" class="inline-flex items-center gap-2 text-zinc-300">
                <input type="checkbox" name="remove_video_file" id="remove_video_file" value="1" @checked(old('remove_video_file'))
                       class="rounded border-zinc-700 bg-zinc-900 text-orange-600 focus:ring-orange-500">
                {{ __('Remover arquivo (exige um link abaixo)') }}
            </label>
        </div>
    @endif

    <div class="space-y-1.5">
        <label for="video_file" class="block text-xs font-semibold text-zinc-300">
            {{ $video?->hasSourceFile() ? __('Substituir arquivo de vídeo') : __('Arquivo de vídeo (MP4 ou MOV)') }}
        </label>
        <input type="file" name="video_file" id="video_file" accept="video/mp4,video/quicktime,.mp4,.mov"
               class="block w-full text-sm text-zinc-300 file:mr-3 file:rounded-lg file:border-0 file:bg-orange-600 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white hover:file:bg-orange-500 @error('video_file') text-rose-400 @enderror">
        <p class="text-[11px] text-zinc-500">{{ __('Até :max MB. Necessário para publicar nas redes pela NUME.', ['max' => $maxMb]) }}</p>
        @error('video_file') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="space-y-1.5">
        <label for="video_url" class="block text-xs font-semibold text-zinc-300">
            {{ __('Ou link do vídeo (YouTube, Shorts, Vimeo, Google Drive ou MP4)') }}
        </label>
        <input type="url" name="video_url" id="video_url"
               placeholder="https://www.youtube.com/shorts/... ou https://drive.google.com/..."
               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('video_url') border-rose-500 @enderror"
               value="{{ old('video_url', $video?->video_url) }}">
        @error('video_url') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
</div>
