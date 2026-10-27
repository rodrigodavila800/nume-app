@extends('layouts.portal')

@section('title', __('Redes Sociais & Distribuição Automática') . ' | NUME')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-8">

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 text-xs font-bold uppercase">
                ✕
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900 text-xs font-bold uppercase">
                ✕
            </button>
        </div>
    @endif

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-orange-100 text-orange-700 border border-orange-200">
                    {{ __('Ecossistema White-label') }}
                </span>
                <span class="text-xs text-zinc-500">
                    {{ __('Distribuição Unificada') }}
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 mt-1">
                {{ __('Conexão de Redes Sociais & Publicação') }}
            </h1>
            <p class="text-xs sm:text-sm text-zinc-600 mt-1 max-w-2xl">
                {{ __('Conecte suas contas corporativas uma única vez de forma 100% segura. Os vídeos aprovados na esteira da NUME são publicados instantaneamente nos seus perfis com o Sofi SEO Pack.') }}
            </p>
            <p class="text-xs font-semibold text-zinc-700 mt-2 flex flex-wrap gap-2">
                <span class="px-2.5 py-1 rounded-lg bg-zinc-100 border border-zinc-200">
                    📲 {{ __('Redes: :connected de :limit', ['connected' => $limits['networks_connected'], 'limit' => $limits['networks_limit'] ?? __('ilimitadas')]) }}
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-zinc-100 border border-zinc-200">
                    🚀 {{ __('Envios no mês: :used de :limit', ['used' => $limits['sends_this_month'], 'limit' => $limits['monthly_limit'] ?? __('ilimitados')]) }}
                </span>
            </p>
        </div>

        @if($usesUploadPost ?? false)
            <!-- Ações do Cabeçalho (apenas quando alguma rede usa hub externo) -->
            <div class="flex items-center gap-2">
                <form action="{{ route('portal.social.sync') }}" method="POST">
                    @csrf
                    <button type="submit" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-white hover:bg-zinc-50 text-zinc-700 border border-zinc-200 shadow-xs transition active:scale-95">
                        <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        {{ __('Sincronizar Status') }}
                    </button>
                </form>

                <form action="{{ route('portal.social.connect-url') }}" method="POST">
                    @csrf
                    <button type="submit" 
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-extrabold text-white bg-gradient-to-r from-orange-600 to-amber-500 hover:from-orange-500 hover:to-amber-400 shadow-sm transition active:scale-95">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        {{ $profile->connected ? __('Gerenciar Conexões Sociais') : __('Conectar Minhas Redes Sociais') }}
                    </button>
                </form>
            </div>
        @endif
    </div>

    <!-- Status Bar Card -->
    <div class="bg-white rounded-2xl p-6 border border-zinc-200/80 shadow-xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center font-bold text-xl border border-orange-100 shadow-xs">
                🌐
            </div>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Status Geral') }}</span>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="w-2.5 h-2.5 rounded-full {{ $connectedPlatforms->isNotEmpty() ? 'bg-emerald-500 animate-pulse' : 'bg-zinc-300' }}"></span>
                    <span class="text-sm font-extrabold text-zinc-900">
                        {{ $connectedPlatforms->isNotEmpty() ? __('Canais Prontos') : __('Desconectado') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 border-t sm:border-t-0 sm:border-l border-zinc-100 sm:pl-6">
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xl border border-blue-100 shadow-xs">
                📊
            </div>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Redes Vinculadas') }}</span>
                <span class="text-sm font-extrabold text-zinc-900 mt-0.5 block">
                    {{ $connectedPlatforms->count() }} {{ __('de') }} {{ count($platforms) }}
                </span>
            </div>
        </div>

        <div class="flex items-center gap-4 border-t lg:border-t-0 lg:border-l border-zinc-100 lg:pl-6">
            <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-xl border border-purple-100 shadow-xs">
                ⚡
            </div>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Cota de Publicações') }}</span>
                @if($cliente->hasDistributionAddon())
                    <span class="text-xs font-extrabold text-emerald-600 mt-0.5 block flex items-center gap-1">
                        <span>✓</span> {{ __('Ilimitado (Add-on Ativo)') }}
                    </span>
                @else
                    <div class="mt-0.5">
                        <span class="text-sm font-extrabold text-zinc-900">
                            {{ $cliente->getRemainingDistributionQuota() }} {{ __('restantes') }}
                        </span>
                        <a href="{{ route('portal.packs.index') }}" class="text-[10px] text-orange-600 font-bold hover:underline block">
                            + {{ __('Contratar Ilimitado') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-4 border-t lg:border-t-0 lg:border-l border-zinc-100 lg:pl-6">
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-xl border border-amber-100 shadow-xs">
                🛡️
            </div>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Privacidade') }}</span>
                <span class="text-xs font-semibold text-zinc-700 mt-0.5 block">
                    {{ __('OAuth JWT Criptografado') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Grid de Plataformas Suportadas -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-zinc-900">{{ __('Plataformas e Redes Integradas') }}</h2>
                <p class="text-xs text-zinc-500">{{ __('Abaixo estão os canais que você pode acionar para publicação automatizada.') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($platforms as $platform)
                @php
                    $isConnected = $connectedPlatforms->contains($platform->value);
                    $native = $connections->get($platform->value);
                    $canConnectNative = $nativeAvailable[$platform->value] ?? false;
                    // Contas sem renovação automática (LinkedIn): avisar antes de vencer.
                    $expiringSoon = $native?->isActive() && ! $native->refresh_token && $native->provider === 'linkedin'
                        && $native->expires_at?->lte(now()->addDays((int) config('social.linkedin.reconnect_warning_days', 7)));
                @endphp
                <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-xs flex flex-col justify-between transition hover:border-zinc-300">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-2xl">{{ $platform->icon() }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider
                                {{ $isConnected ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-zinc-100 text-zinc-500 border border-zinc-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $isConnected ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                {{ $isConnected ? __('Conectado') : __('Não Conectado') }}
                            </span>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-zinc-900">{{ $platform->label() }}</h3>
                            @if($native?->isActive())
                                <p class="text-[11px] text-zinc-600 mt-0.5">
                                    {{ __('Conectado direto pela NUME') }}@if($native->handle || $native->display_name): <span class="font-semibold text-zinc-900">{{ $native->handle ?? $native->display_name }}</span>@endif
                                </p>
                                @if($expiringSoon)
                                    <p class="text-[11px] text-amber-700 mt-1">
                                        {{ __('O LinkedIn pede reconexão a cada 60 dias. Reconecte até :data para não interromper as publicações.', ['data' => $native->expires_at->format('d/m/Y')]) }}
                                    </p>
                                @endif
                            @elseif($native)
                                <p class="text-[11px] text-amber-700 mt-0.5">
                                    {{ __('O acesso à conta expirou ou foi revogado. Reconecte para voltar a publicar direto.') }}
                                </p>
                            @elseif($canConnectNative)
                                <p class="text-[11px] text-zinc-500 mt-0.5">
                                    {{ __('Conecte direto pela NUME: o vídeo sai do nosso servidor para a sua conta, sem intermediários.') }}
                                </p>
                                @if($platform->value === 'instagram')
                                    <p class="text-[11px] text-zinc-500 mt-1">
                                        {{ __('Precisa ser conta profissional (Empresa ou Criador) ligada a uma Página do Facebook. A Página é conectada junto.') }}
                                    </p>
                                @endif
                            @else
                                <p class="text-[11px] text-zinc-500 mt-0.5">
                                    @if($isConnected)
                                        {{ __('Pronto para receber publicações em 1 clique.') }}
                                    @elseif($usesUploadPost ?? false)
                                        {{ __('Clique no botão acima para vincular esta rede.') }}
                                    @else
                                        {{ __('Conexão direta da NUME aguarda configuração das credenciais OAuth desta rede.') }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="pt-4 mt-4 border-t border-zinc-100 flex items-center justify-between gap-2">
                        @if($native)
                            @if((! $native->isActive() || $expiringSoon) && $canConnectNative)
                                <a href="{{ route('portal.social.oauth.redirect', $canConnectNative) }}"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-extrabold text-white bg-orange-600 hover:bg-orange-500 shadow-xs transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                                    {{ __('Reconectar') }}
                                </a>
                            @else
                                <span class="text-xs text-emerald-600 font-extrabold">✓ OK</span>
                            @endif
                            <form action="{{ route('portal.social.connections.destroy', $native) }}" method="POST"
                                onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Desconectar esta conta? Envios agendados para ela serão cancelados.')) }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500">
                                    {{ __('Desconectar') }}
                                </button>
                            </form>
                        @elseif($canConnectNative)
                            <span class="text-[10px] font-mono font-medium text-zinc-400 uppercase">
                                {{ $platform->value }}
                            </span>
                            <a href="{{ route('portal.social.oauth.redirect', $canConnectNative) }}"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-extrabold text-white bg-gradient-to-r from-orange-600 to-amber-500 hover:from-orange-500 hover:to-amber-400 shadow-xs transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                                {{ __('Conectar :rede', ['rede' => $platform->label()]) }}
                            </a>
                        @else
                            <span class="text-[10px] font-mono font-medium text-zinc-400 uppercase">
                                {{ $platform->value }}
                            </span>
                            @if($isConnected)
                                <span class="text-xs text-emerald-600 font-extrabold">✓ OK</span>
                            @else
                                <span class="text-[11px] text-zinc-400 font-semibold">{{ __('OAuth pendente') }}</span>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Dica da Sofi sobre Distribuição de Alta Retenção -->
    <div class="bg-gradient-to-br from-orange-50 via-amber-50 to-zinc-50 rounded-2xl p-6 border border-orange-200/70 shadow-xs flex flex-col sm:flex-row items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-orange-600 text-white flex items-center justify-center font-bold text-lg shadow-sm shrink-0">
            ✨
        </div>
        <div class="space-y-1">
            <h3 class="text-sm font-extrabold text-orange-950 uppercase tracking-wide">
                {{ __('Diretriz da Sofi: Por que postar em até 10 minutos após a entrega?') }}
            </h3>
            <p class="text-xs text-zinc-700 leading-relaxed">
                {{ __('Os algoritmos da Meta (Instagram), TikTok e YouTube premiam a consistência temporal. Quando a equipe NUME finaliza o seu vídeo, ele já está codificado no formato ideal (9:16 ou 16:9) e com legendas magnéticas. Ao usar a publicação direta, você evita a compressão destrutiva do WhatsApp e garante 100% da nitidez em 4K/FullHD.') }}
            </p>
        </div>
    </div>

    <!-- Histórico de Publicações -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-zinc-900">{{ __('Histórico de Distribuições') }}</h2>
                <p class="text-xs text-zinc-500">{{ __('Acompanhe o status e confirmação de entrega de cada post disparado.') }}</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-zinc-200/80 shadow-xs overflow-hidden">
            @if($publications->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-50 border-b border-zinc-200 text-zinc-500 uppercase tracking-wider font-bold">
                            <tr>
                                <th class="px-5 py-3.5">{{ __('Título / Conteúdo') }}</th>
                                <th class="px-5 py-3.5">{{ __('Redes Destino') }}</th>
                                <th class="px-5 py-3.5">{{ __('Status') }}</th>
                                <th class="px-5 py-3.5">{{ __('Disparado em') }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __('Ações') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach($publications as $pub)
                                <tr class="hover:bg-zinc-50/70 transition">
                                    <td class="px-5 py-4">
                                        <div class="font-bold text-zinc-900">{{ $pub->titulo }}</div>
                                        @if($pub->video && $pub->video->production)
                                            <div class="text-[11px] text-zinc-500 mt-0.5 flex items-center gap-1.5">
                                                <span>{{ __('Vídeo NUME:') }}</span>
                                                <a href="{{ route('portal.productions.show', $pub->video->production) }}" class="text-orange-600 hover:underline font-semibold">
                                                    {{ $pub->video->title }}
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($pub->plataformas as $platformSlug)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-zinc-100 text-zinc-700 border border-zinc-200">
                                                    {{ $platformSlug }}
                                                </span>
                                            @endforeach
                                        </div>
                                        @if($pub->driver !== 'upload_post')
                                            <div class="text-[10px] text-zinc-500 mt-1">{{ __('Envio direto pela NUME') }}</div>
                                        @endif
                                        @if($pub->driver !== 'upload_post' && $pub->status === 'publicado' && filled($pub->resultado['url'] ?? null))
                                            <a href="{{ $pub->resultado['url'] }}" target="_blank" rel="noopener noreferrer" class="text-[10px] text-orange-600 font-semibold hover:underline">
                                                {{ __('Ver publicação') }}
                                            </a>
                                        @elseif($pub->status === 'falhou' && $pub->failureHint())
                                            <div class="text-[10px] text-rose-600 mt-1">{{ $pub->failureHint() }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider
                                            @class([
                                                'bg-amber-50 text-amber-700 border border-amber-200 animate-pulse' => in_array($pub->status, ['pendente', 'processando']),
                                                'bg-emerald-50 text-emerald-700 border border-emerald-200' => $pub->status === 'publicado',
                                                'bg-rose-50 text-rose-700 border border-rose-200' => $pub->status === 'falhou',
                                            ])">
                                            <span class="w-1.5 h-1.5 rounded-full mr-1.5
                                                @class([
                                                    'bg-amber-500' => in_array($pub->status, ['pendente', 'processando']),
                                                    'bg-emerald-500' => $pub->status === 'publicado',
                                                    'bg-rose-500' => $pub->status === 'falhou',
                                                ])"></span>
                                            {{ ucfirst($pub->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-500 font-mono text-[11px]">
                                        {{ $pub->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <button type="button" 
                                            onclick="checkPublicationStatus({{ $pub->id }})" 
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold text-zinc-600 hover:text-zinc-900 bg-zinc-100 hover:bg-zinc-200 transition">
                                            <svg class="w-3 h-3 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            <span id="pub-status-label-{{ $pub->id }}">{{ __('Atualizar') }}</span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($publications->hasPages())
                    <div class="p-4 border-t border-zinc-100">
                        {{ $publications->links() }}
                    </div>
                @endif
            @else
                <div class="p-12 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 text-zinc-400 flex items-center justify-center text-xl">
                        🎬
                    </div>
                    <div class="space-y-1">
                        <h4 class="text-sm font-bold text-zinc-800">{{ __('Nenhuma publicação disparada ainda') }}</h4>
                        <p class="text-xs text-zinc-500 max-w-sm mx-auto">
                            {{ __('Conecte suas redes acima e, quando um vídeo da esteira de produção estiver pronto, publique com 1 clique!') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function checkPublicationStatus(pubId) {
    const label = document.getElementById('pub-status-label-' + pubId);
    if (label) label.textContent = 'Verificando...';

    fetch('{{ url("portal/publications") }}/' + pubId + '/status', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (label) label.textContent = 'Atualizado!';
        setTimeout(() => window.location.reload(), 800);
    })
    .catch(() => {
        if (label) label.textContent = 'Erro';
    });
}
</script>
@endsection
