<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\ShowcaseVideo;
use App\Models\Video;
use App\Models\Cliente;
use App\Models\Pack;
use App\Policies\ProductionPolicy;
use App\Policies\RoteiroPolicy;
use App\Policies\ShowcaseVideoPolicy;
use App\Policies\VideoPolicy;
use App\Policies\ClientePolicy;
use App\Policies\PackPolicy;
use App\Services\UploadPost\UploadPostClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(UploadPostClient::class, fn () => UploadPostClient::fromConfig());
        $this->app->singleton(\App\Services\UploadPost\SocialDistributionService::class);
        $this->app->singleton(\App\Services\SocialPublishing\SocialPublisherManager::class);
        $this->app->bind(\App\Contracts\SocialPublisherContract::class, \App\Services\SocialPublishing\SocialPublisherManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Carrega configurações e chaves de pagamento criptografadas do banco
        \App\Models\Setting::loadIntoConfig();

        // Suprime warnings informativos emitidos pela SDK do Stripe (ex: recomendação v2 do Stripe Connect)
        set_error_handler(function ($errno, $errstr) {
            if (str_contains($errstr, 'Accounts v2') || str_contains($errstr, 'stripe.com/api/v2')) {
                \Illuminate\Support\Facades\Log::info("Stripe Notice: {$errstr}");
                return true;
            }
            return false;
        }, E_USER_WARNING);

        // Política de Senha Robusta Global (Mínimo 8 caracteres, letras maiúsculas/minúsculas, números e símbolos)
        Password::defaults(function () {
            $rule = Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });

        Gate::policy(Production::class, ProductionPolicy::class);
        Gate::policy(Roteiro::class, RoteiroPolicy::class);
        Gate::policy(ShowcaseVideo::class, ShowcaseVideoPolicy::class);
        Gate::policy(Video::class, VideoPolicy::class);
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Pack::class, PackPolicy::class);
        Gate::policy(\App\Models\Quote::class, \App\Policies\QuotePolicy::class);
        Gate::policy(\App\Models\VideoType::class, \App\Policies\VideoTypePolicy::class);
        Gate::policy(\App\Models\Lead::class, \App\Policies\LeadPolicy::class);

        // Rate limiter para operações de distribuição social
        RateLimiter::for('social', function (Request $request) {
            $identifier = auth('cliente')->id() ?: $request->user()?->id ?: $request->ip();
            return Limit::perMinute(10)->by($identifier);
        });

        // Chat público da Sofi na vitrine: cada mensagem é uma chamada paga ao modelo.
        // O throttle roda antes do SubstituteBindings: {tenant} ainda pode ser o slug (string).
        RateLimiter::for('sofi-chat', function (Request $request) {
            $limits = config('sofi.chat_rate_limits');
            $ip = $request->ip();

            return [
                Limit::perMinute($limits['per_minute'])->by('sofi-chat:ip-m:'.$ip),
                Limit::perHour($limits['per_hour_ip'])->by('sofi-chat:ip-h:'.$ip),
                Limit::perHour($limits['per_hour_tenant'])->by('sofi-chat:tenant:'.static::storefrontTenantKey($request)),
            ];
        });

        // Chat da Sofi no portal: por cliente logado (cada mensagem é uma chamada paga ao modelo).
        RateLimiter::for('sofi-portal-chat', function (Request $request) {
            $limits = config('sofi.portal_chat_rate_limits');
            $key = 'sofi-portal-chat:'.(auth('cliente')->id() ?: 'ip-'.$request->ip());

            return [
                Limit::perMinute($limits['per_minute'])->by($key.':m'),
                Limit::perHour($limits['per_hour'])->by($key.':h'),
            ];
        });

        RateLimiter::for('sofi-chat-reset', function (Request $request) {
            return Limit::perMinute(config('sofi.chat_rate_limits.reset_per_minute'))->by('sofi-chat-reset:'.$request->ip());
        });

        // MVC: Compartilha dados institucionais do tenant nas views do Portal sem queries dentro do Blade
        \Illuminate\Support\Facades\View::composer(['layouts.portal', 'portal.*'], function ($view) {
            $tenant = auth('cliente')->user()?->tenant;
            if (! $tenant && app()->bound('current_tenant')) {
                $tenant = app('current_tenant');
            }
            if (! $tenant) {
                $tenant = \App\Models\Tenant::where('slug', 'nume')->first();
            }
            $view->with('portalTenant', $tenant);
        });
    }

    /**
     * Chave da vitrine para o rate limit: o slug, com o model já resolvido ou ainda como string.
     */
    protected static function storefrontTenantKey(Request $request): string
    {
        $tenant = $request->route('tenant');

        return $tenant instanceof \App\Models\Tenant ? (string) $tenant->slug : (string) $tenant;
    }
}

