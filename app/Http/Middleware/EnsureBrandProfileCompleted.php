<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBrandProfileCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cliente = $request->user('cliente');

        if ($cliente && ! $cliente->hasCompletedBrandProfile()) {
            return redirect()->route('portal.profile.edit')
                ->with('warning', __('Para abrir sua produção e revisar os roteiros, complete o perfil da sua marca primeiro.'));
        }

        return $next($request);
    }
}
