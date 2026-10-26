@extends('layouts.portal')

@section('title', __('Anamnese da Marca | Portal NUME'))

@php
    $input = 'w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 bg-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors';
    $label = 'block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1';
    $selectedNetworks = old('priority_networks', $answers['priority_networks'] ?? []);
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">{{ __('Anamnese da marca') }}</h1>
            <p class="text-xs text-zinc-500 mt-1">
                {{ __('Responda uma vez (e atualize quando mudar). A Sofi usa estas respostas, o DNA e as suas redes para montar o plano de marketing que você vai aprovar.') }}
            </p>
        </div>
        <a href="{{ route('portal.marketing-plan.index') }}" class="text-xs font-bold text-zinc-500 hover:text-orange-600 transition">← {{ __('Plano de marketing') }}</a>
    </div>

    @if ($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3.5 rounded-xl text-xs font-semibold">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('portal.anamnesis.update') }}" method="POST" class="bg-white rounded-2xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="objective" class="{{ $label }}">{{ __('Principal objetivo com os vídeos') }} *</label>
            <select id="objective" name="objective" required class="{{ $input }}">
                <option value="">{{ __('Escolha') }}</option>
                @foreach (\App\Support\Anamnesis::objectiveLabels() as $value => $text)
                    <option value="{{ $value }}" @selected(old('objective', $answers['objective'] ?? null) === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label for="main_offer" class="{{ $label }}">{{ __('Produto ou serviço que mais quer vender') }} *</label>
                <input id="main_offer" name="main_offer" type="text" maxlength="300" required value="{{ old('main_offer', $answers['main_offer'] ?? '') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="average_ticket" class="{{ $label }}">{{ __('Ticket médio') }}</label>
                <input id="average_ticket" name="average_ticket" type="text" maxlength="60" placeholder="{{ __('Ex.: 90 €') }}" value="{{ old('average_ticket', $answers['average_ticket'] ?? '') }}" class="{{ $input }}">
            </div>
        </div>

        <div>
            <label for="ideal_customer" class="{{ $label }}">{{ __('Quem é o seu cliente ideal e o que ele quer resolver?') }} *</label>
            <textarea id="ideal_customer" name="ideal_customer" rows="3" maxlength="1000" required class="{{ $input }}">{{ old('ideal_customer', $answers['ideal_customer'] ?? '') }}</textarea>
        </div>

        <div>
            <label for="competitors" class="{{ $label }}">{{ __('Concorrentes ou marcas que você admira') }}</label>
            <input id="competitors" name="competitors" type="text" maxlength="500" value="{{ old('competitors', $answers['competitors'] ?? '') }}" class="{{ $input }}">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="tone" class="{{ $label }}">{{ __('Tom de voz') }} *</label>
                <select id="tone" name="tone" required class="{{ $input }}">
                    <option value="">{{ __('Escolha') }}</option>
                    @foreach (\App\Support\Anamnesis::toneLabels() as $value => $text)
                        <option value="{{ $value }}" @selected(old('tone', $answers['tone'] ?? null) === $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="posting_frequency" class="{{ $label }}">{{ __('Frequência desejada') }} *</label>
                <select id="posting_frequency" name="posting_frequency" required class="{{ $input }}">
                    <option value="">{{ __('Escolha') }}</option>
                    @foreach (\App\Support\Anamnesis::frequencyLabels() as $value => $text)
                        <option value="{{ $value }}" @selected(old('posting_frequency', $answers['posting_frequency'] ?? null) === $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <fieldset>
            <legend class="{{ $label }}">{{ __('Redes prioritárias') }} *</legend>
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Enums\PlataformaAlvo::cases() as $network)
                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-zinc-200 text-xs font-semibold text-zinc-700 cursor-pointer has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50">
                        <input type="checkbox" name="priority_networks[]" value="{{ $network->value }}" @checked(in_array($network->value, $selectedNetworks, true)) class="rounded border-zinc-300 text-orange-600 focus:ring-orange-500">
                        <span>{{ $network->icon() }} {{ $network->label() }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="seasonality" class="{{ $label }}">{{ __('Datas ou épocas importantes') }}</label>
                <input id="seasonality" name="seasonality" type="text" maxlength="500" placeholder="{{ __('Ex.: Natal, volta às aulas') }}" value="{{ old('seasonality', $answers['seasonality'] ?? '') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="restrictions" class="{{ $label }}">{{ __('O que não pode aparecer nos vídeos') }}</label>
                <input id="restrictions" name="restrictions" type="text" maxlength="500" value="{{ old('restrictions', $answers['restrictions'] ?? '') }}" class="{{ $input }}">
            </div>
        </div>

        <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-200 space-y-3">
            <label class="flex items-center gap-2 text-sm text-zinc-800 cursor-pointer">
                <input type="hidden" name="wants_ads" value="0">
                <input type="checkbox" name="wants_ads" value="1" @checked(old('wants_ads', $answers['wants_ads'] ?? false)) class="rounded border-zinc-300 text-orange-600 focus:ring-orange-500">
                <span>{{ __('Quero anunciar (a verba é paga por você direto à plataforma de anúncios)') }}</span>
            </label>
            <div>
                <label for="ads_monthly_budget" class="{{ $label }}">{{ __('Verba mensal pensada para anúncios') }}</label>
                <input id="ads_monthly_budget" name="ads_monthly_budget" type="text" maxlength="60" placeholder="{{ __('Ex.: 300 €') }}" value="{{ old('ads_monthly_budget', $answers['ads_monthly_budget'] ?? '') }}" class="{{ $input }}">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex justify-center items-center px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-orange-600 hover:bg-orange-500 shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                {{ __('Salvar anamnese') }}
            </button>
        </div>
    </form>
</div>
@endsection
