@extends('layouts.app')

@section('title', __('Perfil Fiscal da Produtora'))
@section('breadcrumb', __('Perfil Fiscal'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mb-2">
                {{ __('Hacienda / Real Decreto 1619/2012') }}
            </span>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Perfil Fiscal da Produtora') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">
                {{ __('Configurações contábeis e fiscais oficiais para emissão de faturas da produtora.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-zinc-800 text-zinc-300 border border-zinc-700">
                🏢 {{ $tenant->name }}
            </span>
        </div>
    </div>

    <x-settings-tabs active="fiscal" />

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

    <form action="{{ route('admin.settings.fiscal.update') }}" method="POST" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-6">
        @csrf

        <div class="pb-4 border-b border-zinc-800">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                    <span>🏛️</span> {{ __('Regime Tributário & Retenções (Espanha / UE)') }}
                </h2>
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    {{ __('Oficial da Produtora') }}
                </span>
            </div>
            <p class="text-xs text-zinc-400 mt-0.5">
                {{ __('Estes dados são impressos nas faturas oficiais emitidas para os clientes da sua produtora.') }}
            </p>
        </div>

        <!-- Seleção de Regime Tributário -->
        <div class="space-y-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Regime Tributário do Titular') }} <span class="text-emerald-500">*</span>
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="flex items-start gap-3 p-3.5 rounded-xl border border-zinc-800 bg-zinc-950 cursor-pointer hover:border-emerald-500/50 transition">
                    <input type="radio" name="fiscal_regime" value="autonomo" @checked(old('fiscal_regime', $fiscal->regime) === 'autonomo')
                           class="text-emerald-600 focus:ring-emerald-500 mt-1">
                    <div>
                        <p class="text-xs font-extrabold text-white flex items-center gap-1.5">
                            <span>👤</span> {{ __('Autónomo Profissional (Persona Física)') }}
                        </p>
                        <p class="text-[11px] text-zinc-400 mt-0.5">
                            {{ __('Aplica retenção de IRPF automaticamente quando o comprador for Empresa ou outro Autônomo espanhol.') }}
                        </p>
                    </div>
                </label>
                <label class="flex items-start gap-3 p-3.5 rounded-xl border border-zinc-800 bg-zinc-950 cursor-pointer hover:border-emerald-500/50 transition">
                    <input type="radio" name="fiscal_regime" value="empresa_sl" @checked(old('fiscal_regime', $fiscal->regime) === 'empresa_sl')
                           class="text-emerald-600 focus:ring-emerald-500 mt-1">
                    <div>
                        <p class="text-xs font-extrabold text-white flex items-center gap-1.5">
                            <span>🏢</span> {{ __('Sociedad Mercantil (S.L. / S.A.)') }}
                        </p>
                        <p class="text-[11px] text-zinc-400 mt-0.5">
                            {{ __('Sem retenção na fonte de IRPF nas faturas emitidas pela sociedade.') }}
                        </p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Taxas de IRPF e IVA -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
            <div class="space-y-1.5">
                <label for="fiscal_irpf_rate" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Alíquota IRPF (%)') }} <span class="text-emerald-500">*</span>
                </label>
                <input type="number" step="0.5" min="0" max="100" name="fiscal_irpf_rate" id="fiscal_irpf_rate"
                       value="{{ old('fiscal_irpf_rate', $fiscal->irpfRate) }}"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                <p class="text-[11px] text-zinc-500">{{ __('Padrão geral na Espanha: 15% (ou 7% nos 3 primeiros anos de atividade).') }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="tax_default_iva_rate" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Alíquota Padrão de IVA (%)') }} <span class="text-emerald-500">*</span>
                </label>
                <input type="number" step="0.5" min="0" max="100" name="tax_default_iva_rate" id="tax_default_iva_rate"
                       value="{{ old('tax_default_iva_rate', $fiscal->ivaRate) }}"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                <p class="text-[11px] text-zinc-500">{{ __('IVA Geral espanhol para serviços digitais/audiovisuais: 21%.') }}</p>
            </div>
        </div>

        <!-- Dados Cadastrais do Emissor -->
        <div class="pt-6 border-t border-zinc-800 space-y-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                {{ __('Dados Cadastrais do Emissor (Fatura Oficial)') }}
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="fiscal_company_name" class="block text-xs font-semibold text-zinc-300">
                        {{ __('Razão Social / Nome Completo') }}
                    </label>
                    <input type="text" name="fiscal_company_name" id="fiscal_company_name"
                           value="{{ old('fiscal_company_name', $fiscal->companyName) }}"
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 transition">
                </div>

                <div class="space-y-1.5">
                    <label for="fiscal_company_cif" class="block text-xs font-semibold text-zinc-300">
                        {{ __('NIF / CIF Fiscal') }}
                    </label>
                    <input type="text" name="fiscal_company_cif" id="fiscal_company_cif"
                           value="{{ old('fiscal_company_cif', $fiscal->companyCif) }}"
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-emerald-500 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2 space-y-1.5">
                    <label for="fiscal_company_address" class="block text-xs font-semibold text-zinc-300">
                        {{ __('Endereço Fiscal / Sede') }}
                    </label>
                    <input type="text" name="fiscal_company_address" id="fiscal_company_address"
                           value="{{ old('fiscal_company_address', $fiscal->companyAddress) }}"
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 transition">
                </div>

                <div class="space-y-1.5">
                    <label for="fiscal_company_postal_code" class="block text-xs font-semibold text-zinc-300">
                        {{ __('Código Postal (CP)') }}
                    </label>
                    <input type="text" name="fiscal_company_postal_code" id="fiscal_company_postal_code"
                           value="{{ old('fiscal_company_postal_code', $fiscal->companyPostalCode) }}"
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-emerald-500 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="fiscal_company_city" class="block text-xs font-semibold text-zinc-300">
                        {{ __('Cidade') }}
                    </label>
                    <input type="text" name="fiscal_company_city" id="fiscal_company_city"
                           value="{{ old('fiscal_company_city', $fiscal->companyCity) }}"
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 transition">
                </div>

                <div class="space-y-1.5">
                    <label for="fiscal_company_email" class="block text-xs font-semibold text-zinc-300">
                        {{ __('E-mail de Faturamento') }}
                    </label>
                    <input type="email" name="fiscal_company_email" id="fiscal_company_email"
                           value="{{ old('fiscal_company_email', $fiscal->companyEmail) }}"
                           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 transition">
                </div>
            </div>

            <div class="space-y-1.5 pt-2">
                <label for="fiscal_invoice_series" class="block text-xs font-semibold text-zinc-300">
                    {{ __('Série de Faturamento (Prefix)') }}
                </label>
                <input type="text" name="fiscal_invoice_series" id="fiscal_invoice_series"
                       value="{{ old('fiscal_invoice_series', $fiscal->invoiceSeries) }}"
                       placeholder="PROD-2026-"
                       class="w-full max-w-xs bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:border-emerald-500 transition">
                <p class="text-[11px] text-zinc-500">{{ __('Cada produtora possui sua própria numeração sequencial atômica por série.') }}</p>
            </div>
        </div>

        <!-- Botão Salvar -->
        <div class="pt-6 border-t border-zinc-800 flex justify-end">
            <button type="submit"
                    class="px-6 py-2.5 rounded-xl text-xs font-extrabold uppercase tracking-wider bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg shadow-emerald-900/20 active:scale-95 transition-all cursor-pointer">
                💾 {{ __('Salvar Dados Fiscais da Produtora') }}
            </button>
        </div>
    </form>
</div>
@endsection
