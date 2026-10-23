<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-zinc-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Aprovação de Vídeo Final') }} | {{ $video->title ?: $production->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full bg-zinc-950 text-zinc-100 font-sans antialiased selection:bg-orange-500 selection:text-white py-6 px-4 sm:px-6 lg:px-8">

    <div class="max-w-2xl mx-auto space-y-6">

        <!-- Top Header: Brand & Security -->
        <div class="flex items-center justify-between pb-4 border-b border-zinc-800">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-orange-600 flex items-center justify-center font-black text-white text-base shadow-lg shadow-orange-600/30">N</span>
                <div>
                    <p class="text-xs font-black tracking-wider uppercase text-zinc-100">NUME</p>
                    <p class="text-[10px] text-zinc-400">{{ __('Aprovação Instantânea') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span>{{ __('Link Seguro Sem Senha') }}</span>
            </div>
        </div>

        <!-- Feedback Alerts -->
        @if(session('success'))
            <div class="rounded-2xl bg-emerald-950/40 border border-emerald-500/40 p-4 text-emerald-300 text-xs font-semibold flex items-start gap-3">
                <span class="text-lg">🎉</span>
                <div>
                    <p class="font-bold text-emerald-200">{{ __('Vídeo Aprovado!') }}</p>
                    <p class="mt-0.5">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        <!-- Card do Vídeo -->
        <div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-5 sm:p-6 shadow-2xl space-y-5">
            
            <!-- Metadata do Vídeo -->
            <div>
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-orange-400">
                        {{ $cliente?->company_name ?: ($cliente?->name ?? 'Cliente') }}
                    </span>
                    @if($video->status === 'ready' || $video->status === 'published')
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            ✅ {{ __('Vídeo Final Aprovado') }}
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500/20 text-amber-300 border border-amber-500/30 animate-pulse">
                            ⏳ {{ __('Aguardando Sua Aprovação') }}
                        </span>
                    @endif
                </div>

                <h1 class="text-xl sm:text-2xl font-black text-white leading-tight">
                    {{ $video->title ?: $production->title }}
                </h1>
                @if($video->description)
                    <p class="text-xs text-zinc-400 mt-1">
                        {{ $video->description }}
                    </p>
                @endif
            </div>

            <!-- Player de Vídeo -->
            <div class="rounded-2xl overflow-hidden bg-black border border-zinc-800 aspect-video flex items-center justify-center relative shadow-inner">
                @if($video->embed_url)
                    <iframe src="{{ $video->embed_url }}"
                            class="w-full h-full border-0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen></iframe>
                @elseif($video->video_url)
                    <video src="{{ $video->video_url }}"
                           controls
                           preload="metadata"
                           class="w-full h-full object-contain"></video>
                @else
                    <div class="text-center p-6 text-zinc-500">
                        <span class="text-3xl block mb-2">🎬</span>
                        <p class="text-xs font-semibold">{{ __('Vídeo em processamento de renderização.') }}</p>
                    </div>
                @endif
            </div>

            <!-- Botão de Download / Link Externo -->
            @if($video->video_url)
                <div class="flex items-center justify-between text-xs text-zinc-400 px-1">
                    <span>{{ __('Arquivo Original em Alta Resolução:') }}</span>
                    <a href="{{ $video->video_url }}" target="_blank" rel="noopener noreferrer"
                       class="text-orange-400 hover:text-orange-300 font-bold underline flex items-center gap-1">
                        <span>{{ __('Abrir / Baixar Vídeo') }}</span>
                        <span>↗</span>
                    </a>
                </div>
            @endif

            <!-- Painel de Ações do Cliente -->
            @if($video->status !== 'ready' && $video->status !== 'published')
                <div class="border-t border-zinc-800 pt-5">
                    <form method="POST" action="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('magic.videos.approve', now()->addMinutes(60), ['video' => $video->id]) }}">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 py-4 px-6 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-sm shadow-xl shadow-emerald-600/30 hover:scale-[1.01] active:scale-[0.99] transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>{{ __('Aprovar Vídeo Final (1-Clique)') }}</span>
                        </button>
                    </form>
                </div>
            @else
                <div class="border-t border-zinc-800 pt-4 text-center">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-bold">
                        <span>✨</span>
                        <span>{{ __('Vídeo Final já Aprovado! Pronto para distribuição.') }}</span>
                    </div>
                </div>
            @endif

        </div>

        <!-- Footer -->
        <div class="text-center text-[10px] text-zinc-500 space-y-1">
            <p>{{ __('Este link é pessoal e intransferível. Criptografado com padrão HMAC SHA-256.') }}</p>
            <p>© {{ date('Y') }} NUME — {{ __('Ecossistema Audiovisual de Alta Performance') }}</p>
        </div>

    </div>

</body>
</html>
