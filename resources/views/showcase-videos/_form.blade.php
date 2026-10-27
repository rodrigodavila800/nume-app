<div class="space-y-4">
    <div class="space-y-1.5">
        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
            {{ __('Título do Vídeo') }} <span class="text-orange-500">*</span>
        </label>
        <input name="title" value="{{ old('title', $showcaseVideo->title ?? '') }}" required
               placeholder="Ex: Comercial Manifesto B2B"
               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('title') border-rose-500 @enderror">
        @error('title') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="space-y-1.5">
        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
            {{ __('Descrição Estratégica') }}
        </label>
        <textarea name="description" rows="3"
                  placeholder="Ex: Produção desenvolvida para captação de leads qualificados..."
                  class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">{{ old('description', $showcaseVideo->description ?? '') }}</textarea>
    </div>

    <!-- Formato & Orientação do Vídeo (Vertical 9:16 vs Horizontal 16:9) -->
    <div class="space-y-2 pt-1 pb-1">
        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
            {{ __('Formato & Orientação do Vídeo') }} <span class="text-orange-500">*</span>
        </label>
        @php
            $isVerticalVal = old('is_vertical', isset($showcaseVideo) ? ($showcaseVideo->is_vertical ? '1' : '0') : '1');
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label id="format-vertical-card"
                   class="flex items-center gap-3.5 p-3.5 rounded-xl border cursor-pointer transition select-none {{ $isVerticalVal == '1' ? 'border-orange-500 bg-orange-950/25 ring-1 ring-orange-500/50' : 'border-zinc-800 bg-zinc-950/70 hover:border-zinc-700' }}">
                <input type="radio" name="is_vertical" value="1" @checked($isVerticalVal == '1')
                       class="sr-only format-radio" id="radio-vertical" onchange="updateFormatCards()">
                <div class="w-10 h-10 rounded-lg bg-orange-600/20 border border-orange-500/30 flex items-center justify-center text-xl shrink-0">
                    
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-bold text-white flex items-center gap-1.5">
                        {{ __('Vertical (9:16)') }}
                        <span class="text-[10px] uppercase font-black px-1.5 py-0.5 rounded bg-orange-600 text-white">Feed / Shorts</span>
                    </span>
                    <span class="text-[11px] text-zinc-400 mt-0.5">
                        {{ __('Instagram Reels, TikTok, YouTube Shorts e Stories.') }}
                    </span>
                </div>
            </label>

            <label id="format-horizontal-card"
                   class="flex items-center gap-3.5 p-3.5 rounded-xl border cursor-pointer transition select-none {{ $isVerticalVal == '0' ? 'border-orange-500 bg-orange-950/25 ring-1 ring-orange-500/50' : 'border-zinc-800 bg-zinc-950/70 hover:border-zinc-700' }}">
                <input type="radio" name="is_vertical" value="0" @checked($isVerticalVal == '0')
                       class="sr-only format-radio" id="radio-horizontal" onchange="updateFormatCards()">
                <div class="w-10 h-10 rounded-lg bg-cyan-600/20 border border-cyan-500/30 flex items-center justify-center text-xl shrink-0">
                    
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-bold text-white flex items-center gap-1.5">
                        {{ __('Horizontal (16:9)') }}
                        <span class="text-[10px] uppercase font-black px-1.5 py-0.5 rounded bg-cyan-600 text-white">Cinema / TV</span>
                    </span>
                    <span class="text-[11px] text-zinc-400 mt-0.5">
                        {{ __('YouTube Padrão, Institucional, Widescreen e Desktop.') }}
                    </span>
                </div>
            </label>
        </div>
    </div>

    <div class="space-y-1.5">
        <div class="flex items-center justify-between">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Link do Vídeo (YouTube, Vimeo, etc.)') }} <span class="text-orange-500">*</span>
            </label>
            <span id="detected-format-badge" class="text-[10px] font-mono text-orange-400 hidden"></span>
        </div>
        <input type="url" name="video_url" id="video_url_input" value="{{ old('video_url', $showcaseVideo->video_url ?? '') }}" required
               placeholder="https://youtube.com/shorts/... ou https://youtube.com/watch?v=..."
               oninput="autoDetectVideoFormat(this.value)"
               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('video_url') border-rose-500 @enderror">
        @error('video_url') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="space-y-1.5">
        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
            {{ __('Link da Imagem de Capa (Thumbnail)') }}
        </label>
        <input type="url" name="thumbnail_url" value="{{ old('thumbnail_url', $showcaseVideo->thumbnail_url ?? '') }}"
               placeholder="https://... (Deixe vazio para buscar automaticamente a capa oficial do vídeo)"
               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">
        <p class="text-[11px] text-zinc-500 flex items-center gap-1.5">
            <span>{{ __('Se deixar em branco, o sistema extrai e utiliza a capa em alta definição do próprio vídeo (YouTube/Vimeo) automaticamente.') }}</span>
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Categoria Estratégica') }} <span class="text-orange-500">*</span>
            </label>
            <select name="category_id"
                    class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition">
                <option value="">{{ __('Selecione uma categoria...') }}</option>
                @foreach($categories ?? [] as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $showcaseVideo->category_id ?? '') == $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            @error('category_id') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Ordem de Exibição') }}
            </label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $showcaseVideo->sort_order ?? 0) }}" required
                   class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition">
        </div>
    </div>

    <div class="flex items-center gap-6 pt-2">
        <label class="flex items-center gap-2 text-xs font-bold text-zinc-300 cursor-pointer">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $showcaseVideo->is_published ?? false))
                   class="w-4 h-4 rounded border-zinc-700 bg-zinc-800 text-orange-600 focus:ring-orange-500">
            <span>{{ __('Publicar na Vitrine') }}</span>
        </label>
        <label class="flex items-center gap-2 text-xs font-bold text-zinc-300 cursor-pointer">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $showcaseVideo->is_featured ?? false))
                   class="w-4 h-4 rounded border-zinc-700 bg-zinc-800 text-orange-600 focus:ring-orange-500">
            <span>{{ __('Destaque Especial') }}</span>
        </label>
    </div>
