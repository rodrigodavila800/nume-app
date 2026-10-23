<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and attach high-grade security headers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Previne Clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Previne MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Proteção XSS legada para navegadores mais antigos
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Política de Referrer estrita
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrição de APIs do navegador (câmera, microfone, geolocalização)
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(self "https://js.stripe.com")'
        );

        // Forçar HSTS quando em conexão segura
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Remove identificadores do servidor
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
