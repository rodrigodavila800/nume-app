{{-- Limites de publicação liberados pelo plano/pacote. Espera $limitsModel (Pack ou null). Vazio = sem limite. --}}
<div class="space-y-1.5">
    <label for="social_networks_limit" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">{{ __('Redes liberadas') }}</label>
    <input type="number" name="social_networks_limit" id="social_networks_limit" min="1" max="8" placeholder="{{ __('Sem limite') }}"
           value="{{ old('social_networks_limit', $limitsModel?->social_networks_limit) }}"
           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
    <p class="text-[11px] text-zinc-500">{{ __('Quantas redes o cliente pode conectar para publicar.') }}</p>
    @error('social_networks_limit') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
</div>

<div class="space-y-1.5">
    <label for="monthly_post_limit" class="block text-xs font-bold uppercase tracking-wider text-zinc-300">{{ __('Envios por mês') }}</label>
    <input type="number" name="monthly_post_limit" id="monthly_post_limit" min="1" max="1000" placeholder="{{ __('Sem limite') }}"
           value="{{ old('monthly_post_limit', $limitsModel?->monthly_post_limit) }}"
           class="w-full bg-zinc-950 border border-zinc-800 text-white rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:border-orange-500">
    <p class="text-[11px] text-zinc-500">{{ __('Várias redes no mesmo envio contam 1.') }}</p>
    @error('monthly_post_limit') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
</div>
