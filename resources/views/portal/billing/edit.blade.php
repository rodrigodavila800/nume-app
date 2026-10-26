@extends('layouts.portal')

@section('title', __('Dados de faturação | Portal NUME'))

@php
    $input = 'w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 bg-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors';
    $label = 'block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1';
@endphp

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">{{ __('Dados de faturação') }}</h1>
        <p class="text-xs text-zinc-500 mt-1">{{ __('Usados para emitir as suas faturas. Guardamos de forma criptografada (RGPD).') }}</p>
    </div>

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-xl text-sm font-semibold">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3.5 rounded-xl text-xs font-semibold">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('portal.billing.update') }}" method="POST" class="bg-white rounded-2xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm grid grid-cols-1 sm:grid-cols-2 gap-4">
        @csrf
        @method('PUT')

        <div>
            <label for="billing_type" class="{{ $label }}">{{ __('Tipo') }} *</label>
            <select id="billing_type" name="billing_type" class="{{ $input }}">
                <option value="empresa" @selected(old('billing_type', $values['billing_type']) === 'empresa')>{{ __('Empresa') }}</option>
                <option value="autonomo" @selected(old('billing_type', $values['billing_type']) === 'autonomo')>{{ __('Autônomo') }}</option>
                <option value="particular" @selected(old('billing_type', $values['billing_type']) === 'particular')>{{ __('Particular') }}</option>
            </select>
        </div>
        <div>
            <label for="billing_tax_id" class="{{ $label }}">{{ __('NIF / CIF / NIE') }}</label>
            <input id="billing_tax_id" name="billing_tax_id" maxlength="30" value="{{ old('billing_tax_id', $values['billing_tax_id']) }}" autocomplete="off" class="{{ $input }}">
        </div>
        <div class="sm:col-span-2">
            <label for="billing_legal_name" class="{{ $label }}">{{ __('Razão social ou nome completo') }} *</label>
            <input id="billing_legal_name" name="billing_legal_name" maxlength="255" required value="{{ old('billing_legal_name', $values['billing_legal_name']) }}" class="{{ $input }}">
        </div>
        <div class="sm:col-span-2">
            <label for="billing_address" class="{{ $label }}">{{ __('Endereço') }}</label>
            <input id="billing_address" name="billing_address" maxlength="255" value="{{ old('billing_address', $values['billing_address']) }}" autocomplete="street-address" class="{{ $input }}">
        </div>
        <div>
            <label for="billing_postal_code" class="{{ $label }}">{{ __('Código postal') }}</label>
            <input id="billing_postal_code" name="billing_postal_code" maxlength="20" value="{{ old('billing_postal_code', $values['billing_postal_code']) }}" autocomplete="postal-code" class="{{ $input }}">
        </div>
        <div>
            <label for="billing_city" class="{{ $label }}">{{ __('Cidade') }}</label>
            <input id="billing_city" name="billing_city" maxlength="100" value="{{ old('billing_city', $values['billing_city']) }}" class="{{ $input }}">
        </div>
        <div>
            <label for="billing_country" class="{{ $label }}">{{ __('País (código)') }} *</label>
            <input id="billing_country" name="billing_country" maxlength="2" required value="{{ old('billing_country', $values['billing_country']) }}" class="{{ $input }} uppercase">
        </div>
        <div class="sm:col-span-2 flex justify-end">
            <button type="submit" class="inline-flex justify-center items-center px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-orange-600 hover:bg-orange-500 shadow-sm transition">
                {{ __('Salvar dados de faturação') }}
            </button>
        </div>
    </form>
</div>
@endsection
