@extends('layouts.app')

@section('title', __('Dados Bancários para Transferência (IBAN)'))
@section('breadcrumb', __('Transferência Bancária'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mb-2">
                {{ __('Transferência Manual') }}
            </span>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Transferência Bancária da Produtora') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">
                {{ __('Receba pagamentos de pacotes e produções diretamente em sua conta corrente via transferência SEPA/PIX/Wire.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-zinc-800 text-zinc-300 border border-zinc-700">
                🏢 {{ $tenant->name }}
            </span>
        </div>
    </div>

    <x-settings-tabs active="bank_transfer" />

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

    <form action="{{ route('admin.settings.bank-transfer.update') }}" method="POST" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-6">
        @csrf

        <div class="pb-4 border-b border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                    <span>🏛️</span> {{ __('Conta Bancária de Depósito') }}
                </h2>
                <p class="text-xs text-zinc-400 mt-0.5">
                    {{ __('Estes dados serão apresentados ao cliente na vitrine e portal após a escolha de pagamento por transferência.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if ($enabled && !empty($iban))
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        {{ __('Ativo na Vitrine e Portal') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-zinc-800 text-zinc-400 border border-zinc-700">
                        {{ __('Inativo / Não configurado') }}
                    </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="flex items-center gap-3 p-4 rounded-xl bg-zinc-950/60 border border-zinc-800 cursor-pointer hover:border-zinc-700 transition-colors">
                    <input type="checkbox" name="bank_transfer_enabled" value="1" {{ old('bank_transfer_enabled', $enabled) ? 'checked' : '' }} class="w-4 h-4 rounded border-zinc-700 text-emerald-600 focus:ring-emerald-500 bg-zinc-900">
                    <div class="text-xs">
                        <span class="font-bold text-white block">{{ __('Habilitar Transferência Bancária como opção de pagamento') }}</span>
                        <span class="text-zinc-400 block mt-0.5">{{ __('Quando ativado, os clientes poderão optar por pagar via transferência ao adquirir pacotes.') }}</span>
                    </div>
                </label>
            </div>

            <div>
                <label for="bank_transfer_account_holder" class="block text-xs font-bold uppercase tracking-wider text-zinc-400 mb-1">
                    {{ __('Titular da Conta / Razão Social') }} <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="bank_transfer_account_holder" name="bank_transfer_account_holder" value="{{ old('bank_transfer_account_holder', $accountHolder) }}" placeholder="Ex.: Acme Filmes S.L." class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-100 text-xs focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Nome que deve constar como beneficiário da transferência.') }}</p>
            </div>

            <div>
                <label for="bank_transfer_bank_name" class="block text-xs font-bold uppercase tracking-wider text-zinc-400 mb-1">
                    {{ __('Nome do Banco / Instituição') }}
                </label>
                <input type="text" id="bank_transfer_bank_name" name="bank_transfer_bank_name" value="{{ old('bank_transfer_bank_name', $bankName) }}" placeholder="Ex.: BBVA / Banco Santander / CaixaBank" class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-100 text-xs focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Opcional, ajuda o cliente a identificar o banco de destino.') }}</p>
            </div>

            <div>
                <label for="bank_transfer_iban" class="block text-xs font-bold uppercase tracking-wider text-zinc-400 mb-1">
                    {{ __('IBAN / Conta Bancária') }} <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="bank_transfer_iban" name="bank_transfer_iban" value="{{ old('bank_transfer_iban', $iban) }}" placeholder="ES00 0000 0000 0000 0000 0000" class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-100 font-mono text-xs focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Formato internacional IBAN ou número de conta completo.') }}</p>
            </div>

            <div>
                <label for="bank_transfer_bic" class="block text-xs font-bold uppercase tracking-wider text-zinc-400 mb-1">
                    {{ __('Código SWIFT / BIC') }}
                </label>
                <input type="text" id="bank_transfer_bic" name="bank_transfer_bic" value="{{ old('bank_transfer_bic', $bic) }}" placeholder="BBVAESMMXXX" class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-100 font-mono text-xs focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 uppercase">
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Recomendado para transferências internacionais/SEPA.') }}</p>
            </div>

            <div>
                <label for="bank_transfer_expiration_days" class="block text-xs font-bold uppercase tracking-wider text-zinc-400 mb-1">
                    {{ __('Prazo para Pagamento (Dias)') }} <span class="text-rose-500">*</span>
                </label>
                <input type="number" id="bank_transfer_expiration_days" name="bank_transfer_expiration_days" min="1" max="30" value="{{ old('bank_transfer_expiration_days', $expirationDays) }}" class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-100 text-xs focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Após esse prazo sem confirmação, o pedido de transferência é expirado automaticamente.') }}</p>
            </div>

            <div>
                <label for="bank_transfer_instructions" class="block text-xs font-bold uppercase tracking-wider text-zinc-400 mb-1">
                    {{ __('Instruções ou Observações Adicionais') }}
                </label>
                <textarea id="bank_transfer_instructions" name="bank_transfer_instructions" rows="3" placeholder="Ex.: Enviar o comprovante de transferência para financeiro@suaprodutora.com com o código de referência." class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-100 text-xs focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">{{ old('bank_transfer_instructions', $instructions) }}</textarea>
                <p class="text-[11px] text-zinc-500 mt-1">{{ __('Orientações complementares exibidas na tela pós-checkout.') }}</p>
            </div>
        </div>

        <div class="p-4 rounded-xl bg-zinc-950/70 border border-zinc-800 text-zinc-400 text-xs space-y-2">
            <div class="flex items-center gap-2 font-bold text-zinc-200">
                <span>🛡️</span>
                <span>{{ __('Fluxo de Confirmação & Faturamento') }}</span>
            </div>
            <ul class="list-disc pl-5 space-y-1 text-zinc-400 text-[11px]">
                <li>{{ __('Toda solicitação de transferência gera um código identificador único (ex.: BT-A1B2C3D4) que o cliente deve informar no conceito do comprovante.') }}</li>
                <li>{{ __('A liberação é manual: somente administradores da sua produtora podem validar o recebimento bancário em Faturas / Compras.') }}</li>
                <li>{{ __('A confirmação gera atomicamente a fatura oficial com o número sequencial da sua produtora e cria o projeto inicial em Produções.') }}</li>
                <li>{{ __('A comissão da plataforma sobre a venda é lançada como pendente de cobrança posterior (pending_billing) na fatura da sua assinatura SaaS.') }}</li>
            </ul>
        </div>

        <div class="flex justify-end pt-4 border-t border-zinc-800">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs shadow-sm transition-all focus:ring-2 focus:ring-emerald-500/50">
                💾 {{ __('Salvar Dados Bancários') }}
            </button>
        </div>
    </form>
</div>
@endsection
