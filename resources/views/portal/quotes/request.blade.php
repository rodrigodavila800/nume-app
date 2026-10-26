@extends('layouts.portal')

@section('title', __('Pedir orçamento | Portal NUME'))

@php
    $input = 'w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 bg-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors';
    $label = 'block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1';
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">{{ __('Pedir orçamento') }}</h1>
            <p class="text-xs text-zinc-500 mt-1">
                {{ __('Já preenchemos com o seu plano aprovado. Ajuste se precisar: a produtora calcula os valores e envia a proposta para você aprovar.') }}
            </p>
        </div>
        <a href="{{ route('portal.quotes.index') }}" class="text-xs font-bold text-zinc-500 hover:text-orange-600 transition">← {{ __('Orçamentos') }}</a>
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

    <form action="{{ route('portal.quotes.request.store') }}" method="POST" class="bg-white rounded-2xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm space-y-5">
        @csrf

        <p class="text-xs text-zinc-600">
            {{ __('Plano de marketing de :month · :count vídeos', ['month' => $plan->month_year, 'count' => $plan->items->count()]) }}
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="videos" class="{{ $label }}">{{ __('Vídeos por mês') }} *</label>
                <input id="videos" name="videos" type="number" min="1" max="60" required value="{{ old('videos', $defaults['videos']) }}" class="{{ $input }}">
            </div>
            <div>
                <label for="video_type_id" class="{{ $label }}">{{ __('Tipo de vídeo') }}</label>
                <select id="video_type_id" name="video_type_id" class="{{ $input }}">
                    <option value="">{{ __('A produtora escolhe') }}</option>
                    @foreach ($videoTypes as $type)
                        <option value="{{ $type->id }}" @selected((int) old('video_type_id', $defaults['video_type_id']) === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label for="social_networks" class="{{ $label }}">{{ __('Em quantas redes quer publicar?') }} *</label>
            <input id="social_networks" name="social_networks" type="number" min="1" max="8" required value="{{ old('social_networks', $defaults['social_networks']) }}" class="{{ $input }}">
            <p class="text-[11px] text-zinc-500 mt-1">{{ __('A quantidade de redes liberadas para publicar pela plataforma depende do plano contratado.') }}</p>
        </div>

        <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-200 space-y-3">
            <label class="flex items-center gap-2 text-sm text-zinc-800 cursor-pointer">
                <input type="hidden" name="wants_ads" value="0">
                <input type="checkbox" name="wants_ads" value="1" @checked(old('wants_ads', $defaults['wants_ads'])) class="rounded border-zinc-300 text-orange-600 focus:ring-orange-500">
                <span>{{ __('Quero também um orçamento de anúncios (a NUME cria a mídia; a verba você paga direto à plataforma)') }}</span>
            </label>
            <div>
                <label for="ads_budget" class="{{ $label }}">{{ __('Verba mensal pensada para anúncios') }}</label>
                <input id="ads_budget" name="ads_budget" type="text" maxlength="60" value="{{ old('ads_budget', $defaults['ads_budget']) }}" class="{{ $input }}">
            </div>
        </div>

        <div>
            <label for="message" class="{{ $label }}">{{ __('Algo mais que a produtora precisa saber?') }}</label>
            <textarea id="message" name="message" rows="3" maxlength="2000" class="{{ $input }}">{{ old('message') }}</textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex justify-center items-center px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-orange-600 hover:bg-orange-500 shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                {{ __('Enviar pedido de orçamento') }}
            </button>
        </div>
    </form>
</div>
@endsection
