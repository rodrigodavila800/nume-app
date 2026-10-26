<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\BillingProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dados de faturação do cliente logado (guard cliente). Gravados criptografados no cadastro.
 */
class BillingProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('portal.billing.edit', ['values' => BillingProfile::formValues($request->user('cliente'))]);
    }

    public function update(Request $request): RedirectResponse
    {
        BillingProfile::save($request->user('cliente'), $request->validate(BillingProfile::rules()));

        return redirect()->route('portal.billing.edit')
            ->with('status', __('Dados de faturação salvos. Eles vão nas suas próximas faturas.'));
    }
}
