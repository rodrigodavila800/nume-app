@extends('layouts.app')

@section('title', __('Suporte').' | NUME')
@section('breadcrumb', __('Suporte'))

@php
    $statusLabels = \App\Models\SupportTicket::statusLabels();
    $tabs = ['abertos' => __('Em aberto')] + $statusLabels;
@endphp

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Suporte') }}</h1>
        <p class="text-xs text-zinc-400 mt-1">{{ __('Demandas dos clientes abertas pelo portal ou pelo chat da Sofi. Urgentes e altas primeiro.') }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-1.5">
        @foreach($tabs as $key => $label)
            <a href="{{ route('support.index', ['status' => $key]) }}"
               class="px-3 py-1.5 rounded-xl text-xs font-semibold transition {{ $status === $key ? 'bg-orange-600 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-800' }}">{{ $label }}</a>
        @endforeach
        <a href="{{ route('support.index', ['status' => $status, 'mine' => 1]) }}" class="px-3 py-1.5 rounded-xl text-xs text-zinc-400 hover:text-white">{{ __('Só meus') }}</a>
    </div>

    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-950/80 border-b border-zinc-800 text-[10px] uppercase font-bold text-zinc-500 tracking-wider">
                    <tr>
                        <th class="py-3 px-4">#</th>
                        <th class="py-3 px-4">{{ __('Assunto') }}</th>
                        <th class="py-3 px-4">{{ __('Cliente') }}</th>
                        <th class="py-3 px-4">{{ __('Prioridade') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4">{{ __('Responsável') }}</th>
                        <th class="py-3 px-4">{{ __('Última mensagem') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80">
                    @forelse($tickets as $ticket)
                        <tr class="hover:bg-zinc-800/40 transition">
                            <td class="py-3 px-4 font-mono text-zinc-500">{{ $ticket->id }}</td>
                            <td class="py-3 px-4"><a href="{{ route('support.show', $ticket) }}" class="font-bold text-white hover:text-orange-400">{{ $ticket->subject }}</a></td>
                            <td class="py-3 px-4 text-zinc-300">{{ $ticket->cliente?->company_name ?: $ticket->cliente?->name ?: '—' }}</td>
                            <td class="py-3 px-4 {{ in_array($ticket->priority, ['urgente', 'alta'], true) ? 'text-rose-300 font-bold' : 'text-zinc-400' }}">{{ \App\Models\SupportTicket::priorityLabels()[$ticket->priority] ?? $ticket->priority }}</td>
                            <td class="py-3 px-4 text-zinc-300">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</td>
                            <td class="py-3 px-4 text-zinc-400">{{ $ticket->assignee?->name ?? '—' }}</td>
                            <td class="py-3 px-4 text-zinc-500">{{ $ticket->last_reply_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-zinc-500">{{ __('Nenhum chamado aqui.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $tickets->links() }}
</div>
@endsection
