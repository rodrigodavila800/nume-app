@extends('layouts.portal')

@section('title', __('Chamado #:id | Portal NUME', ['id' => $ticket->id]))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 space-y-5">
    <div>
        <a href="{{ route('portal.support.index') }}" class="text-xs font-bold text-zinc-500 hover:text-orange-600 transition">← {{ __('Suporte') }}</a>
        <h1 class="text-xl font-extrabold text-zinc-900 tracking-tight mt-1">#{{ $ticket->id }} · {{ $ticket->subject }}</h1>
        <p class="text-xs text-zinc-500">{{ \App\Models\SupportTicket::statusLabels()[$ticket->status] ?? $ticket->status }}</p>
    </div>

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-semibold">{{ session('error') }}</div>
    @endif

    <div class="space-y-3">
        @foreach ($ticket->publicMessages as $message)
            <div class="p-4 rounded-2xl border {{ $message->author_type === 'cliente' ? 'bg-orange-50/60 border-orange-100 ml-6' : 'bg-white border-zinc-200 mr-6' }}">
                <div class="flex items-center justify-between text-[11px] text-zinc-500">
                    <span class="font-bold text-zinc-700">{{ $message->author_type === 'cliente' ? __('Você') : ($message->author_type === 'system' ? __('Sistema') : __('Equipe NUME')) }}</span>
                    <span>{{ $message->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <p class="text-sm text-zinc-800 mt-1 whitespace-pre-line">{{ $message->body }}</p>
            </div>
        @endforeach
    </div>

    @if ($ticket->status !== 'fechado')
        <form action="{{ route('portal.support.reply', $ticket->id) }}" method="POST" class="bg-white rounded-2xl p-4 border border-zinc-200 space-y-3">
            @csrf
            <label for="body" class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Sua resposta') }}</label>
            <textarea id="body" name="body" rows="3" maxlength="10000" required class="w-full text-sm px-3 py-2.5 rounded-xl border border-zinc-300 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20"></textarea>
            <div class="flex justify-end">
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-orange-600 hover:bg-orange-500 transition">{{ __('Enviar') }}</button>
            </div>
        </form>
    @endif
</div>
@endsection
