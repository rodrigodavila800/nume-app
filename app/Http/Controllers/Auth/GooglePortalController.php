<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Crm\LeadAutomation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GooglePortalController extends Controller
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
     * Redireciona para o Google OAuth (Login do Portal do Cliente)
     */
    public function redirect(): RedirectResponse
    {
        $clientId = trim((string) (Setting::get('google_client_id') ?: config('services.google.client_id')));

        if (empty($clientId)) {
            return redirect()->route('portal.login')->withErrors([
                'email' => __('Login com o Google ainda não foi configurado na plataforma. Acesse Configurações > Stripe & Google Login para inserir o Client ID e Secret.'),
            ]);
        }

        $tenantSlug = request('tenant_slug') ?: session('portal_tenant_slug', 'nume');
        session(['portal_tenant_slug' => $tenantSlug]);

        return $this->getGoogleProvider(route('portal.auth.google.callback'))
            ->with(['state' => $tenantSlug])
            ->redirect();
    }

    /**
     * Callback após autenticação Google (Portal do Cliente)
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = $this->getGoogleProvider(route('portal.auth.google.callback'))->user();
        } catch (\Exception $e) {
            return redirect()->route('portal.login')->withErrors(['email' => 'Falha na autenticação com o Google: '.$e->getMessage()]);
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
                'name' => $googleUser->getName() ?: 'Cliente '.explode('@', $email)[0],
                'email' => $email,
                'password' => bcrypt(Str::random(32)),
                'status' => 'active',
            ]);

            app(LeadAutomation::class)->clienteRegistered($cliente, 'portal_register');
        }

        Auth::guard('cliente')->login($cliente, true);

        request()->session()->regenerate();

        try {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $cliente->id)
                ->where('id', '!=', request()->session()->getId())
                ->delete();
        } catch (\Throwable $e) {
            // Fallback silencioso se tabela de sessões não estiver acessível
        }

        // Se havia um pacote intencionado na sessão antes do login, redireciona para a contratação
        if ($intendedPack = session('intended_pack_slug')) {
            session()->forget('intended_pack_slug');
            session()->forget('url.intended');

            return redirect()->route('portal.packs.index');
        }

        $intended = session('url.intended');
        if ($intended && ! str_contains($intended, '/portal')) {
            session()->forget('url.intended');
        }

        return redirect()->intended(route('portal.dashboard'));
    }
}
