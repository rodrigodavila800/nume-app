@extends('layouts.app')

@section('title', __('NUME | Editar Roteiro com Sofi AI'))
@section('breadcrumb', __('Editar Roteiro'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <h1>{{ __('Editar Roteiro') }}</h1>
                <span class="status-pill copper">Sofi /copywriter</span>
            </div>
            <p>{{ __('Produção:') }} <strong class="text-orange-400">{{ $production->title }}</strong></p>
        </div>
        <a href="{{ route('roteiros.show', ['production' => $production, 'roteiro' => $roteiro]) }}" class="btn text-xs py-2 px-3.5">
            ← {{ __('Voltar ao roteiro') }}
        </a>
    </div>

    @if ($roteiro->status === 'rejected')
        <div class="card space-y-2">
            <p class="font-semibold text-rose-400 text-sm">{{ __('Sugestão de Mudanças Enviada pelo Cliente') }}</p>
            @if($roteiro->rejection_reason)
                <p class="text-xs text-rose-300 bg-zinc-950 p-3 rounded-xl border border-zinc-800 font-mono">{{ $roteiro->rejection_reason }}</p>
            @endif
            <p class="text-zinc-400 text-xs">{{ __('Você pode pedir para a Sofi IA reescrever o roteiro aplicando essa sugestão automaticamente ou editar o texto na mão abaixo. Ao salvar, o roteiro voltará para Rascunho para reenvio ao cliente.') }}</p>
        </div>
    @endif

    <!-- Sofi AI Copilot Copywriting Engine Hub -->
    <div class="card space-y-4">
        <div class="card-header pb-3 border-b border-zinc-800">
            <div>
                <h2 class="card-title">{{ __('Regerar Narrativa com Sofi AI (/copywriter)') }}</h2>
                <p class="card-description">{{ __('A Sofi reescreve o roteiro aplicando as sugestões do cliente ou a sua orientação nos blocos do vídeo.') }}</p>
            </div>
            <span class="status-pill">Frameworks Meta / YouTube</span>
        </div>

        @include('roteiros._video_type_select', ['selectedVideoType' => old('video_type_id', $roteiro->video_type_id)])

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="space-y-1.5">
                <label for="sofiFramework" class="block text-xs font-medium text-zinc-300">{{ __('Estrutura / Framework') }}</label>
                <select id="sofiFramework" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-200 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none">
                    <option value="pattern_interrupt">{{ __('Padrão de Interrupção (0-3s)') }}</option>
                    <option value="authority_interview">{{ __('Autoridade Executiva / B2B') }}</option>
                    <option value="direct_offer">{{ __('Oferta Direta (Tráfego Pago)') }}</option>
                    <option value="storytelling_case">{{ __('Storytelling & Case de Sucesso') }}</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label for="sofiTone" class="block text-xs font-medium text-zinc-300">{{ __('Tom de Voz') }}</label>
                <select id="sofiTone" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-200 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none">
                    <option value="persuasive">{{ __('Persuasivo & Enérgico') }}</option>
                    <option value="executive">{{ __('Corporativo & Autoritário') }}</option>
                    <option value="empathic">{{ __('Didático & Empático') }}</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label for="sofiLanguage" class="block text-xs font-medium text-zinc-300">{{ __('Idioma do Roteiro') }}</label>
                <select id="sofiLanguage" class="w-full bg-zinc-950 border border-zinc-800 text-zinc-200 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none">
                    <option value="pt_BR" {{ session('locale') === 'pt_BR' ? 'selected' : '' }}>{{ __('Português (Brasil)') }}</option>
                    <option value="es" {{ session('locale') === 'es' ? 'selected' : '' }}>{{ __('Español (España / Zaragoza)') }}</option>
                </select>
            </div>
        </div>

        <div class="space-y-1.5">
            <label for="sofiRevisionNotes" class="block text-xs font-medium text-zinc-300">
                {{ __('Sugestão de Mudanças do Cliente / Orientação para a Sofi (Opcional)') }}
            </label>
            <input type="text" id="sofiRevisionNotes" maxlength="1000"
                   value="{{ $roteiro->rejection_reason }}"
                   placeholder="{{ __('Ex.: Deixar o gancho de 3s mais direto e destacar a garantia...') }}"
                   class="w-full bg-zinc-950 border border-zinc-800 text-zinc-200 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none placeholder:text-zinc-500">
        </div>

        <div class="pt-1 flex items-center justify-between gap-4">
            <p class="text-xs text-zinc-400">
                {{ __('A Sofi gera a nova versão abaixo, e você também pode ajustar o texto manualmente antes de salvar.') }}
            </p>
            <button type="button" id="btnGenerateSofi" onclick="generateSofiScript()" class="btn primary text-xs py-2 px-4 shrink-0">
                <span id="sofiSpinner" class="hidden animate-spin">...</span>
                <span id="sofiBtnText">{{ __('Regerar com Sofi') }}</span>
            </button>
        </div>
    </div>

    @if ($errors->any())
        <div class="card text-rose-400 text-xs font-medium">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form de Edição Manual / Confirmação da Produtora -->
    <form action="{{ route('roteiros.update', ['production' => $production, 'roteiro' => $roteiro]) }}" method="POST" class="card space-y-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="video_type_id" id="videoTypeId" value="{{ old('video_type_id', $roteiro->video_type_id) }}">

        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <label for="content" class="block text-sm font-semibold text-white">
                    {{ __('Conteúdo do Roteiro (Editável pela Produtora)') }} <span class="text-orange-400">*</span>
                </label>
                <span id="scriptBadge" class="hidden status-pill positive">
                    {{ __('Roteiro Atualizado pela Sofi') }}
                </span>
            </div>
            <textarea name="content" id="content" rows="18" required
                      class="w-full bg-zinc-950 border border-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm font-mono leading-relaxed focus:outline-none @error('content') border-rose-500 @enderror">{{ old('content', $roteiro->content) }}</textarea>
            @error('content') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-zinc-800">
            <button type="submit" class="btn primary text-sm py-2.5 px-6">
                {{ __('Salvar Roteiro') }}
            </button>
            <a href="{{ route('roteiros.show', ['production' => $production, 'roteiro' => $roteiro]) }}" class="btn text-sm py-2.5 px-6">
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
        const revision_notes = document.getElementById('sofiRevisionNotes')?.value || null;

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
                body: JSON.stringify({ framework, tone, language, video_type_id, revision_notes })
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
            btnText.textContent = "{{ __('Regerar com Sofi') }}";
        }
    }
</script>
@endsection
