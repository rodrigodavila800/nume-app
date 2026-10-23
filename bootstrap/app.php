<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            \App\Http\Middleware\EnsureTenantIsActive::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        $middleware->alias([
            'brand.profile.completed' => \App\Http\Middleware\EnsureBrandProfileCompleted::class,
            'auth.api_key' => \App\Http\Middleware\AuthenticateApiKey::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'webhooks/redsys',
            'webhooks/upload-post',
            'api/*',
        ]);
        $middleware->redirectUsersTo(function () {
            if (\Illuminate\Support\Facades\Auth::guard('cliente')->check()) {
                return route('portal.dashboard');
            }
            return route('productions.index');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419 || $e->getPrevious() instanceof \Illuminate\Session\TokenMismatchException) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'message' => __('Sua sessão expirou por inatividade. Por favor, atualize a página e tente novamente.'),
                        'csrf_token' => csrf_token(),
                    ], 419);
                }

                $fallbackUrl = ($request->is('portal/*') || $request->is('portal')) 
                    ? route('portal.login') 
                    : route('login');

                return redirect()
                    ->back(fallback: $fallbackUrl)
                    ->withInput($request->except(['_token', 'password', 'password_confirmation']))
                    ->with('warning', __('Sua sessão expirou por inatividade. O formulário foi atualizado, por favor tente novamente.'));
            }
        });
    })->create();

