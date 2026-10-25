<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Exibe o formulário de perfil e redes sociais do cliente.
     */
    public function edit(Request $request): View
    {
        $cliente = $request->user('cliente');

        return view('portal.profile.edit', compact('cliente'));
    }

    /**
     * Atualiza os dados de posicionamento, redes e site do cliente.
     */
    public function update(Request $request): RedirectResponse
    {
        $cliente = $request->user('cliente');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'instagram_handle' => ['nullable', 'string', 'max:100'],
            'linkedin_url' => ['nullable', 'string', 'max:255'],
            'youtube_channel' => ['nullable', 'string', 'max:255'],
            'tiktok_handle' => ['nullable', 'string', 'max:100'],
            'facebook_url' => ['nullable', 'string', 'max:255'],
            'x_handle' => ['nullable', 'string', 'max:100'],
            'threads_handle' => ['nullable', 'string', 'max:100'],
            'pinterest_url' => ['nullable', 'string', 'max:255'],
            'brand_niche' => ['required', 'string', 'max:150'],
            'target_audience' => ['nullable', 'string', 'max:1000'],
            'key_differentials' => ['nullable', 'string', 'max:1000'],
        ]);

        $cliente->update($validated);

        return redirect()->route('portal.dashboard')
            ->with('status', __('Perfil da sua marca preenchido com sucesso! Suas produções foram desbloqueadas e a Sofi AI já está calibrada.'));
    }

    /**
     * Escaneia o site corporativo e gera o Dossiê Estratégico de Posicionamento via Sofi IA.
     */
    public function analyzeDna(Request $request, \App\Services\SofiBrandAnalystService $analystService): RedirectResponse
    {
        /** @var \App\Models\Cliente $cliente */
        $cliente = $request->user('cliente');

        $url = $request->input('website') ?: $cliente->website;
        if (! empty($url) && $url !== $cliente->website) {
            $cliente->update(['website' => $url]);
        }

        $study = $analystService->analyze($cliente, $url);
        $cliente->refresh();

        // Se o cliente tem um plano de marketing ativo sem produções, recalcula para aplicar o novo estudo
        $currentMonth = now()->format('Y-m');
        $plan = \App\Models\MarketingPlan::where('cliente_id', $cliente->id)
            ->where('month_year', $currentMonth)
            ->first();

        if ($plan && $plan->items()->whereNotNull('production_id')->count() === 0) {
            app(\App\Services\MarketingPlanGeneratorService::class)->regeneratePlan($plan, $cliente, $plan->items()->count() ?: 4);
        }

        $isEs = \App\Services\MarketingPlanGeneratorService::isSpanishLocale($cliente);
        $msg = $isEs
            ? "¡Estudio de ADN y Posicionamiento generado con éxito por Sofi IA! Tu Calendario Editorial y Guiones han sido calibrados con los diferenciales y servicios de tu web."
            : "Estudo de DNA e Posicionamento gerado com sucesso pela Sofi IA! Sua Grade Editorial e Roteiros foram calibrados com os diferenciais e serviços do seu site.";

        return redirect()->route('portal.profile.edit')->with('status', $msg);
    }
}
