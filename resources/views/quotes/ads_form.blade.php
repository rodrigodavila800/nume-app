@extends('layouts.app')

@section('title', ($quote->exists ? __('Orçamento de anúncios').' '.$quote->number : __('Novo orçamento de anúncios')).' | NUME')
@section('breadcrumb', __('Orçamento de anúncios'))

@php
    $field = 'w-full bg-zinc-950 border border-zinc-800 text-zinc-100 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-500';
    $label = 'block text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-1';
    $calc = $quote->calculation ?? [];
    $selectedPlatforms = old('inputs.plataformas', $inputs['plataformas'] ?? []);
@endphp

@section('content')
<div class="space-y-6 max-w-5xl">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('quotes.index') }}" class="text-xs text-zinc-400 hover:text-white transition">← {{ __('Voltar para lista') }}</a>
            <h1 class="text-2xl font-extrabold text-white tracking-tight mt-1">
                {{ $quote->exists ? $quote->client_name.' · '.$quote->number : __('Novo orçamento de anúncios') }}
            </h1>
            <p class="text-xs text-zinc-400 mt-0.5">
                {{ __('A NUME cobra a criação dos criativos e a gestão da campanha. A verba de anúncio é paga pelo cliente direto à plataforma e só aparece como informação.') }}
            </p>
        </div>
        @if($quote->exists)
            <div class="flex flex-wrap items-center gap-2">
                <span class="uppercase text-xs font-bold text-orange-400">{{ $quote->status }}</span>
                <a href="{{ route('quotes.pdf', $quote) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 font-bold text-xs transition border border-zinc-700">{{ __('Baixar PDF') }}</a>
            </div>
        @endif
    </div>

    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-semibold">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs font-semibold">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs font-semibold space-y-1">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @if($quote->request_details)
        <div class="p-4 rounded-2xl bg-sky-950/40 border border-sky-500/30 text-xs text-sky-100 space-y-1">
            <p class="font-extrabold text-sky-300">{{ __('Pedido do cliente pelo portal') }}</p>
            <p>{{ __('Verba pensada:') }} {{ $quote->request_details['ads_budget'] ?? '—' }}</p>
            @if(! empty($quote->request_details['message']))
                <p><span class="font-bold">{{ __('Mensagem:') }}</span> {{ $quote->request_details['message'] }}</p>
            @endif
        </div>
    @endif

    <form action="{{ $quote->exists ? route('quotes.update', $quote) : route('quotes.store') }}" method="POST" class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-5">
        @csrf
        @if($quote->exists)
            @method('PUT')
        @else
            <input type="hidden" name="kind" value="ads">
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="cliente_id" class="{{ $label }}">{{ __('Cliente cadastrado') }}</label>
                <select id="cliente_id" name="cliente_id" class="{{ $field }}">
                    <option value="">{{ __('Nenhum') }}</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected((int) old('cliente_id', $quote->cliente_id) === $cliente->id)>{{ $cliente->company_name ?: $cliente->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="client_name" class="{{ $label }}">{{ __('Nome no orçamento') }} *</label>
                <input id="client_name" name="client_name" required maxlength="255" value="{{ old('client_name', $quote->client_name) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="client_email" class="{{ $label }}">{{ __('E-mail') }}</label>
                <input id="client_email" name="client_email" type="email" maxlength="255" value="{{ old('client_email', $quote->client_email) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="project_title" class="{{ $label }}">{{ __('Título') }}</label>
                <input id="project_title" name="project_title" maxlength="255" value="{{ old('project_title', $quote->project_title) }}" placeholder="Campaña de anuncios" class="{{ $field }}">
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <label for="criativos" class="{{ $label }}">{{ __('Criativos') }} *</label>
                <input id="criativos" name="inputs[criativos]" type="number" min="0" max="100" required value="{{ old('inputs.criativos', $inputs['criativos'] ?? 4) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="valor_criativo" class="{{ $label }}">{{ __('€ por criativo') }} *</label>
                <input id="valor_criativo" name="inputs[valor_criativo]" type="number" min="0" step="0.01" required value="{{ old('inputs.valor_criativo', $inputs['valor_criativo'] ?? 0) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="gestao_mensal" class="{{ $label }}">{{ __('Gestão (€/mês)') }} *</label>
                <input id="gestao_mensal" name="inputs[gestao_mensal]" type="number" min="0" step="0.01" required value="{{ old('inputs.gestao_mensal', $inputs['gestao_mensal'] ?? 0) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="meses" class="{{ $label }}">{{ __('Meses') }} *</label>
                <input id="meses" name="inputs[meses]" type="number" min="1" max="24" required value="{{ old('inputs.meses', $inputs['meses'] ?? 1) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="verba_mensal" class="{{ $label }}">{{ __('Verba (€/mês, informativa)') }}</label>
                <input id="verba_mensal" name="inputs[verba_mensal]" type="number" min="0" step="0.01" value="{{ old('inputs.verba_mensal', $inputs['verba_mensal'] ?? 0) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="iva" class="{{ $label }}">{{ __('IVA (%)') }} *</label>
                <input id="iva" name="inputs[iva]" type="number" min="0" max="100" step="0.01" required value="{{ old('inputs.iva', $inputs['iva'] ?? 21) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="adicional" class="{{ $label }}">{{ __('Ajuste (€)') }}</label>
                <input id="adicional" name="inputs[adicional]" type="number" step="0.01" value="{{ old('inputs.adicional', $inputs['adicional'] ?? 0) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="prazo" class="{{ $label }}">{{ __('Prazo (dias)') }} *</label>
                <input id="prazo" name="inputs[prazo]" type="number" min="1" max="365" required value="{{ old('inputs.prazo', $inputs['prazo'] ?? 10) }}" class="{{ $field }}">
            </div>
        </div>

        <fieldset>
            <legend class="{{ $label }}">{{ __('Plataformas') }}</legend>
            <div class="flex flex-wrap gap-2">
                @foreach(\App\Services\Quotes\QuoteCalculatorService::AD_PLATFORMS as $platform)
                    <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-zinc-800 text-xs text-zinc-300 cursor-pointer has-[:checked]:border-orange-500">
                        <input type="checkbox" name="inputs[plataformas][]" value="{{ $platform }}" @checked(in_array($platform, $selectedPlatforms, true)) class="rounded border-zinc-700 bg-zinc-950 text-orange-600">
                        <span>{{ ucfirst($platform) }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div>
            <label for="language" class="{{ $label }}">{{ __('Idioma da proposta') }}</label>
            <select id="language" name="language" class="{{ $field }} sm:w-60">
                <option value="es" @selected(old('language', $quote->language ?? 'es') === 'es')>Español</option>
                <option value="pt_BR" @selected(old('language', $quote->language) === 'pt_BR')>Português (Brasil)</option>
            </select>
        </div>

        @if($quote->exists)
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800"><span class="text-zinc-500 block">{{ __('Criação') }}</span><span class="font-mono text-white">€ {{ number_format($calc['criacao'] ?? 0, 2, ',', '.') }}</span></div>
                <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800"><span class="text-zinc-500 block">{{ __('Gestão') }}</span><span class="font-mono text-white">€ {{ number_format($calc['gestao'] ?? 0, 2, ',', '.') }}</span></div>
                <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800"><span class="text-zinc-500 block">{{ __('Total com IVA') }}</span><span class="font-mono text-orange-400 font-bold">€ {{ number_format($quote->total_amount, 2, ',', '.') }}</span></div>
                <div class="p-3 rounded-xl bg-zinc-950 border border-zinc-800"><span class="text-zinc-500 block">{{ __('Verba (fora do total)') }}</span><span class="font-mono text-zinc-300">€ {{ number_format($calc['verba_total_info'] ?? 0, 2, ',', '.') }}</span></div>
            </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-sm transition">{{ __('Salvar e calcular') }}</button>
        </div>
    </form>

    @if($quote->exists && ! $quote->isAccepted())
        <form action="{{ route('quotes.send', $quote) }}" method="POST" class="p-5 rounded-2xl bg-zinc-900 border border-zinc-800 flex flex-col sm:flex-row gap-3 sm:items-end">
            @csrf
            <div class="flex-1">
                <label for="send_email" class="{{ $label }}">{{ __('Enviar proposta para') }}</label>
                <input id="send_email" name="email" type="email" required value="{{ $quote->client_email }}" class="{{ $field }}">
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm transition">{{ __('Enviar proposta com PDF') }}</button>
        </form>
    @endif
</div>
@endsection
