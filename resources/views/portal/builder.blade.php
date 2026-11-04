@extends('layouts.portal')

@section('title', __('Monte seu pacote') . ' | NUME Portal')

@section('content')
@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.') . ' €';
@endphp
<div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">{{ __('Monte seu pacote') }}</h1>
        <p class="text-sm text-zinc-600">{{ __('Escolha quantos vídeos de cada tipo e os extras. O valor é atualizado na hora; ao confirmar, você recebe a proposta em PDF e segue para o pagamento.') }}</p>
    </div>

    @if (session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-semibold">{{ session('error') }}</div>
    @endif

    @if (empty($catalog['video_types']))
        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-sm font-semibold">
            {{ __('A produtora ainda não liberou o montador de pacotes. Veja os pacotes prontos ou fale com a equipe.') }}
        </div>
    @else
        <form method="POST" action="{{ route('portal.builder.store') }}" id="packBuilder" data-vat="{{ $catalog['vat_rate'] }}" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @csrf

            <div class="lg:col-span-2 space-y-6">
                <section class="space-y-3">
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-zinc-500">{{ __('Vídeos') }}</h2>
                    @foreach ($catalog['video_types'] as $type)
                        <div class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-zinc-200 shadow-xs">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-zinc-900">{{ $type['name'] }}</p>
                                <p class="text-xs text-zinc-500">{{ $type['duration_seconds'] }} s · {{ $money($type['price']) }} {{ __('por vídeo') }}</p>
                                @if ($type['description'])
                                    <p class="text-xs text-zinc-500 mt-1 line-clamp-2">{{ $type['description'] }}</p>
                                @endif
                            </div>
                            <label class="sr-only" for="type-{{ $type['id'] }}">{{ __('Quantidade de :type', ['type' => $type['name']]) }}</label>
                            <input id="type-{{ $type['id'] }}" type="number" name="items[{{ $type['id'] }}]" min="0" max="50" step="1" inputmode="numeric"
                                value="{{ $selectedItems[$type['id']] ?? 0 }}" data-builder-qty data-price="{{ $type['price'] }}"
                                class="w-20 text-center text-sm font-bold px-2 py-2 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-orange-500">
                        </div>
                    @endforeach
                </section>

                @if (! empty($catalog['addons']))
                    <section class="space-y-3">
                        <h2 class="text-sm font-extrabold uppercase tracking-wider text-zinc-500">{{ __('Extras') }}</h2>
                        @foreach ($catalog['addons'] as $addon)
                            <label class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-zinc-200 shadow-xs cursor-pointer has-[:checked]:border-orange-500 has-[:checked]:ring-2 has-[:checked]:ring-orange-500/20">
                                <input type="checkbox" name="addons[]" value="{{ $addon['id'] }}" data-builder-addon data-price="{{ $addon['price'] }}"
                                    @checked(in_array($addon['id'], $selectedAddons, true)) class="w-4 h-4 text-orange-600 focus:ring-orange-500">
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm font-bold text-zinc-900">{{ $addon['name'] }}</span>
                                    @if ($addon['description'])
                                        <span class="block text-xs text-zinc-500 line-clamp-2">{{ $addon['description'] }}</span>
                                    @endif
                                </span>
                                <span class="text-sm font-bold text-zinc-700">+ {{ $money($addon['price']) }}</span>
                            </label>
                        @endforeach
                    </section>
                @endif
            </div>

            <aside class="lg:sticky lg:top-6 h-fit p-5 bg-zinc-900 text-white rounded-2xl space-y-3">
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-zinc-400">{{ __('Seu pacote') }}</h2>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-400">{{ __('Vídeos') }}</dt><dd id="builderVideos" class="font-bold">0</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-400">{{ __('Subtotal') }}</dt><dd id="builderSubtotal" class="font-mono">0,00 €</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-400">{{ __('IVA (:rate%)', ['rate' => rtrim(rtrim(number_format($catalog['vat_rate'], 2, ',', ''), '0'), ',')]) }}</dt><dd id="builderTax" class="font-mono">0,00 €</dd></div>
                    <div class="flex justify-between border-t border-zinc-700 pt-2 text-base"><dt class="font-bold">{{ __('Total') }}</dt><dd id="builderTotal" class="font-mono font-extrabold text-orange-400">0,00 €</dd></div>
                    <div class="flex justify-between text-xs"><dt class="text-zinc-400">{{ __('Sinal para começar (50%)') }}</dt><dd id="builderDeposit" class="font-mono">0,00 €</dd></div>
                </dl>
                <p class="text-[11px] text-zinc-500">{{ __('Valor final confirmado na proposta.') }}</p>
                <button type="submit" id="builderSubmit" disabled
                    class="w-full px-5 py-3 rounded-xl text-sm font-extrabold text-white bg-gradient-to-r from-orange-600 to-amber-500 hover:from-orange-500 hover:to-amber-400 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    {{ __('Gerar proposta e contratar') }}
                </button>
            </aside>
        </form>
    @endif
</div>

<script>
    // Prévia do total (o servidor recalcula tudo com os preços do banco ao enviar).
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('packBuilder');
        if (!form) return;

        const vat = parseFloat(form.dataset.vat) || 0;
        const money = (value) => value.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
        const out = (id, text) => { document.getElementById(id).textContent = text; };

        function refresh() {
            let videos = 0;
            let subtotal = 0;

            form.querySelectorAll('[data-builder-qty]').forEach(function (input) {
                const qty = Math.max(0, Math.min(50, parseInt(input.value, 10) || 0));
                videos += qty;
                subtotal += qty * parseFloat(input.dataset.price);
            });
            form.querySelectorAll('[data-builder-addon]:checked').forEach(function (box) {
                subtotal += parseFloat(box.dataset.price);
            });

            const tax = Math.round(subtotal * vat) / 100;
            const total = subtotal + tax;

            out('builderVideos', String(videos));
            out('builderSubtotal', money(subtotal));
            out('builderTax', money(tax));
            out('builderTotal', money(total));
            out('builderDeposit', money(Math.round(total * 50) / 100));
            document.getElementById('builderSubmit').disabled = videos === 0;
        }

        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        refresh();
    });
</script>
@endsection
