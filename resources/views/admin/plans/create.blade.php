@extends('layouts.app')

@section('title', __('Novo Plano de Assinatura Recorrente | NUME Admin'))
@section('breadcrumb', __('Novo Plano'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-orange-500/20 text-orange-400 border border-orange-500/30">
                    🔁 {{ __('Modo Criador & Retainers') }}
                </span>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight mt-1">{{ __('Criar Plano de Assinatura Recorrente') }}</h1>
            <p class="text-xs text-zinc-400 mt-0.5">{{ __('Configure propostas contínuas com cotas mensais de vídeos, fidelidade e cobrança recorrente automatizada.') }}</p>
        </div>
        <div>
            <a href="{{ route('admin.plans.index') }}" class="text-xs font-bold text-zinc-400 hover:text-white transition flex items-center gap-1.5">
                ← {{ __('Voltar aos Planos') }}
            </a>
        </div>
    </div>

    <form action="{{ route('admin.plans.store') }}" method="POST" id="plan-form" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-6 shadow-xl">
        @csrf

        <!-- Dados Básicos -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Categoria do Catálogo') }}
                </label>
                <select name="category_id"
                        class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 transition">
                    <option value="">{{ __('Selecione uma categoria...') }}</option>
                    @foreach($categories ?? [] as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Vídeo de Vitrine / Portfólio') }}
                </label>
                <select name="showcase_video_id"
                        class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 transition">
                    <option value="">{{ __('Nenhum (Sem vídeo de demonstração)') }}</option>
                    @foreach($showcaseVideos as $video)
                        <option value="{{ $video->id }}" @selected(old('showcase_video_id') == $video->id)>
                            {{ $video->is_vertical ? '📱 [9:16]' : '🖥️ [16:9]' }} {{ $video->title }}
                        </option>
                    @endforeach
                </select>
                @error('showcase_video_id') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5 sm:col-span-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Nome do Plano') }} <span class="text-orange-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       placeholder="Ex: Modo Criador — Retainer Mensal (4 Vídeos)"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 transition placeholder:text-zinc-600">
                @error('name') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <!-- Parâmetros de Recorrência & Fidelidade -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-5 rounded-xl bg-zinc-950/80 border border-zinc-800">
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Ciclo de Renovação') }} <span class="text-orange-500">*</span>
                </label>
                <select name="billing_interval"
                        class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-orange-500">
                    <option value="month" @selected(old('billing_interval', 'month') === 'month')>{{ __('Mensal (Todo mês)') }}</option>
                    <option value="quarter" @selected(old('billing_interval') === 'quarter')>{{ __('Trimestral (A cada 3 meses)') }}</option>
                </select>
                @error('billing_interval') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Fidelidade Mínima') }} <span class="text-orange-500">*</span>
                </label>
                <select name="commitment_months"
                        class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-orange-500">
                    <option value="1" @selected(old('commitment_months') == 1)>1 {{ __('mês (Sem fidelidade)') }}</option>
                    <option value="3" @selected(old('commitment_months') == 3)>3 {{ __('meses (Trimestral)') }}</option>
                    <option value="6" @selected(old('commitment_months', 6) == 6)>6 {{ __('meses (Recomendado)') }}</option>
                    <option value="12" @selected(old('commitment_months') == 12)>12 {{ __('meses (Anual)') }}</option>
                </select>
                @error('commitment_months') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Status do Plano') }}
                </label>
                <select name="status"
                        class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-orange-500">
                    <option value="active" @selected(old('status', 'active') === 'active')>🟢 {{ __('Ativo para Venda') }}</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>⚪ {{ __('Rascunho / Oculto') }}</option>
                </select>
            </div>
        </div>

        <!-- Preço e Cota de Vídeos -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Cota de Vídeos / Ciclo') }} <span class="text-orange-500">*</span>
                </label>
                <input type="number" name="video_quantity" min="1" max="100" value="{{ old('video_quantity', 4) }}" required
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 transition">
                <p class="text-[11px] text-zinc-500">{{ __('Quantidade renovada a cada ciclo.') }}</p>
                @error('video_quantity') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            @include('packs._publishing_limits', ['limitsModel' => null])

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Valor Recorrente (€)') }} <span class="text-orange-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-2.5 text-zinc-500 text-sm font-bold">€</span>
                    <input type="number" step="0.01" min="0" name="price" value="{{ old('price', 590.00) }}" required
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl pl-8 pr-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 transition">
                </div>
                <p class="text-[11px] text-zinc-500">{{ __('Cobrado automaticamente por ciclo.') }}</p>
                @error('price') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('IVA Repercutido (%)') }}
                </label>
                <input type="number" step="0.01" name="vat_rate" value="{{ old('vat_rate', 21.00) }}"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 transition">
                <p class="text-[11px] text-zinc-500">{{ __('Padrão Espanha: 21% (AEAT)') }}</p>
            </div>
        </div>

        <!-- Descrição Comercial -->
        <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Proposta de Valor / Descrição do Plano') }}
            </label>
            <textarea name="description" rows="3"
                      placeholder="Ex: Parceria estratégica contínua de vídeo marketing para marcas que buscam autoridade semanal e presença massiva nas redes."
                      class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 transition placeholder:text-zinc-600">{{ old('description') }}</textarea>
            @error('description') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- Entregáveis Inclusos (Features) -->
        <div class="space-y-3 pt-4 border-t border-zinc-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-300">{{ __('Entregáveis Inclusos no Contrato') }}</h3>
                    <p class="text-[11px] text-zinc-500">{{ __('Benefícios exibidos na vitrine e garantidos contratualmente ao cliente.') }}</p>
                </div>
                <button type="button" onclick="addFeatureField()" class="text-xs font-bold text-orange-400 hover:text-orange-300 transition">
                    + {{ __('Adicionar Benefício') }}
                </button>
            </div>

            <div id="features-container" class="space-y-2">
                @php
                    $defaultFeatures = old('features', [
                        'Roteiros com ganchos Sofi de alta retenção (0-3s)',
                        'Captação e edição profissional em 4K HDR',
                        'Legendas dinâmicas otimizadas para Meta e TikTok',
                        'Distribuição social e agendamento automático multi-canal',
                        'Reunião mensal de alinhamento estratégico de audiência',
                    ]);
                @endphp

                @foreach($defaultFeatures as $feat)
                    <div class="flex items-center gap-2 feature-row">
                        <input type="text" name="features[]" value="{{ $feat }}"
                               class="flex-1 bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2 text-xs focus:outline-none focus:border-orange-500 transition">
                        <button type="button" onclick="this.closest('.feature-row').remove()" class="p-2 text-zinc-500 hover:text-rose-400 transition" title="Remover">
                            ✕
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Add-ons Disponíveis -->
        @if(isset($availableAddons) && $availableAddons->isNotEmpty())
        <div class="space-y-3 pt-4 border-t border-zinc-800">
            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-300">{{ __('Add-ons & Serviços Vinculados') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($availableAddons as $addon)
                    <label class="flex items-center gap-3 p-3 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-zinc-700 transition cursor-pointer">
                        <input type="checkbox" name="addon_ids[]" value="{{ $addon->id }}"
                               class="w-4 h-4 rounded bg-zinc-900 border-zinc-700 text-orange-600 focus:ring-orange-500">
                        <div class="overflow-hidden">
                            <p class="text-xs font-bold text-white truncate">{{ $addon->name }}</p>
                            <p class="text-[11px] text-emerald-400 font-mono">+ € {{ number_format($addon->default_price, 2, ',', '.') }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Botões -->
        <div class="pt-6 border-t border-zinc-800 flex items-center justify-end gap-3">
            <a href="{{ route('admin.plans.index') }}" class="px-5 py-2.5 rounded-xl text-xs font-bold text-zinc-400 hover:text-white transition">
                {{ __('Cancelar') }}
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-orange-600 hover:bg-orange-500 text-white shadow-lg shadow-orange-900/20 transition active:scale-95 cursor-pointer">
                {{ __('Salvar Plano de Assinatura') }}
            </button>
        </div>
    </form>
</div>

<script>
function addFeatureField() {
    const container = document.getElementById('features-container');
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2 feature-row';
    row.innerHTML = `
        <input type="text" name="features[]" placeholder="Novo benefício do plano..." class="flex-1 bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2 text-xs focus:outline-none focus:border-orange-500 transition">
        <button type="button" onclick="this.closest('.feature-row').remove()" class="p-2 text-zinc-500 hover:text-rose-400 transition" title="Remover">✕</button>
    `;
    container.appendChild(row);
}
</script>
@endsection
