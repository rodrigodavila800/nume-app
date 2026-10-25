<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\SocialPublishing\Connectors\MetaConnector;
use App\Services\SocialPublishing\OAuth\OAuthException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Escolha da Página do Facebook quando o cliente administra mais de uma.
 */
class MetaPageSelectionController extends Controller
{
    public function __construct(private readonly MetaConnector $meta) {}

    public function show(Request $request): View|RedirectResponse
    {
        $pages = $this->meta->pendingPages(auth('cliente')->user(), $request->session());

        if ($pages === null) {
            return $this->expired();
        }

        return view('portal.social.meta-pages', ['pages' => $pages]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['page_id' => ['required', 'string', 'max:100']]);

        try {
            $connections = $this->meta->choosePage(auth('cliente')->user(), $request->session(), $validated['page_id']);
        } catch (OAuthException) {
            return $this->expired();
        }

        return redirect()->route('portal.social.index')->with('success', $this->meta->successMessage($connections));
    }

    private function expired(): RedirectResponse
    {
        return redirect()->route('portal.social.index')
            ->with('error', __('A escolha da Página expirou. Conecte o Facebook novamente.'));
    }
}
