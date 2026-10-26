@extends('layouts.app')

@section('title', __('NUME | Editar Roteiro com Sofi AI'))
@section('breadcrumb', __('Editar Roteiro'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header & Breadcrumb -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2.5 mb-1">
                <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Editar Roteiro') }}</h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-orange-500/10 text-orange-400 border border-orange-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400 animate-pulse"></span>
                    Sofi /copywriter
                </span>
            </div>
            <p class="text-xs text-zinc-400">{{ __('Produção:') }} <strong class="text-orange-400">{{ $production->title }}</strong></p>
        </div>
        <a href="{{ route('roteiros.show', ['production' => $production, 'roteiro' => $roteiro]) }}" class="text-xs font-bold text-zinc-400 hover:text-white transition flex items-center gap-1.5">
            ← {{ __('Voltar ao roteiro') }}
        </a>
    </div>

    <!-- Sofi AI Copilot Copywriting Engine Hub -->
    <div class="p-6 rounded-2xl bg-gradient-to-b from-zinc-900 to-zinc-950 border border-orange-500/20 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-zinc-800">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-orange-600/20 border border-orange-500/30 text-orange-400 flex items-center justify-center text-base">
                    ✍️
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white flex items-center gap-1.5">
                        <span>{{ __('Regerar Narrativa com Sofi AI (/copywriter)') }}</span>
                    </h2>
                    <p class="text-[11px] text-zinc-400">{{ __('Substitua ou aprimore este roteiro com frameworks científicos de retenção.') }}</p>
                </div>
            </div>
            <span class="text-[10px] font-mono text-zinc-500">Frameworks Meta / YouTube</span>
        </div>

        @include('roteiros._video_type_select', ['selectedVideoType' => old('video_type_id', $roteiro->video_type_id)])

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="space-y-1">
                <label for="sofiFramework" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Estrutura / Framework') }}</label>
                <select id="sofiFramework" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-orange-500 transition">
                    <option value="pattern_interrupt">⚡ {{ __('Padrão de Interrupção (0-3s)') }}</option>
                    <option value="authority_interview">🎙️ {{ __('Autoridade Executiva / B2B') }}</option>
                    <option value="direct_offer">🔥 {{ __('Oferta Direta (Tráfego Pago)') }}</option>
                    <option value="storytelling_case">📖 {{ __('Storytelling & Case de Sucesso') }}</option>
                </select>
            </div>

            <div class="space-y-1">
                <label for="sofiTone" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Tom de Voz') }}</label>
                <select id="sofiTone" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-orange-500 transition">
                    <option value="persuasive">🎯 {{ __('Persuasivo & Enérgico') }}</option>
                    <option value="executive">👔 {{ __('Corporativo & Autoritário') }}</option>
                    <option value="empathic">🤝 {{ __('Didático & Empático') }}</option>
                </select>
            </div>

            <div class="space-y-1">
                <label for="sofiLanguage" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Idioma do Roteiro') }}</label>
                <select id="sofiLanguage" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-orange-500 transition">
                    <option value="pt_BR" {{ session('locale') === 'pt_BR' ? 'selected' : '' }}>🇧🇷 {{ __('Português (Brasil)') }}</option>
                    <option value="es" {{ session('locale') === 'es' ? 'selected' : '' }}>🇪🇸 {{ __('Español (España / Zaragoza)') }}</option>
                </select>
            </div>
        </div>

        <div class="pt-1 flex items-center justify-between">
            <p class="text-[11px] text-zinc-500">
                💡 {{ __('A Sofi utilizará o briefing desta produção para estruturar uma nova proposta de roteiro.') }}
            </p>
            <button type="button" id="btnGenerateSofi" onclick="generateSofiScript()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 active:scale-95 text-white text-xs font-extrabold uppercase tracking-wider shadow-md shadow-orange-900/20 transition-all">
                <span id="sofiSpinner" class="hidden animate-spin">🔄</span>
                <span id="sofiBtnText">✨ {{ __('Regerar com Sofi') }}</span>
            </button>
        </div>
    </div>

    @if ($roteiro->status === 'submitted')
        <div class="p-4 rounded-2xl bg-amber-950/60 border border-amber-500/40 text-amber-200 text-xs sm:text-sm flex items-start gap-3 shadow-lg">
            <span class="text-base leading-none">⚠️</span>
            <div class="space-y-0.5">
                <p class="font-bold text-amber-300">{{ __('Roteiro Aguardando Aprovação do Cliente') }}</p>
                <p class="text-zinc-300 text-xs">{{ __('Este roteiro já foi enviado ao cliente. Qualquer modificação feita aqui atualizará diretamente a proposta visível no Portal do Cliente.') }}</p>
            </div>
        </div>
    @elseif ($roteiro->status === 'rejected')
        <div class="p-4 rounded-2xl bg-rose-950/60 border border-rose-500/40 text-rose-200 text-xs sm:text-sm flex items-start gap-3 shadow-lg">
            <span class="text-base leading-none">✗</span>
            <div class="space-y-1">
                <p class="font-bold text-rose-300">{{ __('Roteiro Precisa de Ajustes') }}</p>
                @if($roteiro->rejection_reason)
                    <p class="text-xs text-rose-200/90 bg-rose-950/80 p-2.5 rounded-xl border border-rose-900/60 font-mono">{{ $roteiro->rejection_reason }}</p>
                @endif
                <p class="text-zinc-400 text-xs">{{ __('Ao salvar as alterações, este roteiro voltará para Rascunho para que você possa submetê-lo novamente.') }}</p>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form de Edição -->
    <form action="{{ route('roteiros.update', ['production' => $production, 'roteiro' => $roteiro]) }}" method="POST" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="video_type_id" id="videoTypeId" value="{{ old('video_type_id', $roteiro->video_type_id) }}">

        <div class="space-y-1.5">
            <div class="flex items-center justify-between">
                <label for="content" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Conteúdo do Roteiro') }} <span class="text-orange-500">*</span>
                </label>
                <span id="scriptBadge" class="hidden text-[10px] font-mono text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                    ✓ {{ __('Roteiro Atualizado pela Sofi') }}
                </span>
            </div>
            <textarea name="content" id="content" rows="18" required
                      class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm font-mono leading-relaxed focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('content') border-rose-500 @enderror">{{ old('content', $roteiro->content) }}</textarea>
            @error('content') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-zinc-800">
            <button type="submit"
                    class="flex-1 sm:flex-initial bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-lg shadow-orange-900/20 transition-all">
                {{ __('Atualizar Roteiro') }}
            </button>
            <a href="{{ route('roteiros.show', ['production' => $production, 'roteiro' => $roteiro]) }}"
               class="flex-1 sm:flex-initial inline-flex items-center justify-center bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm px-6 py-2.5 rounded-xl transition">
                {{ __('Cancelar') }}
            </a>
        </div>
    </form>
</div>

<script>
    async function generateSofiScript() {
        const btn = document.getElementById('btnGenerateSofi');
        const spinner = document.getElementById('sofiSpinner');
        const btnText = document.getElementById('sofiBtnText');
        const textarea = document.getElementById('content');
        const badge = document.getElementById('scriptBadge');

        const framework = document.getElementById('sofiFramework').value;
        const tone = document.getElementById('sofiTone').value;
        const language = document.getElementById('sofiLanguage').value;
        const video_type_id = document.getElementById('sofiVideoType').value || null;

        // UI Loading state
        btn.disabled = true;
        spinner.classList.remove('hidden');
        btnText.textContent = "{{ __('Criando roteiro magnético...') }}";

        try {
            const response = await fetch("{{ route('roteiros.generate-sofi', $production) }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    "Accept": "application/json"
                },
                body: JSON.stringify({ framework, tone, language, video_type_id })
            });

            const result = await response.json();

            if (result.status === 'success' && result.data.full_script) {
                textarea.value = result.data.full_script;
                badge.classList.remove('hidden');
                textarea.focus();
            } else {
                alert(result.message || "{{ __('Não foi possível gerar o roteiro neste momento. Tente novamente.') }}");
            }
        } catch (e) {
            console.error(e);
            alert("{{ __('Erro ao conectar com a Sofi AI. Tente novamente.') }}");
        } finally {
            btn.disabled = false;
            spinner.classList.add('hidden');
            btnText.textContent = "✨ {{ __('Regerar com Sofi') }}";
        }
    }
</script>
@endsection
