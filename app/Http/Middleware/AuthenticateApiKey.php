<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->header('X-API-Key');

        if (! $token) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Autenticação obrigatória. Forneça sua chave no cabeçalho Authorization: Bearer <key> ou X-API-Key: <key>.',
            ], 401);
        }

        $apiKey = ApiKey::findToken($token);

        if (! $apiKey || ! $apiKey->isValid()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Chave de API inválida, revogada ou expirada.',
            ], 401);
        }

        $tenant = $apiKey->tenant;
        if (! $tenant || ! $tenant->isActive()) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'A conta de produtora associada a esta chave está inativa ou suspensa.',
            ], 403);
        }

        // Aplica Rate Limiter por chave
        $limiterKey = 'api_key:' . $apiKey->id;
        $maxAttempts = $apiKey->rate_limit_per_minute ?: 60;

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($limiterKey);

            return response()->json([
                'error' => 'Too Many Requests',
                'message' => "Limite de requisições excedido. Tente novamente em {$seconds} segundos.",
            ], 429, [
                'Retry-After' => $seconds,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($limiterKey, 60);

        // Ativa o contexto do Tenant globalmente
        app()->instance('current_tenant', $tenant);
        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('tenant', $tenant);

        // Atualiza carimbo de último uso
        $apiKey->recordUsage();

        return $next($request);
    }
}
