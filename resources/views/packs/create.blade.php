@extends('layouts.app')

@section('title', __('NUME | Novo Pacote'))
@section('breadcrumb', __('Novo Pacote'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Criar Pacote de Vídeos') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Configure a proposta comercial, entregáveis inclusos e vincule os Add-ons do catálogo.') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" id="btn-sofi-generate"
                    class="inline-flex items-center gap-2 bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-lg shadow-orange-950/40 transition active:scale-[0.98]">
                <span id="sofi-icon">✨</span>
                <span>{{ __('Gerar com Sofi IA') }}</span>
            </button>
            <a href="{{ route('packs.index') }}" class="text-xs font-bold text-zinc-400 hover:text-white transition flex items-center gap-1.5">
                ← {{ __('Voltar') }}
            </a>
        </div>
    </div>

    <!-- Feedback da Sofi IA -->
    <div id="sofi-alert" class="hidden p-4 rounded-xl bg-orange-950/40 border border-orange-500/30 text-xs text-orange-200 flex items-start gap-3">
        <span class="text-base">💡</span>
        <div class="space-y-0.5">
            <strong class="font-bold text-orange-400">{{ __('Sofi IA (/ceo + /copywriter):') }}</strong>
            <p id="sofi-alert-msg">{{ __('Estrutura de alta conversão, entregáveis e Add-ons vinculados com sucesso!') }}</p>
        </div>
    </div>

    <form action="{{ route('packs.store') }}" method="POST" id="pack-form" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-6">
        @csrf

        <!-- Dados Básicos -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Categoria') }}
                </label>
                <select name="category_id" id="field-category-id"
                        class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition">
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
                    {{ __('Vídeo de Exemplo do Portfólio') }}
                </label>
                <select name="showcase_video_id" id="field-showcase-video"
                        class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition">
                    <option value="">{{ __('Nenhum (Sem vídeo de demonstração)') }}</option>
                    @foreach($showcaseVideos as $video)
                        <option value="{{ $video->id }}" @selected(old('showcase_video_id') == $video->id)>
                            {{ $video->is_vertical ? '📱 [9:16]' : '🖥️ [16:9]' }} {{ $video->title }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-zinc-500">{{ __('Permite que o cliente abra um modal 4K para ver a qualidade e padrão de entrega deste pacote.') }}</p>
                @error('showcase_video_id') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5 sm:col-span-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Nome do Pacote') }} <span class="text-orange-500">*</span>
                </label>
                <input type="text" name="name" id="field-name" value="{{ old('name') }}" required
                       placeholder="Ex: Pack Posicionamiento Pro"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('name') border-rose-500 @enderror">
                @error('name') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <!-- SEÇÃO: Modelo de Faturamento & Assinatura Recorrente -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 rounded-xl bg-zinc-950/60 border border-zinc-800/80">
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Tipo de Cobrança') }}
                </label>
                <select name="billing_type" id="field-billing-type"
                        class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
                    <option value="one_time" @selected(old('billing_type', 'one_time') == 'one_time')>{{ __('Compra Pontual / Avulsa') }}</option>
                    <option value="subscription" @selected(old('billing_type') == 'subscription')>{{ __('Plano Recorrente / Assinatura') }}</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Ciclo de Renovação') }}
                </label>
                <select name="billing_interval" id="field-billing-interval"
                        class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
                    <option value="month" @selected(old('billing_interval', 'month') == 'month')>{{ __('Mensal (a cada 30 dias)') }}</option>
                    <option value="quarter" @selected(old('billing_interval') == 'quarter')>{{ __('Trimestral (a cada 90 dias)') }}</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Contrato Mínimo (Meses)') }}
                </label>
                <input type="number" min="1" max="36" name="commitment_months" id="field-commitment-months" value="{{ old('commitment_months', 1) }}"
                       class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
                <p class="text-[10px] text-zinc-500">{{ __('Ex: 3 para contratos trimestrais com cota renovada.') }}</p>
            </div>
        </div>

        <!-- SEÇÃO: Modalidade Comercial de Pagamento & Custódia de Ativos (Asset Escrow) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 rounded-xl bg-gradient-to-r from-zinc-950 via-zinc-950 to-orange-950/20 border border-orange-500/20">
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-orange-400">
                    {{ __('Modalidade Comercial') }}
                </label>
                <select name="billing_mode" id="field-billing-mode"
                        class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
                    <option value="full_upfront" @selected(old('billing_mode', 'full_upfront') == 'full_upfront')>
                        {{ __('100% Antecipado (Clássico)') }}
                    </option>
                    <option value="split_50_50" @selected(old('billing_mode') == 'split_50_50')>
                        {{ __('Modelo 50/50 (Sinal + Saldo na Entrega)') }}
                    </option>
                    <option value="pilot_entry" @selected(old('billing_mode') == 'pilot_entry')>
                        {{ __('Vídeo Piloto de Entrada (Com Abatimento)') }}
                    </option>
                </select>
                <p class="text-[10px] text-zinc-500">{{ __('O modelo 50/50 retém o download e postagem até aprovação final.') }}</p>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Percentual de Sinal (%)') }}
                </label>
                <input type="number" min="10" max="90" name="deposit_percentage" id="field-deposit-percentage" value="{{ old('deposit_percentage', 50) }}"
                       class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
                <p class="text-[10px] text-zinc-500">{{ __('Padrão: 50% cobrado no pedido e 50% na aprovação do vídeo.') }}</p>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Crédito na Assinatura') }}
                </label>
                <input type="number" step="0.01" min="0" name="pilot_discount_on_subscription" id="field-pilot-discount" value="{{ old('pilot_discount_on_subscription', 0.00) }}"
                       class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
                <p class="text-[10px] text-zinc-500">{{ __('Valor abatido caso o cliente migre para um plano mensal.') }}</p>
            </div>
        </div>

        <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Descrição Comercial') }}
            </label>
            <textarea name="description" id="field-description" rows="3"
                      placeholder="Ex: Producción audiovisual continua para posicionar tu marca como referente de mercado..."
                      class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">{{ old('description') }}</textarea>
            @error('description') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- Precificação & Formato -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-zinc-950/60 border border-zinc-800/80">
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Qtd. Vídeos') }} <span class="text-orange-500">*</span>
                </label>
                <input type="number" name="video_quantity" id="field-video-quantity" min="1" value="{{ old('video_quantity', 4) }}" required
                       class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Preço Base') }} <span class="text-orange-500">*</span>
                </label>
                <input type="number" step="0.01" min="0" name="price" id="field-price" value="{{ old('price', '850.00') }}" required
                       placeholder="850.00"
                       class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Moeda') }}
                </label>
                <select name="currency" id="field-currency" class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
                    <option value="eur" @selected(old('currency', 'eur') == 'eur')>EUR (€)</option>
                    <option value="brl" @selected(old('currency') == 'brl')>BRL (R$)</option>
                    <option value="usd" @selected(old('currency') == 'usd')>USD ($)</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Taxa IVA / Imposto (%)') }}
                </label>
                <input type="number" step="0.01" min="0" max="100" name="vat_rate" id="field-vat-rate" value="{{ old('vat_rate', '21.00') }}"
                       placeholder="21.00"
                       class="w-full bg-zinc-900 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
            </div>
        </div>

        <!-- SEÇÃO: Entregáveis Inclusos (Features) -->
        <div class="space-y-3 pt-2">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-white">{{ __('Entregáveis Inclusos (Features)') }}</h3>
                    <p class="text-xs text-zinc-500">{{ __('Lista de itens que o cliente recebe neste pacote.') }}</p>
                </div>
                <button type="button" id="btn-add-feature"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-orange-400 hover:text-orange-300 bg-orange-950/30 hover:bg-orange-950/60 border border-orange-800/40 px-3 py-1.5 rounded-lg transition">
                    + {{ __('Adicionar Entregável') }}
                </button>
            </div>

            <div id="features-container" class="space-y-2">
                @php
                    $oldFeatures = old('features', [
                        'Vídeos gravados e editados em 4K',
                        'Roteirização estratégica orientada a retenção',
                        'Aprovação e feedback no Portal do Cliente',
                    ]);
                @endphp
                @foreach($oldFeatures as $idx => $feat)
                    <div class="feature-row flex items-center gap-2">
                        <span class="text-xs text-orange-500 font-bold">✓</span>
                        <input type="text" name="features[]" value="{{ $feat }}" placeholder="Ex: Roteiros persuasivos e gravação 4K"
                               class="flex-1 bg-zinc-950 border border-zinc-800 text-white text-xs rounded-xl px-3.5 py-2 focus:outline-none focus:border-orange-500">
                        <button type="button" onclick="this.closest('.feature-row').remove()" class="text-zinc-500 hover:text-rose-400 p-2 text-xs transition" title="Remover">
                            ✕
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- SEÇÃO RELACIONAL: Catálogo de Add-ons da Produtora -->
        <div class="space-y-3 pt-4 border-t border-zinc-800">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span class="text-orange-500 font-bold">+</span>
                        {{ __('Add-ons do Catálogo (Selecione para Vincular)') }}
                    </h3>
                    <p class="text-xs text-zinc-500">{{ __('Marque os adicionais que estarão disponíveis para o cliente contratar junto a este pacote.') }}</p>
                </div>
                <a href="{{ route('addons.index') }}" target="_blank"
                   class="text-xs font-bold text-orange-400 hover:text-orange-300 transition flex items-center gap-1">
                    <span>+ {{ __('Gerenciar Catálogo de Add-ons') }}</span>
                    <span>↗</span>
                </a>
            </div>

            <div id="addons-picker-container" class="space-y-2">
                @forelse($availableAddons ?? [] as $addon)
                    <div class="addon-item flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl bg-zinc-950/60 border border-zinc-800/80 hover:border-orange-500/40 transition">
                        <label class="flex items-start sm:items-center gap-3 cursor-pointer select-none flex-1">
                            <input type="checkbox" name="addon_ids[]" value="{{ $addon->id }}"
                                   @checked(in_array($addon->id, old('addon_ids', [])))
                                   class="mt-0.5 sm:mt-0 w-4 h-4 rounded bg-zinc-900 border-zinc-700 text-orange-600 focus:ring-orange-500 addon-picker-checkbox"
                                   data-addon-name="{{ strtolower($addon->name) }}">
                            <div>
                                <span class="text-xs font-bold text-white block">+ {{ $addon->name }}</span>
                                <span class="text-[10px] text-zinc-500">
                                    {{ __('Preço padrão de catálogo:') }} <strong class="text-zinc-400 font-mono">{{ number_format($addon->default_price, 0) }} €</strong>
                                    @if($addon->description) · {{ $addon->description }} @endif
                                </span>
                            </div>
                        </label>
                        <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
                            <span class="text-[10px] font-medium text-zinc-400">{{ __('Preço no Pacote (€):') }}</span>
                            <input type="number" step="0.01" min="0" name="addon_prices[{{ $addon->id }}]"
                                   value="{{ old('addon_prices.' . $addon->id) }}"
                                   placeholder="{{ number_format($addon->default_price, 2, '.', '') }}"
                                   class="w-24 bg-zinc-900 border border-zinc-800 text-white text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-orange-500 text-right">
                        </div>
                    </div>
                @empty
                    <div class="p-4 rounded-xl bg-zinc-950/40 border border-dashed border-zinc-800 text-center text-xs text-zinc-500">
                        {{ __('Nenhum Add-on cadastrado no catálogo ainda.') }}
                        <a href="{{ route('addons.index') }}" class="text-orange-400 hover:underline font-bold ml-1">{{ __('Criar Add-on no catálogo') }}</a>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="flex items-center gap-3 pt-6 border-t border-zinc-800">
            <button type="submit"
                    class="flex-1 sm:flex-initial bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-lg shadow-orange-900/20 transition-all">
                {{ __('Salvar Pacote') }}
            </button>
            <a href="{{ route('packs.index') }}"
               class="flex-1 sm:flex-initial inline-flex items-center justify-center bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm px-6 py-2.5 rounded-xl transition">
                {{ __('Cancelar') }}
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar Linha de Entregável
    document.getElementById('btn-add-feature').addEventListener('click', function() {
        const container = document.getElementById('features-container');
        const div = document.createElement('div');
        div.className = 'feature-row flex items-center gap-2';
        div.innerHTML = `
            <span class="text-xs text-orange-500 font-bold">✓</span>
            <input type="text" name="features[]" placeholder="Ex: Formatos otimizados 9:16 e 16:9"
                   class="flex-1 bg-zinc-950 border border-zinc-800 text-white text-xs rounded-xl px-3.5 py-2 focus:outline-none focus:border-orange-500">
            <button type="button" onclick="this.closest('.feature-row').remove()" class="text-zinc-500 hover:text-rose-400 p-2 text-xs transition" title="Remover">
                ✕
            </button>
        `;
        container.appendChild(div);
    });

    // Gerar com Sofi IA
    const btnSofi = document.getElementById('btn-sofi-generate');
    const sofiIcon = document.getElementById('sofi-icon');
    const sofiAlert = document.getElementById('sofi-alert');

    btnSofi.addEventListener('click', async function() {
        btnSofi.disabled = true;
        btnSofi.classList.add('opacity-70');
        sofiIcon.innerHTML = '⏳';

        const categoryId = document.getElementById('field-category-id').value;
        const name = document.getElementById('field-name').value;
        const price = document.getElementById('field-price').value;
        const videoQuantity = document.getElementById('field-video-quantity').value;

        try {
            const res = await fetch('{{ route('packs.sofi_generate') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    category_id: categoryId,
                    name: name,
                    price: price,
                    video_quantity: videoQuantity,
                    language: 'es'
                })
            });

            const json = await res.json();
            if (json.success && json.data) {
                const data = json.data;

                // Preencher Descrição
                if (data.description) {
                    document.getElementById('field-description').value = data.description;
                }

                // Preencher Features
                if (Array.isArray(data.features) && data.features.length > 0) {
                    const featContainer = document.getElementById('features-container');
                    featContainer.innerHTML = '';
                    data.features.forEach(f => {
                        const div = document.createElement('div');
                        div.className = 'feature-row flex items-center gap-2';
                        div.innerHTML = `
                            <span class="text-xs text-orange-500 font-bold">✓</span>
                            <input type="text" name="features[]" value="${f}"
                                   class="flex-1 bg-zinc-950 border border-zinc-800 text-white text-xs rounded-xl px-3.5 py-2 focus:outline-none focus:border-orange-500">
                            <button type="button" onclick="this.closest('.feature-row').remove()" class="text-zinc-500 hover:text-rose-400 p-2 text-xs transition" title="Remover">
                                ✕
                            </button>
                        `;
                        featContainer.appendChild(div);
                    });
                }

                // Selecionar Add-ons sugeridos nos checkboxes relacionais
                if (Array.isArray(data.addons) && data.addons.length > 0) {
                    const checkboxes = document.querySelectorAll('.addon-picker-checkbox');
                    const suggestedNames = data.addons.map(a => (a.name || '').toLowerCase());

                    checkboxes.forEach(cb => {
                        const cbName = cb.dataset.addonName || '';
                        const matches = suggestedNames.some(sn => cbName.includes(sn) || sn.includes(cbName));
                        if (matches) {
                            cb.checked = true;
                        }
                    });
                }

                sofiAlert.classList.remove('hidden');
            }
        } catch (e) {
            console.error(e);
            alert('Não foi possível comunicar com a Sofi IA no momento.');
        } finally {
            btnSofi.disabled = false;
            btnSofi.classList.remove('opacity-70');
            sofiIcon.innerHTML = '✨';
        }
    });
});
</script>
@endsection
