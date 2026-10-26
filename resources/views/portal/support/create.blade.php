@extends('layouts.portal')

@section('title', __('Abrir chamado | Portal NUME'))

@php
    $input = 'w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 bg-white focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors';
    $label = 'block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1';
@endphp

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-6">
    <div>
        <a href="{{ route('portal.support.index') }}" class="text-xs font-bold text-zinc-500 hover:text-orange-600 transition">← {{ __('Suporte') }}</a>
        <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight mt-1">{{ __('Abrir chamado') }}</h1>
    </div>

    @if ($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3.5 rounded-xl text-xs font-semibold">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form action="{{ route('portal.support.store') }}" method="POST" class="bg-white rounded-2xl p-6 border border-zinc-200/80 shadow-sm space-y-4">
        @csrf
        @if (request()->query('origem') === 'chat')
            <input type="hidden" name="from_chat" value="1">
        @endif
        <div>
            <label for="subject" class="{{ $label }}">{{ __('Assunto') }} *</label>
            <input id="subject" name="subject" maxlength="200" required value="{{ old('subject', $subject) }}" class="{{ $input }}">
        </div>
        <div>
            <label for="category" class="{{ $label }}">{{ __('Sobre o quê?') }} *</label>
            <select id="category" name="category" class="{{ $input }}">
                @foreach (\App\Models\SupportTicket::categoryLabels() as $value => $text)
                    <option value="{{ $value }}" @selected(old('category') === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="body" class="{{ $label }}">{{ __('Conte o que precisa') }} *</label>
            <textarea id="body" name="body" rows="6" maxlength="10000" required class="{{ $input }}">{{ old('body', $body) }}</textarea>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-orange-600 hover:bg-orange-500 transition">{{ __('Enviar chamado') }}</button>
        </div>
    </form>
</div>
@endsection
