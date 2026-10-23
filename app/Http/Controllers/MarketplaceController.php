<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    /**
     * Landing Page Principal da Plataforma NUME Creative.
     * Canal oficial de atração e aquisição de clientes B2B e Pessoas Físicas.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $tenant = Tenant::where('slug', 'nume')->first() 
            ?? Tenant::where('status', 'active')->first() 
            ?? Tenant::first();

        if (! $tenant) {
            $tenant = Tenant::create([
                'name' => 'NUME Vídeos Estratégicos',
                'slug' => 'nume',
                'status' => 'active',
                'description' => 'Produção de vídeos de alta conversão e retenção.',
                'subscription_expires_at' => now()->addYears(10),
            ]);
        }

        return app(StorefrontController::class)->show($tenant, $request);
    }
}
