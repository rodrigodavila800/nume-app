<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends Controller
{
    /**
     * Retorna o provedor Google configurado dinamicamente com as credenciais salvas no banco.
     */
    protected function getGoogleProvider(string $redirectUrl)
    {
        $clientId = trim((string) (Setting::get('google_client_id') ?: config('services.google.client_id')));
        $clientSecret = trim((string) (Setting::get('google_client_secret') ?: config('services.google.client_secret')));

        config([
            'services.google.client_id' => $clientId,
            'services.google.client_secret' => $clientSecret,
            'services.google.redirect' => $redirectUrl,
        ]);

        return Socialite::driver('google')->redirectUrl($redirectUrl);
    }

    /**
     * Redireciona para o Google OAuth (Login da Produtora)
     */
    public function redirect(): RedirectResponse
    {
        $clientId = trim((string) (Setting::get('google_client_id') ?: config('services.google.client_id')));

        if (empty($clientId)) {
            return redirect()->route('login')->withErrors([
                'email' => __('Login com o Google ainda não foi configurado na plataforma. Acesse Configurações > Stripe & Google Login para inserir o Client ID e Secret.'),
            ]);
        }

        return $this->getGoogleProvider(route('auth.google.callback'))->redirect();
    }

    /**
     * Callback após autenticação Google (Login da Produtora)
     * NOTA: Para segurança em multi-tenant, o usuário precisa já existir
     * com o mesmo e-mail vinculado a um tenant.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = $this->getGoogleProvider(route('auth.google.callback'))->user();
        } catch (InvalidStateException $e) {
            try {
                $googleUser = $this->getGoogleProvider(route('auth.google.callback'))->stateless()->user();
            } catch (\Throwable $fallbackError) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Falha na autenticação com o Google: '.($fallbackError->getMessage() ?: 'Sessão expirada. Tente novamente.'),
                ]);
            }
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Falha na autenticação com o Google: '.($e->getMessage() ?: 'Erro de comunicação com o provedor.'),
            ]);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Nenhuma conta encontrada com este e-mail Google. Contate seu administrador.',
            ]);
        }

        Auth::guard('web')->login($user, true);

        request()->session()->regenerate();

        try {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', request()->session()->getId())
                ->delete();
        } catch (\Throwable $e) {
            // Fallback silencioso
        }

        return redirect()->intended(route('productions.index'));
    }
}
