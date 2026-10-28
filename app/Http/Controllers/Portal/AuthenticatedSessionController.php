<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ClienteLoginRequest;
use App\Models\Pack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        $packIdentifier = $request->query('pack') ?? $request->query('pack_id') ?? $request->query('intended_pack_slug');

        if ($packIdentifier) {
            $pack = Pack::where('slug', $packIdentifier)->orWhere('id', is_numeric($packIdentifier) ? $packIdentifier : 0)->first();
            if ($pack) {
                session(['intended_pack_slug' => $pack->slug]);
            }
        }

        $intendedPack = null;
        if (session('intended_pack_slug')) {
            $intendedPack = Pack::where('slug', session('intended_pack_slug'))->first();
        }

        return view('portal.auth.login', compact('intendedPack'));
    }

    public function store(ClienteLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // DevSecOps: Força sessão única do cliente no portal
        Auth::guard('cliente')->logoutOtherDevices($request->input('password'));

        try {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', Auth::guard('cliente')->id())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        } catch (\Throwable $e) {
            // Fallback silencioso
        }

        if (session('intended_pack_slug')) {
            $packSlug = session()->pull('intended_pack_slug');
            $pack = Pack::where('slug', $packSlug)->first();
            if ($pack && $pack->isActive()) {
                session()->forget('url.intended');
                return redirect()->route('portal.packs.index')
                    ->with('status', "Você está conectado! Selecione o pacote '{$pack->name}' para concluir a contratação.");
            }
        }

        $intended = session('url.intended');
        if ($intended && !str_contains($intended, '/portal')) {
            session()->forget('url.intended');
        }

        return redirect()->intended(route('portal.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('cliente')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
