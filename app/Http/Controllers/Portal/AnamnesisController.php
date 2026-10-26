<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\Anamnesis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Anamnese do cliente logado (guard cliente): só lê e grava o próprio cadastro.
 */
class AnamnesisController extends Controller
{
    public function edit(Request $request): View
    {
        $cliente = $request->user('cliente');

        return view('portal.anamnesis.edit', [
            'cliente' => $cliente,
            'answers' => $cliente->anamnesis ?? [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $cliente = $request->user('cliente');

        $validated = $request->validate(Anamnesis::rules());

        $cliente->forceFill([
            'anamnesis' => Anamnesis::normalize($validated),
            'anamnesis_completed_at' => now(),
        ])->save();

        return redirect()->route('portal.marketing-plan.index')
            ->with('status', __('Anamnese salva. A Sofi vai usar suas respostas para montar o plano de marketing.'));
    }
}
