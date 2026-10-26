{{-- Direitos RGPD sobre o lead (só admin): acesso/portabilidade e apagamento. --}}
<div class="p-4 rounded-2xl bg-zinc-900 border border-zinc-800 space-y-3 text-xs text-zinc-400">
    <p class="font-bold text-zinc-200">{{ __('Dados pessoais (RGPD)') }}</p>
    <p>{{ __('Leads perdidos são anonimizados automaticamente depois de :months meses.', ['months' => config('crm.anonymize_lost_after_months')]) }}</p>

    @unless($lead->anonymized_at)
        <a href="{{ route('crm.leads.export', $lead) }}" class="inline-flex px-3 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-white font-bold border border-zinc-700 transition">{{ __('Exportar dados (JSON)') }}</a>

        <form action="{{ route('crm.leads.anonymize', $lead) }}" method="POST" class="space-y-2">
            @csrf
            <label class="flex items-start gap-2 text-zinc-300">
                <input type="checkbox" name="confirm" value="1" required class="mt-0.5 rounded border-zinc-700 bg-zinc-950 text-rose-600">
                <span>{{ __('Confirmo que a pessoa pediu o apagamento. A ação não pode ser desfeita.') }}</span>
            </label>
            <button type="submit" class="px-3 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold transition">{{ __('Anonimizar dados') }}</button>
        </form>
    @endunless
</div>
