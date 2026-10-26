@extends('layouts.portal')

@section('title', __('Suporte | Portal NUME'))

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">{{ __('Suporte') }}</h1>
            <p class="text-xs text-zinc-500 mt-1">{{ __('Fale com a equipe sobre produção, faturas, redes ou qualquer dúvida. Respondemos por aqui.') }}</p>
        </div>
        <a href="{{ route('portal.support.create') }}" class="inline-flex justify-center px-4 py-2.5 rounded-xl text-xs font-extrabold text-white bg-orange-600 hover:bg-orange-500 transition">{{ __('Abrir chamado') }}</a>
    </div>

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-xl text-sm font-semibold">{{ session('status') }}</div>
    @endif

    <div class="bg-white rounded-2xl border border-zinc-200/80 shadow-sm divide-y divide-zinc-100">
        @forelse ($tickets as $ticket)
            <a href="{{ route('portal.support.show', $ticket->id) }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-4 hover:bg-zinc-50 transition">
                <div>
                    <p class="text-sm font-bold text-zinc-900">#{{ $ticket->id }} · {{ $ticket->subject }}</p>
                    <p class="text-xs text-zinc-500">{{ \App\Models\SupportTicket::categoryLabels()[$ticket->category] ?? $ticket->category }} · {{ $ticket->last_reply_at?->format('d/m/Y H:i') }}</p>
                </div>
                <span class="self-start sm:self-auto px-2.5 py-1 rounded-full text-xs font-bold border {{ $ticket->status === 'aguardando_cliente' ? 'bg-amber-50 text-amber-800 border-amber-200' : ($ticket->isOpen() ? 'bg-sky-50 text-sky-800 border-sky-200' : 'bg-zinc-100 text-zinc-600 border-zinc-200') }}">
                    {{ \App\Models\SupportTicket::statusLabels()[$ticket->status] ?? $ticket->status }}
                </span>
            </a>
        @empty
            <p class="p-8 text-center text-sm text-zinc-500">{{ __('Você ainda não abriu nenhum chamado.') }}</p>
        @endforelse
    </div>
    {{ $tickets->links() }}
</div>
@endsection
