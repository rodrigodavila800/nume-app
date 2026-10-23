@extends('layouts.app')

@section('title', __('Chaves de API & Desenvolvedores') . ' | NUME')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Flash Messages -->
    @if(session('success') && ! session('plainTextToken'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900">✕</button>
        </div>
    @endif

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-orange-100 text-orange-700 border border-orange-200">
                    {{ __('B2B Developer API') }}
                </span>
                <span class="text-zinc-400 text-xs">•</span>
                <span class="text-xs text-zinc-500 font-medium">{{ __('White-label Engine') }}</span>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-zinc-900 mt-1">
                {{ __('Chaves de API & Desenvolvedores') }}
            </h1>
            <p class="text-xs text-zinc-500 mt-1">
                {{ __('Emita tokens B2B para integrar seu ERP, Make.com, n8n ou Zapier diretamente com a engine de postagens da NUME.') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('api.v1.docs') }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-xl text-xs font-bold transition shadow-xs">
                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
                <span>{{ __('Ver Documentação da API') }}</span>
            </a>
        </div>
    </div>

    <!-- Mensagem de Sucesso com o Token Cru (Exibido apenas uma vez) -->
    @if (session('plainTextToken'))
        <div class="p-6 bg-emerald-950/40 border border-emerald-500/30 rounded-2xl shadow-xl space-y-3">
            <div class="flex items-center space-x-2 text-emerald-400 font-bold">
                <svg class="w-5 h-5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>{{ __('Nova Chave de API Criada: ') }} <strong class="text-white">{{ session('keyName') }}</strong></span>
            </div>
            <p class="text-xs text-emerald-300">
                {{ __('Copie seu token agora. Por motivos de segurança de nível bancário, nós armazenamos apenas o hash SHA-256 e este token NÃO poderá ser exibido novamente.') }}
            </p>
            <div class="flex items-center space-x-2">
                <input type="text" readonly value="{{ session('plainTextToken') }}" id="newTokenInput" class="w-full font-mono text-sm px-4 py-2.5 bg-zinc-950 border border-emerald-500/40 rounded-xl text-emerald-300 select-all font-semibold focus:outline-none" />
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('newTokenInput').value); alert('Chave de API copiada para a área de transferência!');" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-xl shadow transition shrink-0 cursor-pointer">
                    {{ __('Copiar') }}
                </button>
            </div>
        </div>
    @endif

    <!-- Formulário de Criação de Nova Chave -->
    <div class="bg-zinc-900 p-6 rounded-2xl shadow-xl border border-zinc-800">
        <h3 class="text-sm font-bold text-white mb-4">{{ __('Emitir Nova Chave de API') }}</h3>
        <form action="{{ route('api_keys.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            @csrf
            <div class="md:col-span-6">
                <label for="name" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">{{ __('Nome / Finalidade da Chave') }}</label>
                <input type="text" name="name" id="name" required placeholder="Ex: Automação Make.com / ERP Nume" class="w-full text-sm bg-zinc-950 border border-zinc-800 text-white placeholder-zinc-500 rounded-xl px-3.5 py-2.5 focus:border-orange-500 focus:bg-zinc-900 focus:outline-none" />
            </div>
            <div class="md:col-span-3">
                <label for="rate_limit_per_minute" class="block text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">{{ __('Limite (Req / Min)') }}</label>
                <input type="number" name="rate_limit_per_minute" id="rate_limit_per_minute" value="60" min="10" max="600" class="w-full text-sm bg-zinc-950 border border-zinc-800 text-white rounded-xl px-3.5 py-2.5 focus:border-orange-500 focus:bg-zinc-900 focus:outline-none" />
            </div>
            <div class="md:col-span-3">
                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-orange-600 border border-transparent rounded-xl font-bold text-xs text-white uppercase tracking-widest hover:bg-orange-500 transition shadow-md shadow-orange-900/20 cursor-pointer">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    {{ __('Gerar Chave') }}
                </button>
            </div>
        </form>
    </div>

    <!-- Templates de Integração No-Code para Download Rápido -->
    <div class="p-6 bg-zinc-950 rounded-2xl border border-zinc-800 text-white flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="text-[10px] px-2 py-0.5 rounded font-extrabold bg-orange-500/20 text-orange-400 border border-orange-500/30 uppercase tracking-wider">No-Code Hub</span>
                <h4 class="text-sm font-bold text-white">{{ __('Templates Oficiais de Automação') }}</h4>
            </div>
            <p class="text-xs text-zinc-400">
                {{ __('Conecte a NUME aos seus fluxos em 2 cliques importando nossos blueprints prontos para Make.com ou n8n.') }}
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('api.v1.integrations.make') }}" class="inline-flex items-center gap-1.5 px-3 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-bold transition">
                <span>🟣</span>
                <span>{{ __('Blueprint Make.com') }}</span>
            </a>
            <a href="{{ route('api.v1.integrations.n8n') }}" class="inline-flex items-center gap-1.5 px-3 py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold transition">
                <span>🟠</span>
                <span>{{ __('Workflow n8n') }}</span>
            </a>
        </div>
    </div>

    <!-- Tabela de Chaves Existentes -->
    <div class="bg-zinc-900 rounded-2xl shadow-xl border border-zinc-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-zinc-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-white">{{ __('Chaves de API Emitidas') }}</h3>
            <span class="text-xs text-zinc-400 font-medium">{{ $apiKeys->count() }} {{ __('registros') }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-800/60 text-left text-sm">
                <thead class="bg-zinc-950/60 text-[10px] uppercase tracking-wider text-zinc-400 font-bold border-b border-zinc-800">
                    <tr>
                        <th class="px-6 py-3.5">{{ __('Nome') }}</th>
                        <th class="px-6 py-3.5">{{ __('Prefixo') }}</th>
                        <th class="px-6 py-3.5">{{ __('Limite') }}</th>
                        <th class="px-6 py-3.5">{{ __('Último Uso') }}</th>
                        <th class="px-6 py-3.5">{{ __('Status') }}</th>
                        <th class="px-6 py-3.5 text-right">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60">
                    @forelse($apiKeys as $key)
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="px-6 py-4 font-semibold text-white">
                                {{ $key->name }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-zinc-400">
                                {{ $key->prefix }}••••••••
                            </td>
                            <td class="px-6 py-4 text-xs text-zinc-300">
                                {{ $key->rate_limit_per_minute }} req/min
                            </td>
                            <td class="px-6 py-4 text-xs text-zinc-400">
                                {{ $key->last_used_at ? $key->last_used_at->diffForHumans() : __('Nunca utilizada') }}
                            </td>
                            <td class="px-6 py-4">
                                @if($key->isValid())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        {{ __('Ativa') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                        {{ __('Revogada') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($key->isValid())
                                    <form action="{{ route('api_keys.destroy', $key) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja revogar esta chave? Qualquer integração utilizando-a deixará de funcionar imediatamente.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-bold text-rose-400 hover:text-rose-300 transition cursor-pointer">
                                            {{ __('Revogar') }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-zinc-500 italic">{{ __('Desativada') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-xs text-zinc-400">
                                {{ __('Nenhuma chave de API emitida até o momento. Gere sua primeira chave acima para integrar via API.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Card: Conector Oficial para ChatGPT & Claude (Sofi Ops) -->
    <div class="bg-zinc-900 border border-purple-500/30 rounded-2xl p-6 shadow-xl space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-purple-500/20 border border-purple-500/40 flex items-center justify-center text-lg">🤖</span>
                <div>
                    <h3 class="text-sm font-extrabold text-white flex items-center gap-2">
                        <span>{{ __('Conector ChatGPT Custom GPT & Claude (Sofi Ops)') }}</span>
                        <span class="text-[9px] uppercase px-2 py-0.5 rounded font-black bg-purple-500/20 text-purple-300 border border-purple-500/30">Moat B2B</span>
                    </h3>
                    <p class="text-xs text-zinc-400 mt-0.5">
                        {{ __('Conecte seu assistente pessoal de IA para consultar gravações, roteiros pendentes e gerar links do WhatsApp pelo celular.') }}
                    </p>
                </div>
            </div>
            <a href="{{ route('api.assistant.openapi') }}" target="_blank"
               class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-extrabold text-xs shadow-md shadow-purple-600/30 transition text-center shrink-0">
                {{ __('Ver Schema OpenAPI 3.1') }} →
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-zinc-800 text-xs text-zinc-300">
            <div class="space-y-2 bg-zinc-950/60 p-4 rounded-xl border border-zinc-800">
                <p class="font-bold text-white flex items-center gap-1.5">
                    <span>1.</span>
                    <span>{{ __('URL da Action para importar no ChatGPT:') }}</span>
                </p>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ route('api.assistant.openapi') }}"
                           class="w-full bg-zinc-900 border border-zinc-700/80 rounded-lg px-2.5 py-1.5 text-[11px] font-mono text-purple-300 select-all">
                </div>
                <p class="text-[10px] text-zinc-500">
                    {{ __('No ChatGPT Plus: Vá em "Explore GPTs" > "Create" > "Configure" > "Actions" > "Import from URL".') }}
                </p>
            </div>

            <div class="space-y-2 bg-zinc-950/60 p-4 rounded-xl border border-zinc-800">
                <p class="font-bold text-white flex items-center gap-1.5">
                    <span>2.</span>
                    <span>{{ __('Autenticação da Action (Header):') }}</span>
                </p>
                <p class="text-[11px] text-zinc-400">
                    {{ __('Selecione "API Key" > Tipo "Custom" > Nome do Header:') }}
                    <span class="font-mono text-orange-400 font-bold">X-API-Key</span>.
                </p>
                <p class="text-[10px] text-zinc-500">
                    {{ __('Cole a chave gerada acima (nume_live_...). O seu assistente estará instantaneamente sincronizado com a produtora!') }}
                </p>
            </div>
        </div>
    </div>

</div>
@endsection
