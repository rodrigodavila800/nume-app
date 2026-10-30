<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\SocialPublishing\Connectors\InstagramConnector;
use App\Services\SocialPublishing\OAuth\OAuthException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Escolha da conta do Instagram quando o cliente libera mais de uma no login unificado.
 */
class InstagramAccountSelectionController extends Controller
{
    public function __construct(private readonly InstagramConnector $instagram) {}

    public function show(Request $request): View|RedirectResponse
    {
        $accounts = $this->instagram->pendingAccounts(auth('cliente')->user(), $request->session());

        if ($accounts === null) {
            return $this->expired();
        }

        return view('portal.social.instagram-accounts', ['accounts' => $accounts]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['account_id' => ['required', 'string', 'max:100']]);

        try {
            $connection = $this->instagram->chooseAccount(auth('cliente')->user(), $request->session(), $validated['account_id']);
        } catch (OAuthException) {
            return $this->expired();
        }

        return redirect()->route('portal.social.index')->with('success', $this->instagram->successMessage([$connection]));
    }

    private function expired(): RedirectResponse
    {
        return redirect()->route('portal.social.index')
            ->with('error', __('A escolha da conta expirou. Conecte o Instagram novamente.'));
    }
}
