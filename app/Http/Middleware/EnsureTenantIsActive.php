<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('web')->check()) {
            $tenant = auth('web')->user()->tenant;

            if (!$tenant || !$tenant->isActive()) {
                auth('web')->logout();
                return redirect('/')->with('error', 'Seu tenant foi desativado. Contate o suporte.');
            }

            if (!$tenant->isSubscriptionValid() && !$request->is('billing*') && !$request->is('api/*') && !$request->expectsJson()) {
                return redirect('/billing')->with('warning', 'Sua subscrição expirou.');
            }
        }

        if (auth('cliente')->check()) {
            $tenant = auth('cliente')->user()->tenant;

            if (!$tenant || !$tenant->isActive()) {
                auth('cliente')->logout();
                return redirect('/portal/login')->with('error', 'Esta produtora está temporariamente indisponível.');
            }
        }

        return $next($request);
    }
}
