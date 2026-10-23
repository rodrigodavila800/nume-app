@extends('layouts.app')

@section('title', __('TPV Redsys & Bizum da Produtora'))
@section('breadcrumb', __('TPV Redsys'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-blue-500/10 text-blue-400 border border-blue-500/20 mb-2">
                {{ __('BBVA / Redsys TPV Virtual') }}
            </span>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('TPV Redsys & Bizum da Produtora') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">
                {{ __('Configuração do terminal virtual próprio para recebimento via Bizum e cartões na vitrine e portal.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-zinc-800 text-zinc-300 border border-zinc-700">
                🏢 {{ $tenant->name }}
            </span>
        </div>
    </div>

    <x-settings-tabs active="redsys" />

    @if (session('status'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold flex items-center gap-2">
            <span>✅</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold space-y-1">
            <p class="font-bold">⚠️ {{ __('Corrija os erros abaixo para salvar:') }}</p>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.settings.redsys.update') }}" method="POST" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-6">
        @csrf

        <div class="pb-4 border-b border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                    <span>🏦</span> {{ __('Terminal Virtual da Produtora') }}
                </h2>
                <p class="text-xs text-zinc-400 mt-0.5">
                    {{ __('As transações aprovadas creditam diretamente na conta bancária vinculada ao seu TPV.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if ($enabled && !empty($merchantCode) && $hasSecretKey)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        {{ __('TPV Ativo') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        {{ __('Aguardando Configuração') }}
                    </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <label class="flex items-start gap-3 p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 cursor-pointer hover:border-zinc-700 transition">
                <input type="checkbox" name="redsys_enabled" value="1" {{ old('redsys_enabled', $enabled) ? 'checked' : '' }} class="mt-0.5 rounded border-zinc-700 text-blue-600 focus:ring-blue-500">
                <div>
                    <span class="text-xs font-bold text-white block">{{ __('Ativar Redsys / Bizum') }}</span>
                    <span class="text-[11px] text-zinc-400">{{ __('Habilita o processamento via terminal BBVA/Redsys.') }}</span>
                </div>
            </label>

            <label class="flex items-start gap-3 p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 cursor-pointer hover:border-zinc-700 transition">
                <input type="checkbox" name="redsys_bizum_enabled" value="1" {{ old('redsys_bizum_enabled', $bizumEnabled) ? 'checked' : '' }} class="mt-0.5 rounded border-zinc-700 text-emerald-600 focus:ring-emerald-500">
                <div>
                    <span class="text-xs font-bold text-white block">{{ __('Habilitar Bizum') }}</span>
                    <span class="text-[11px] text-zinc-400">{{ __('Exibe opção de pagamento instantâneo por telefone.') }}</span>
                </div>
            </label>

            <label class="flex items-start gap-3 p-4 rounded-xl border border-zinc-800 bg-zinc-950/60 cursor-pointer hover:border-zinc-700 transition">
                <input type="checkbox" name="redsys_card_enabled" value="1" {{ old('redsys_card_enabled', $cardEnabled) ? 'checked' : '' }} class="mt-0.5 rounded border-zinc-700 text-blue-600 focus:ring-blue-500">
                <div>
                    <span class="text-xs font-bold text-white block">{{ __('Habilitar Cartões') }}</span>
                    <span class="text-[11px] text-zinc-400">{{ __('Aceita cartões de crédito e débito no formulário Redsys.') }}</span>
                </div>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="redsys_merchant_code" class="block text-xs font-bold text-zinc-300 mb-1">
                    {{ __('Código de Comércio (FUC)') }}
                </label>
                <input type="text"
                       name="redsys_merchant_code"
                       id="redsys_merchant_code"
                       value="{{ old('redsys_merchant_code', $merchantCode) }}"
                       placeholder="Ex: 999008881"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-white text-xs placeholder-zinc-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-mono">
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Número de 9 dígitos fornecido pelo banco.') }}</p>
            </div>

            <div>
                <label for="redsys_terminal" class="block text-xs font-bold text-zinc-300 mb-1">
                    {{ __('Número do Terminal') }}
                </label>
                <input type="text"
                       name="redsys_terminal"
                       id="redsys_terminal"
                       value="{{ old('redsys_terminal', $terminal) }}"
                       placeholder="001"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-white text-xs placeholder-zinc-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-mono">
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Geralmente 001 para vendas online.') }}</p>
            </div>

            <div>
                <label for="redsys_environment" class="block text-xs font-bold text-zinc-300 mb-1">
                    {{ __('Ambiente do TPV') }}
                </label>
                <select name="redsys_environment"
                        id="redsys_environment"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-white text-xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="test" {{ old('redsys_environment', $environment) === 'test' ? 'selected' : '' }}>
                        🧪 {{ __('Testes / Sandbox (sis-t.redsys.es)') }}
                    </option>
                    <option value="live" {{ old('redsys_environment', $environment) === 'live' ? 'selected' : '' }}>
                        🚀 {{ __('Produção Real (sis.redsys.es)') }}
                    </option>
                </select>
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Alterne para Produção quando o banco homologar.') }}</p>
            </div>
        </div>

        <div>
            <label for="redsys_secret_key" class="block text-xs font-bold text-zinc-300 mb-1">
                {{ __('Chave Secreta de Assinatura (HMAC SHA-256)') }}
                @if ($hasSecretKey)
                    <span class="text-emerald-400 font-normal ml-2">✓ {{ __('Chave cadastrada e criptografada com AES-256') }}</span>
                @endif
            </label>
            <input type="password"
                   name="redsys_secret_key"
                   id="redsys_secret_key"
                   placeholder="{{ $hasSecretKey ? '•••••••••••••••••••••••••••••••• (deixe em branco para manter a atual)' : 'Insira a chave secreta gerada no portal de administração Redsys' }}"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-white text-xs placeholder-zinc-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-mono">
            <p class="text-[11px] text-zinc-500 mt-1">
                {{ __('Chave Base64 obtida no painel da Redsys (Comércio > Consultar Dados > Chave Secreta). Nunca é exibida em texto plano.') }}
            </p>
        </div>

        <div class="p-4 rounded-xl bg-blue-500/5 border border-blue-500/15 text-xs text-zinc-300 space-y-1">
            <p class="font-bold text-blue-400">ℹ️ {{ __('Configuração de Notificação Online no Painel do Banco:') }}</p>
            <p class="text-[11px] text-zinc-400">
                {{ __('No painel administrativo Redsys da sua produtora, configure a URL de Notificação Online HTTP como:') }}
                <code class="px-2 py-0.5 rounded bg-zinc-950 text-blue-300 border border-zinc-800 font-mono">{{ url('/webhooks/redsys') }}</code>
            </p>
        </div>

        <div class="pt-4 border-t border-zinc-800 flex items-center justify-end">
            <button type="submit"
                    class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition shadow-sm flex items-center gap-2 cursor-pointer">
                <span>💾</span>
                <span>{{ __('Salvar Credenciais do TPV') }}</span>
            </button>
        </div>
    </form>
</div>
@endsection
