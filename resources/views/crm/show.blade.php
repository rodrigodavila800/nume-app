@extends('layouts.app')

@section('title', ($lead->company ?: $lead->name).' · CRM | NUME')
@section('breadcrumb', __('CRM'))

@php
    $field = 'w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-orange-500';
    $label = 'block text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-1';
    $stages = \App\Models\Lead::stageLabels();
    $activityLabels = ['note' => __('Nota'), 'call' => __('Ligação'), 'email' => __('E-mail'), 'meeting' => __('Reunião'), 'whatsapp' => 'WhatsApp', 'stage_change' => __('Etapa'), 'system' => __('Sistema')];
@endphp

@section('content')
<div class="space-y-5 max-w-5xl">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
        <div>
            <a href="{{ route('crm.leads.index') }}" class="text-xs text-zinc-400 hover:text-white">← {{ __('Funil de vendas') }}</a>
            <h1 class="text-2xl font-extrabold text-white tracking-tight mt-1">{{ $lead->company ?: $lead->name }}</h1>
            <p class="text-xs text-zinc-400">
                {{ $lead->name }} · {{ $lead->email ?: __('sem e-mail') }} · {{ $lead->phone ?: __('sem telefone') }}
            </p>
            <p class="text-xs text-zinc-500 mt-1">
                {{ __('Origem') }}: {{ \App\Models\Lead::sourceLabels()[$lead->source] ?? $lead->source }}
                · {{ __('Etapa') }}: <span class="font-bold text-orange-300">{{ $stages[$lead->stage] ?? $lead->stage }}</span>
                @if($lead->owner) · {{ __('Responsável') }}: {{ $lead->owner->name }} @endif
                @if($lead->cliente) · {{ __('Cliente cadastrado') }} @endif
            </p>
            @if($lead->anonymized_at)
                <p class="text-xs text-amber-300 mt-1">{{ __('Dados pessoais anonimizados em :date (RGPD).', ['date' => $lead->anonymized_at->format('d/m/Y')]) }}</p>
            @elseif($lead->isStale())
                <p class="text-xs font-bold text-amber-400 mt-1">{{ __('Sem contato há mais de :days dias', ['days' => \App\Models\Lead::STALE_DAYS]) }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $lead)
                <a href="{{ route('crm.leads.edit', $lead) }}" class="px-3 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-bold border border-zinc-700 transition">{{ __('Editar') }}</a>
            @endcan
        </div>
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
        <div class="lg:col-span-1 space-y-4">
            @can('update', $lead)
                <form action="{{ route('crm.leads.stage', $lead) }}" method="POST" class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-3">
                    @csrf
                    <label for="stage" class="{{ $label }}">{{ __('Mover para a etapa') }}</label>
                    <select id="stage" name="stage" class="{{ $field }}">
                        @foreach($stages as $value => $text)
                            <option value="{{ $value }}" @selected($lead->stage === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                    <label for="lost_reason" class="{{ $label }}">{{ __('Motivo (se perdido)') }}</label>
                    <input id="lost_reason" name="lost_reason" maxlength="255" value="{{ old('lost_reason', $lead->lost_reason) }}" class="{{ $field }}">
                    <button type="submit" class="w-full px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition">{{ __('Atualizar etapa') }}</button>
                </form>
            @endcan

            <div class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-1 text-xs text-zinc-300">
                <p><span class="text-zinc-500">{{ __('Valor estimado') }}:</span> {{ $lead->estimated_value ? '€ '.number_format($lead->estimated_value, 2, ',', '.') : '—' }}</p>
                <p><span class="text-zinc-500">{{ __('Próximo contato') }}:</span> {{ $lead->next_action_at?->format('d/m/Y') ?? '—' }}</p>
                <p><span class="text-zinc-500">{{ __('Último contato') }}:</span> {{ $lead->last_contact_at?->format('d/m/Y H:i') ?? '—' }}</p>
                <p><span class="text-zinc-500">{{ __('Base legal') }}:</span> {{ $lead->legal_basis }}{{ $lead->consent_at ? ' · '.__('consentimento em :date', ['date' => $lead->consent_at->format('d/m/Y H:i')]) : '' }}</p>
                @if($lead->notes)
                    <p class="pt-2 text-zinc-400 whitespace-pre-line">{{ $lead->notes }}</p>
                @endif
            </div>

            @can('managePersonalData', $lead)
                @include('crm._privacy', ['lead' => $lead])
            @endcan
        </div>

        <div class="lg:col-span-2 space-y-4">
            @can('update', $lead)
                <form action="{{ route('crm.leads.activity', $lead) }}" method="POST" class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label for="type" class="{{ $label }}">{{ __('Tipo') }}</label>
                            <select id="type" name="type" class="{{ $field }}">
                                @foreach(['note', 'call', 'email', 'meeting', 'whatsapp'] as $type)
                                    <option value="{{ $type }}">{{ $activityLabels[$type] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="next_action_at_activity" class="{{ $label }}">{{ __('Próximo contato') }}</label>
                            <input id="next_action_at_activity" name="next_action_at" type="date" class="{{ $field }}">
                        </div>
                    </div>
                    <label for="body" class="{{ $label }}">{{ __('O que aconteceu?') }}</label>
                    <textarea id="body" name="body" rows="3" maxlength="5000" required class="{{ $field }}"></textarea>
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-bold border border-zinc-700 transition">{{ __('Registrar no histórico') }}</button>
                    </div>
                </form>
            @endcan

            <ol class="space-y-2">
                @forelse($lead->activities as $activity)
                    <li class="p-3 rounded-xl bg-zinc-900 border border-zinc-800">
                        <div class="flex items-center justify-between text-[11px] text-zinc-500">
                            <span class="font-bold {{ $activity->type === 'stage_change' ? 'text-orange-300' : 'text-zinc-300' }}">{{ $activityLabels[$activity->type] ?? $activity->type }}</span>
                            <span>{{ $activity->created_at?->format('d/m/Y H:i') }}{{ $activity->user ? ' · '.$activity->user->name : '' }}</span>
                        </div>
                        <p class="text-xs text-zinc-200 mt-1 whitespace-pre-line">{{ $activity->body }}</p>
                    </li>
                @empty
                    <li class="text-xs text-zinc-500">{{ __('Sem histórico ainda.') }}</li>
                @endforelse
            </ol>
        </div>
    </div>
</div>
@endsection
