@extends('layouts.app')

@section('title', ($lead->exists ? __('Editar lead') : __('Novo lead')).' | NUME')
@section('breadcrumb', __('CRM'))

@php
    $field = 'w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-500';
    $label = 'block text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-1';
@endphp

@section('content')
<div class="max-w-3xl space-y-5">
    <div>
        <a href="{{ $lead->exists ? route('crm.leads.show', $lead) : route('crm.leads.index') }}" class="text-xs text-zinc-400 hover:text-white">← {{ __('Voltar') }}</a>
        <h1 class="text-2xl font-extrabold text-white tracking-tight mt-1">{{ $lead->exists ? __('Editar lead') : __('Novo lead') }}</h1>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs space-y-1">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form action="{{ $lead->exists ? route('crm.leads.update', $lead) : route('crm.leads.store') }}" method="POST" class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 grid grid-cols-1 sm:grid-cols-2 gap-4">
        @csrf
        @if($lead->exists) @method('PUT') @endif

        <div>
            <label for="name" class="{{ $label }}">{{ __('Nome do contato') }} *</label>
            <input id="name" name="name" required maxlength="255" value="{{ old('name', $lead->name) }}" class="{{ $field }}">
        </div>
        <div>
            <label for="company" class="{{ $label }}">{{ __('Empresa') }}</label>
            <input id="company" name="company" maxlength="255" value="{{ old('company', $lead->company) }}" class="{{ $field }}">
        </div>
        <div>
            <label for="email" class="{{ $label }}">{{ __('E-mail') }}</label>
            <input id="email" name="email" type="email" maxlength="255" value="{{ old('email', $lead->email) }}" class="{{ $field }}">
        </div>
        <div>
            <label for="phone" class="{{ $label }}">{{ __('Telefone') }}</label>
            <input id="phone" name="phone" maxlength="50" value="{{ old('phone', $lead->phone) }}" class="{{ $field }}">
        </div>
        <div>
            <label for="source" class="{{ $label }}">{{ __('Origem') }} *</label>
            <select id="source" name="source" class="{{ $field }}">
                @foreach(\App\Models\Lead::sourceLabels() as $value => $text)
                    <option value="{{ $value }}" @selected(old('source', $lead->source ?? 'manual') === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="owner_id" class="{{ $label }}">{{ __('Responsável') }}</label>
            <select id="owner_id" name="owner_id" class="{{ $field }}">
                <option value="">{{ __('Ninguém') }}</option>
                @foreach($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((int) old('owner_id', $lead->owner_id) === $owner->id)>{{ $owner->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="estimated_value" class="{{ $label }}">{{ __('Valor estimado (€)') }}</label>
            <input id="estimated_value" name="estimated_value" type="number" min="0" step="0.01" value="{{ old('estimated_value', $lead->estimated_value) }}" class="{{ $field }}">
        </div>
        <div>
            <label for="next_action_at" class="{{ $label }}">{{ __('Próximo contato') }}</label>
            <input id="next_action_at" name="next_action_at" type="date" value="{{ old('next_action_at', $lead->next_action_at?->format('Y-m-d')) }}" class="{{ $field }}">
        </div>
        <div class="sm:col-span-2">
            <label for="notes" class="{{ $label }}">{{ __('Observações') }}</label>
            <textarea id="notes" name="notes" rows="3" maxlength="5000" class="{{ $field }}">{{ old('notes', $lead->notes) }}</textarea>
        </div>

        <fieldset class="sm:col-span-2 p-4 rounded-xl bg-zinc-950 border border-zinc-800 space-y-2">
            <legend class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 px-1">{{ __('Proteção de dados (RGPD)') }}</legend>
            <label for="legal_basis" class="{{ $label }}">{{ __('Base legal do tratamento') }} *</label>
            <select id="legal_basis" name="legal_basis" class="{{ $field }}">
                <option value="legitimate_interest" @selected(old('legal_basis', $lead->legal_basis) === 'legitimate_interest')>{{ __('Interesse legítimo (contato comercial B2B)') }}</option>
                <option value="consent" @selected(old('legal_basis', $lead->legal_basis) === 'consent')>{{ __('Consentimento') }}</option>
                <option value="contract" @selected(old('legal_basis', $lead->legal_basis) === 'contract')>{{ __('Contrato ou pré-contrato') }}</option>
            </select>
            <label class="flex items-center gap-2 text-xs text-zinc-300">
                <input type="hidden" name="consent_given" value="0">
                <input type="checkbox" name="consent_given" value="1" @checked(old('consent_given', $lead->consent_at !== null)) class="rounded border-zinc-700 bg-zinc-950 text-orange-600">
                <span>{{ __('A pessoa deu consentimento para receber contato (registra data e hora)') }}</span>
            </label>
        </fieldset>

        <div class="sm:col-span-2 flex justify-end">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-sm transition">{{ __('Salvar lead') }}</button>
        </div>
    </form>
</div>
@endsection
