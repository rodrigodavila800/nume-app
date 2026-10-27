@extends('layouts.app')

@section('title', __('NUME | Pacotes de Vídeos'))
@section('breadcrumb', __('Pacotes'))

@section('content')
<div class="space-y-6">

    <!-- Header & Ações -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Pacotes Estratégicos & Catálogo de Vendas') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Ofertas de produção prontas para venda na vitrine pública com seleção interativa de Add-ons.') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ url('/') }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-300 hover:text-white bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 px-3.5 py-2.5 rounded-xl transition">
                <span>↗</span>
                <span>{{ __('Ver Vitrine Pública') }}</span>
            </a>
            @can('create', App\Models\Pack::class)
                <a href="{{ route('packs.create') }}"
                   class="inline-flex items-center gap-2 font-bold rounded-xl text-xs sm:text-sm px-4 py-2.5 bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white shadow-lg shadow-orange-900/20 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>{{ __('Novo Pacote') }}</span>
                </a>
            @endcan
        </div>
    </div>

    <!-- Navegação de Abas do Catálogo & Cadastros -->
    <x-catalog-tabs active="packs" />

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold flex items-center gap-2">
            <span>✓</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Cards de Pacotes -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($packs as $pack)
            @php
                $currencySymbol = ($pack->currency ?? 'eur') === 'eur' ? '€' : (($pack->currency ?? 'eur') === 'usd' ? '$' : 'R$');
                $addonsList = $pack->effective_addons;
                $featuresList = $pack->features ?? [];
            @endphp
            <div class="rounded-2xl bg-zinc-900 border border-zinc-800 hover:border-zinc-700 p-6 flex flex-col justify-between space-y-4 transition-all hover:shadow-xl hover:shadow-black/40 group">
                <div class="space-y-3.5">
                    <!-- Top Bar: Categoria & Status -->
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-orange-950/40 text-orange-400 border border-orange-800/40">
                            {{ $pack->category_name }}
                        </span>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-lg border {{ $pack->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-zinc-800 text-zinc-400 border-zinc-700' }}">
                            {{ $pack->status === 'active' ? __('Ativo na Vitrine') : __('Inativo') }}
                        </span>
                    </div>

                    <!-- Título & Descrição -->
                    <div>
                        <h2 class="text-base font-bold text-white group-hover:text-orange-400 transition">{{ $pack->name }}</h2>
                        <p class="text-xs text-zinc-400 line-clamp-2 leading-relaxed mt-1">
                            {{ $pack->description ?: __('Pacote audiovisual estratégico com produção e finalização.') }}
                        </p>
                    </div>

                    <!-- Preço & Formato -->
                    <div class="p-3.5 rounded-xl bg-zinc-950/60 border border-zinc-800/80 flex items-baseline justify-between">
                        <div>
                            <span class="text-xs text-zinc-500 font-medium mr-1">{{ __('A partir de') }}</span>
                            <span class="text-2xl font-black text-white tracking-tight">{{ number_format($pack->price, 0, ',', '.') }} {{ $currencySymbol }}</span>
                            @if($pack->vat_rate > 0)
                                <span class="text-[11px] font-semibold text-zinc-400 ml-1">+ {{ number_format($pack->vat_rate, 0) }}% IVA</span>
                            @endif
                        </div>
                        <span class="text-xs font-bold text-orange-400 bg-orange-500/10 px-2 py-1 rounded-md border border-orange-500/20">
                            {{ $pack->video_quantity }} {{ $pack->video_quantity == 1 ? __('vídeo') : __('vídeos') }}
                        </span>
                    </div>

                    <!-- Entregáveis (Features) -->
                    @if(!empty($featuresList))
                        <div class="space-y-1.5">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Entregáveis Inclusos:') }}</span>
                            <ul class="space-y-1">
                                @foreach(array_slice($featuresList, 0, 3) as $feat)
                                    <li class="text-[11px] text-zinc-300 flex items-start gap-1.5 leading-snug">
                                        <span class="text-orange-500 text-[10px] mt-0.5">✓</span>
                                        <span>{{ $feat }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- SEÇÃO: ADD-ONS (AÑADE A TU PACK) -->
                    <div class="pt-3 border-t border-zinc-800/80">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-orange-400 flex items-center gap-1">
                                <span class="font-bold">+</span> {{ __('Add-ons da Proposta:') }}
                            </span>
                            <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-orange-950/50 text-orange-300 border border-orange-800/30">
                                {{ count($addonsList) }} {{ __('opcionais') }}
                            </span>
                        </div>

                        @if(!empty($addonsList))
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($addonsList as $addon)
                                    @php
                                        $aName = is_array($addon) ? ($addon['name'] ?? '') : (string) $addon;
                                        $aPrice = is_array($addon) ? ($addon['price'] ?? 0) : 0;
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-zinc-950 text-zinc-300 border border-zinc-800 hover:border-orange-500/40 transition">
                                        <span class="text-orange-400 font-bold">+</span>
                                        <span>{{ $aName }}</span>
                                        @if($aPrice > 0)
                                            <span class="font-mono text-[10px] font-bold text-orange-400 bg-orange-950/40 px-1 rounded ml-0.5">+{{ number_format($aPrice, 0) }}{{ $currencySymbol }}</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-[11px] text-zinc-500 italic">{{ __('Nenhum add-on opcional configurado.') }}</p>
                        @endif
                    </div>
                </div>

                <!-- Footer do Card -->
                <div class="flex items-center gap-2 pt-3 border-t border-zinc-800">
                    @can('update', $pack)
                        <a href="{{ route('packs.edit', $pack) }}"
                           class="flex-1 text-center py-2 px-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white font-bold text-xs transition flex items-center justify-center gap-1">
                            <span>{{ __('Editar & Add-ons') }}</span>
                        </a>
                    @endcan
                    @can('delete', $pack)
                        <form action="{{ route('packs.destroy', $pack) }}" method="POST" class="inline" onsubmit="return confirm('Remover este pacote?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-2 rounded-xl bg-zinc-800 hover:bg-rose-950 hover:text-rose-400 text-zinc-400 transition" title="Remover">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            <div class="col-span-3 p-12 rounded-2xl bg-zinc-900 border border-zinc-800 text-center space-y-3">
                <p class="text-zinc-400 text-xs">{{ __('Nenhum pacote cadastrado ainda.') }}</p>
                <a href="{{ route('packs.create') }}" class="inline-block text-xs font-bold text-orange-500 hover:text-orange-400">
                    + {{ __('Criar primeiro pacote') }}
                </a>
            </div>
        @endforelse
    </div>

    <div class="pt-2">
        {{ $packs->links() }}
    </div>
</div>
@endsection
