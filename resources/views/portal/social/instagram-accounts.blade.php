@extends('layouts.portal')

@section('title', __('Escolha a conta do Instagram') . ' | NUME')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">
            {{ __('Qual Instagram é da sua marca?') }}
        </h1>
        <p class="text-xs sm:text-sm text-zinc-600 mt-1">
            {{ __('Você liberou mais de uma conta. Escolha a que deve receber os vídeos.') }}
        </p>
    </div>

    <form action="{{ route('portal.social.instagram.accounts.store') }}" method="POST" class="space-y-3">
        @csrf

        @foreach($accounts as $account)
            <label class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-zinc-200 shadow-xs cursor-pointer transition-all duration-200 hover:border-orange-300 hover:shadow-md has-[:checked]:border-orange-500 has-[:checked]:ring-2 has-[:checked]:ring-orange-500/20">
                <input type="radio" name="account_id" value="{{ $account['id'] }}" class="w-4 h-4 text-orange-600 focus:ring-orange-500" @checked($loop->first) required>
                <span class="w-10 h-10 rounded-xl flex items-center justify-center border border-zinc-200/80 bg-zinc-50 shrink-0">
                    @include('portal.profile._social-icon', ['platform' => 'instagram', 'iconClass' => 'w-5 h-5'])
                </span>
                <span class="flex-1 min-w-0 text-sm font-bold text-zinc-900 truncate">
                    {{ $account['username'] ? '@'.ltrim($account['username'], '@') : __('Conta sem nome de usuário') }}
                </span>
            </label>
        @endforeach

        @error('account_id')
            <p class="text-xs text-rose-600 font-medium">{{ $message }}</p>
        @enderror

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
            <a href="{{ route('portal.social.index') }}"
                class="inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-700 bg-white border border-zinc-200 hover:bg-zinc-50 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-zinc-400">
                {{ __('Cancelar') }}
            </a>
            <button type="submit"
                class="inline-flex justify-center items-center px-5 py-2.5 rounded-xl text-xs font-extrabold text-white bg-gradient-to-r from-orange-600 to-amber-500 hover:from-orange-500 hover:to-amber-400 shadow-sm transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                {{ __('Conectar esta conta') }}
            </button>
        </div>
    </form>
</div>
@endsection
