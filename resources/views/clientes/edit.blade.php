@extends('layouts.app')

@section('title', __('NUME | Editar Cliente'))
@section('breadcrumb', __('Editar Cliente'))

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ __('Editar Cliente') }}</h1>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Atualize informações de contato e vínculo empresarial.') }}</p>
        </div>
        <a href="{{ route('clientes.index') }}" class="text-xs font-bold text-zinc-400 hover:text-white transition flex items-center gap-1.5">
            ← {{ __('Voltar') }}
        </a>
    </div>

    <form action="{{ route('clientes.update', $cliente) }}" method="POST" class="p-6 sm:p-8 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-5">
        @csrf
        @method('PUT')

        <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('Nome do Responsável / Contato') }} <span class="text-orange-500">*</span>
            </label>
            <input type="text" name="name" value="{{ old('name', $cliente->name) }}" required
                   class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('name') border-rose-500 @enderror">
            @error('name') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                {{ __('E-mail de Acesso') }} <span class="text-orange-500">*</span>
            </label>
            <input type="email" name="email" value="{{ old('email', $cliente->email) }}" required
                   class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600 @error('email') border-rose-500 @enderror">
            @error('email') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Empresa / Marca') }}
                </label>
                <input type="text" name="company_name" value="{{ old('company_name', $cliente->company_name) }}"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-300">
                    {{ __('Telefone / WhatsApp') }}
                </label>
                <input type="text" name="phone" value="{{ old('phone', $cliente->phone) }}"
                       class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition placeholder:text-zinc-600">
            </div>
        </div>

        @if(auth()->user()->isAdmin())
            @php $limits = app(\App\Services\SocialPublishing\PublishingLimits::class)->summary($cliente); @endphp
            <div class="space-y-3 pt-4 border-t border-zinc-800">
                <div>
                    <h2 class="text-sm font-bold text-white">{{ __('Publicação nas redes (gestão da NUME)') }}</h2>
                    <p class="text-xs text-zinc-500">
                        {{ __('Hoje: :connected redes conectadas de :networks liberadas · :sends envios no mês de :monthly.', [
                            'connected' => $limits['networks_connected'],
                            'networks' => $limits['networks_limit'] ?? __('ilimitadas'),
                            'sends' => $limits['sends_this_month'],
                            'monthly' => $limits['monthly_limit'] ?? __('ilimitados'),
                        ]) }}
                        {{ __('Vazio = segue o plano, pacote ou orçamento contratado.') }}
                    </p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="social_networks_limit_override" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">{{ __('Redes liberadas (ajuste)') }}</label>
                        <input type="number" id="social_networks_limit_override" name="social_networks_limit_override" min="1" max="8" placeholder="{{ __('Do contratado') }}"
                               value="{{ old('social_networks_limit_override', $cliente->social_networks_limit_override) }}"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500">
                    </div>
                    <div class="space-y-1.5">
                        <label for="monthly_post_limit_override" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">{{ __('Envios por mês (ajuste)') }}</label>
                        <input type="number" id="monthly_post_limit_override" name="monthly_post_limit_override" min="1" max="1000" placeholder="{{ __('Do contratado') }}"
                               value="{{ old('monthly_post_limit_override', $cliente->monthly_post_limit_override) }}"
                               class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500">
                    </div>
                </div>
            </div>
        @endif

        <div class="flex items-center gap-3 pt-4 border-t border-zinc-800">
            <button type="submit"
                    class="flex-1 sm:flex-initial bg-orange-600 hover:bg-orange-500 active:scale-[0.98] text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-lg shadow-orange-900/20 transition-all">
                {{ __('Salvar Alterações') }}
            </button>
            <a href="{{ route('clientes.index') }}"
               class="flex-1 sm:flex-initial inline-flex items-center justify-center bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm px-6 py-2.5 rounded-xl transition">
                {{ __('Cancelar') }}
            </a>
        </div>
    </form>
</div>
@endsection
