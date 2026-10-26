@extends('layouts.app')

@section('title', __('Chamado #:id', ['id' => $ticket->id]).' | NUME')
@section('breadcrumb', __('Suporte'))

@php
    $field = 'w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-500';
    $label = 'block text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-1';
@endphp

@section('content')
<div class="space-y-5 max-w-5xl">
    <div>
        <a href="{{ route('support.index') }}" class="text-xs text-zinc-400 hover:text-white">← {{ __('Suporte') }}</a>
        <h1 class="text-2xl font-extrabold text-white tracking-tight mt-1">#{{ $ticket->id }} · {{ $ticket->subject }}</h1>
        <p class="text-xs text-zinc-400">
            {{ $ticket->cliente?->company_name ?: $ticket->cliente?->name }} · {{ \App\Models\SupportTicket::categoryLabels()[$ticket->category] ?? $ticket->category }}
            · {{ __('origem: :source', ['source' => $ticket->source]) }}
        </p>
    </div>

    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-semibold">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs space-y-1">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-3">
            @foreach($ticket->messages as $message)
                <div class="p-3 rounded-xl border {{ $message->internal ? 'bg-amber-950/30 border-amber-500/30' : ($message->author_type === 'cliente' ? 'bg-zinc-900 border-zinc-800' : 'bg-zinc-950 border-zinc-800') }}">
                    <div class="flex items-center justify-between text-[11px] text-zinc-500">
                        <span class="font-bold text-zinc-300">{{ $message->authorName() }}{{ $message->internal ? ' · '.__('nota interna') : '' }}</span>
                        <span>{{ $message->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <p class="text-sm text-zinc-200 mt-1 whitespace-pre-line">{{ $message->body }}</p>
                </div>
            @endforeach

            <form action="{{ route('support.reply', $ticket) }}" method="POST" class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-3">
                @csrf
                <label for="body" class="{{ $label }}">{{ __('Responder') }}</label>
                <textarea id="body" name="body" rows="4" maxlength="10000" required class="{{ $field }}"></textarea>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-xs text-zinc-300">
                        <input type="checkbox" name="internal" value="1" class="rounded border-zinc-700 bg-zinc-950 text-orange-600">
                        <span>{{ __('Nota interna (o cliente não vê)') }}</span>
                    </label>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition">{{ __('Enviar') }}</button>
                </div>
            </form>
        </div>

        <form action="{{ route('support.update', $ticket) }}" method="POST" class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-3 h-fit">
            @csrf
            @method('PATCH')
            <div>
                <label for="status" class="{{ $label }}">{{ __('Status') }}</label>
                <select id="status" name="status" class="{{ $field }}">
                    @foreach(\App\Models\SupportTicket::statusLabels() as $value => $text)
                        <option value="{{ $value }}" @selected($ticket->status === $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="priority" class="{{ $label }}">{{ __('Prioridade') }}</label>
                <select id="priority" name="priority" class="{{ $field }}">
                    @foreach(\App\Models\SupportTicket::priorityLabels() as $value => $text)
                        <option value="{{ $value }}" @selected($ticket->priority === $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="assigned_to" class="{{ $label }}">{{ __('Responsável') }}</label>
                <select id="assigned_to" name="assigned_to" class="{{ $field }}">
                    <option value="">{{ __('Ninguém') }}</option>
                    @foreach($team as $member)
                        <option value="{{ $member->id }}" @selected($ticket->assigned_to === $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="w-full px-4 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-bold border border-zinc-700 transition">{{ __('Salvar') }}</button>
        </form>
    </div>
</div>
@endsection
