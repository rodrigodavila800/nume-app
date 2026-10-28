<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // DevSecOps: Força sessão única do admin/produtora invalidando sessões em outros navegadores/dispositivos
        Auth::logoutOtherDevices($request->input('password'));

        try {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', Auth::id())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        } catch (\Throwable $e) {
            // Fallback silencioso caso a tabela sessions não exista no driver atual
        }

        return redirect()->intended(route('productions.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
