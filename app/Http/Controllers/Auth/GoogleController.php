<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Redireciona para o Google OAuth (Login da Produtora)
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl(route('auth.google.callback'))
            ->redirect();
    }

    /**
     * Callback após autenticação Google (Login da Produtora)
     * NOTA: Para segurança em multi-tenant, o usuário precisa já existir
     * com o mesmo e-mail vinculado a um tenant.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('auth.google.callback'))
                ->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['email' => 'Falha na autenticação com o Google. Tente novamente.']);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Nenhuma conta encontrada com este e-mail Google. Contate seu administrador.',
            ]);
        }

        Auth::guard('web')->login($user, true);

        return redirect()->intended(route('productions.index'));
    }
}