</div>

<script>
    function updateFormatCards() {
        const isVertical = document.getElementById('radio-vertical').checked;
        const cardV = document.getElementById('format-vertical-card');
        const cardH = document.getElementById('format-horizontal-card');
        
        if (isVertical) {
            cardV.className = 'flex items-center gap-3.5 p-3.5 rounded-xl border cursor-pointer transition select-none border-orange-500 bg-orange-950/25 ring-1 ring-orange-500/50';
            cardH.className = 'flex items-center gap-3.5 p-3.5 rounded-xl border cursor-pointer transition select-none border-zinc-800 bg-zinc-950/70 hover:border-zinc-700';
        } else {
            cardH.className = 'flex items-center gap-3.5 p-3.5 rounded-xl border cursor-pointer transition select-none border-orange-500 bg-orange-950/25 ring-1 ring-orange-500/50';
            cardV.className = 'flex items-center gap-3.5 p-3.5 rounded-xl border cursor-pointer transition select-none border-zinc-800 bg-zinc-950/70 hover:border-zinc-700';
        }
    }

    function autoDetectVideoFormat(url) {
        if (!url) return;
        const badge = document.getElementById('detected-format-badge');
        const lowerUrl = url.toLowerCase();
        
        if (lowerUrl.includes('/shorts/') || lowerUrl.includes('/reel/') || lowerUrl.includes('tiktok.com')) {
            document.getElementById('radio-vertical').checked = true;
            updateFormatCards();
            if (badge) {
                badge.innerText = 'Detectado: Vertical (Shorts/Reels)';
                badge.classList.remove('hidden');
            }
        } else if (lowerUrl.includes('watch?v=') || lowerUrl.includes('vimeo.com')) {
            document.getElementById('radio-horizontal').checked = true;
            updateFormatCards();
            if (badge) {
                badge.innerText = 'Detectado: Horizontal (Widescreen)';
                badge.classList.remove('hidden');
            }
        }
    }
</script>
