<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureBrandProfileCompleted;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            EnsureTenantIsActive::class,
            SetLocale::class,
            SecurityHeaders::class,
        ]);
        $middleware->alias([
            'brand.profile.completed' => EnsureBrandProfileCompleted::class,
            'auth.api_key' => AuthenticateApiKey::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'webhooks/redsys',
            'webhooks/upload-post',
            'api/*',
        ]);
        $middleware->redirectUsersTo(function () {
            if (Auth::guard('cliente')->check()) {
                return route('portal.dashboard');
            }

            return route('productions.index');
        });
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('portal/*') || $request->is('portal')) {
                return route('portal.login');
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419 || $e->getPrevious() instanceof TokenMismatchException) {
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

        // Limites do plano (redes liberadas / envios do mês): mensagem pronta para a tela ou API.
        $exceptions->render(function (\App\Services\SocialPublishing\PublishingLimitException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Limit Reached', 'reason' => $e->reason, 'message' => $e->getMessage()], 403);
            }

            return redirect()->back(fallback: $request->is('portal/*') ? route('portal.social.index') : route('dashboard'))
                ->with('error', $e->getMessage());
        });
    })->create();
