<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GooglePortalController extends Controller
{
    /**
     * Redireciona para o Google OAuth (Login do Portal do Cliente)
     */
    public function redirect(): RedirectResponse
    {
        $tenantSlug = request('tenant_slug') ?: session('portal_tenant_slug', 'nume');
        session(['portal_tenant_slug' => $tenantSlug]);

        return Socialite::driver('google')
            ->redirectUrl(route('portal.auth.google.callback'))
            ->with(['state' => $tenantSlug])
            ->redirect();
    }

    /**
     * Callback após autenticação Google (Portal do Cliente)
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('portal.auth.google.callback'))
                ->user();
        } catch (\Exception $e) {
            return redirect()->route('portal.login')->withErrors(['email' => 'Falha na autenticação com o Google. Tente novamente.']);
        }

        $email = $googleUser->getEmail();
        $cliente = Cliente::withoutGlobalScope('tenant')->where('email', $email)->first();

        // Se o cliente ainda não existir, realiza o auto-cadastro vinculado à produtora indicada
        if (! $cliente) {
            $tenantSlug = request('state') ?: session('portal_tenant_slug', 'nume');
            $tenant = Tenant::where('slug', $tenantSlug)->first() ?: Tenant::first();

            if (! $tenant) {
                return redirect()->route('portal.login')->withErrors([
                    'email' => 'Nenhuma produtora encontrada para vincular este acesso.',
                ]);
            }

            $cliente = Cliente::withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenant->id,
                'name' => $googleUser->getName() ?: 'Cliente ' . explode('@', $email)[0],
                'email' => $email,
                'password' => bcrypt(Str::random(32)),
                'status' => 'active',
            ]);
        }

        Auth::guard('cliente')->login($cliente, true);

        // Se havia um pacote intencionado na sessão antes do login, redireciona para a contratação
        if ($intendedPack = session('intended_pack_slug')) {
            session()->forget('intended_pack_slug');
            session()->forget('url.intended');
            return redirect()->route('portal.packs.index');
        }

        $intended = session('url.intended');
        if ($intended && !str_contains($intended, '/portal')) {
            session()->forget('url.intended');
        }

        return redirect()->intended(route('portal.dashboard'));
    }
}
