@extends('layouts.app')

@section('title', __('NUME | Novo Cliente'))
@section('breadcrumb', __('Novo Cliente'))

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Cadastrar Novo Cliente') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('O cliente terá acesso ao Portal exclusivo para aprovação de roteiros e vídeos.') }}</p>
        </div>
        <a href="{{ route('clientes.index') }}" class="text-xs font-bold text-zinc-400 hover:text-white transition flex items-center gap-1.5">
            ← {{ __('Voltar') }}
        </a>
    </div>

    <form action="{{ route('clientes.store') }}" method="POST" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-5">
        @csrf

        <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Nome do Responsável / Contato') }} <span class="text-orange-500">*</span>
            </label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   placeholder="Ex: Carlos Albuquerque"
                   class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('name') border-rose-500 @enderror">
            @error('name') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('E-mail de Acesso') }} <span class="text-orange-500">*</span>
            </label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   placeholder="carlos@empresa.com.br"
                   class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('email') border-rose-500 @enderror">
            @error('email') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Empresa / Marca') }}
                </label>
                <input type="text" name="company_name" value="{{ old('company_name') }}"
                       placeholder="Ex: Klay Tech"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Telefone / WhatsApp') }}
                </label>
                <input type="text" name="phone" value="{{ old('phone') }}"
                       placeholder="+55 (11) 99999-9999"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">
            </div>
        </div>

        <div class="p-3.5 rounded-xl bg-zinc-950 border border-zinc-800 text-xs text-zinc-400">
            {{ __('O cliente poderá acessar o portal com o código de acesso do workspace:') }} <strong class="text-orange-400 font-mono">{{ auth()->user()->tenant->slug ?? 'nume' }}</strong>
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-zinc-800">
            <button type="submit"
                    class="flex-1 sm:flex-initial bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-lg shadow-orange-900/20 transition-all">
                {{ __('Salvar Cliente') }}
            </button>
            <a href="{{ route('clientes.index') }}"
               class="flex-1 sm:flex-initial inline-flex items-center justify-center bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm px-6 py-2.5 rounded-xl transition">
                {{ __('Cancelar') }}
            </a>
        </div>
    </form>
</div>
@endsection
