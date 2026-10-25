<?php

use App\Http\Controllers\Auth\GooglePortalController;
use App\Http\Controllers\Portal\AuthenticatedSessionController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\MarketingPlanController;
use App\Http\Controllers\Portal\MetaPageSelectionController;
use App\Http\Controllers\Portal\PackController;
use App\Http\Controllers\Portal\PinterestComposerController;
use App\Http\Controllers\Portal\ProductionController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\Portal\QuoteController;
use App\Http\Controllers\Portal\RegisteredClientController;
use App\Http\Controllers\Portal\RoteiroController;
use App\Http\Controllers\Portal\SocialConnectionController;
use App\Http\Controllers\Portal\SocialDistributionController;
use App\Http\Controllers\Portal\TikTokComposerController;
use App\Http\Controllers\Portal\VideoApprovalController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:cliente')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store']);

        Route::get('register', [RegisteredClientController::class, 'create'])->name('register');
        Route::post('register', [RegisteredClientController::class, 'store']);

        // Google OAuth para Portal do Cliente
        Route::get('auth/google', [GooglePortalController::class, 'redirect'])->name('auth.google');
        Route::get('auth/google/callback', [GooglePortalController::class, 'callback'])->name('auth.google.callback');
    });

    Route::middleware('auth:cliente')->group(function () {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Perfil e Redes da Marca do Cliente
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/analyze-dna', [ProfileController::class, 'analyzeDna'])->name('profile.analyze-dna');

        // Rotas que exigem o perfil da marca preenchido
        Route::middleware('brand.profile.completed')->group(function () {
            Route::get('productions/{production}', [ProductionController::class, 'show'])->name('productions.show');

            Route::get('roteiros/{roteiro}', [RoteiroController::class, 'show'])->name('roteiros.show');
            Route::post('roteiros/{roteiro}/approve', [RoteiroController::class, 'approve'])->name('roteiros.approve');
            Route::post('roteiros/{roteiro}/reject', [RoteiroController::class, 'reject'])->name('roteiros.reject');

            // Grade Editorial & Plano de Marketing Sofi IA
            Route::get('marketing-plan', [MarketingPlanController::class, 'index'])->name('marketing-plan.index');
            Route::post('marketing-plan/regenerate', [MarketingPlanController::class, 'regenerate'])->name('marketing-plan.regenerate');
            Route::post('marketing-plan/items/{item}/convert', [MarketingPlanController::class, 'convertToProduction'])->name('marketing-plan.convert-item');
        });

        // Propostas & Orçamentos vinculados ao cliente
        Route::get('orcamentos', [QuoteController::class, 'index'])->name('quotes.index');

        // Planos Recorrentes (Retainers / Assinaturas)
        Route::get('planos', [PackController::class, 'plans'])->name('plans.index');

        // Pacotes & Produções Avulsas (One-off)
        Route::get('packs', [PackController::class, 'index'])->name('packs.index');
        Route::get('pacotes', [PackController::class, 'index'])->name('packs.pacotes');

        Route::post('packs/{pack}/checkout', [PackController::class, 'checkout'])
            ->middleware('throttle:10,1')
            ->name('packs.checkout');
        Route::get('packs/checkout/success', [PackController::class, 'success'])->name('packs.success');
        Route::get('packs/checkout/cancel', [PackController::class, 'cancel'])->name('packs.cancel');
        Route::get('packs/checkout/transferencia/{purchase}', [PackController::class, 'bankTransfer'])->name('packs.bank_transfer');
        Route::get('purchases/{purchase}/factura', [PackController::class, 'downloadInvoice'])->name('purchases.invoice');

        // Distribuição Multiplataforma & Conexão de Redes Sociais
        Route::prefix('redes-sociais')->name('social.')->group(function () {
            Route::get('/', [SocialDistributionController::class, 'index'])->name('index');
            Route::post('connect-url', [SocialDistributionController::class, 'connectUrl'])
                ->middleware('throttle:social')
                ->name('connect-url');
            Route::get('conectado', [SocialDistributionController::class, 'connectedCallback'])->name('connected');
            Route::post('sync', [SocialDistributionController::class, 'sync'])
                ->middleware('throttle:social')
                ->name('sync');

            // App próprio da NUME (OAuth oficial da rede)
            Route::get('conectar/{platform}', [SocialConnectionController::class, 'redirect'])
                ->whereIn('platform', ['youtube', 'meta', 'threads', 'tiktok', 'linkedin', 'pinterest'])
                ->middleware('throttle:social')
                ->name('oauth.redirect');
            Route::get('oauth/{platform}/callback', [SocialConnectionController::class, 'callback'])
                ->whereIn('platform', ['youtube', 'meta', 'threads', 'tiktok', 'linkedin', 'pinterest'])
                ->middleware('throttle:social')
                ->name('oauth.callback');
            Route::get('meta/paginas', [MetaPageSelectionController::class, 'show'])->name('meta.pages');
            Route::post('meta/paginas', [MetaPageSelectionController::class, 'store'])
                ->middleware('throttle:social')
                ->name('meta.pages.store');
            Route::delete('conexoes/{socialConnection}', [SocialConnectionController::class, 'destroy'])
                ->whereNumber('socialConnection')
                ->middleware('throttle:social')
                ->name('connections.destroy');
        });

        // Disparo e consulta de status de publicação de vídeo
        // TikTok: tela própria exigida pelas regras do Direct Post
        Route::get('videos/{video}/tiktok', [TikTokComposerController::class, 'show'])
            ->whereNumber('video')
            ->middleware('throttle:social')
            ->name('videos.tiktok');
        Route::post('videos/{video}/tiktok', [TikTokComposerController::class, 'store'])
            ->whereNumber('video')
            ->middleware('throttle:social')
            ->name('videos.tiktok.store');

        // Pinterest: tela própria para escolher a pasta do pin
        Route::get('videos/{video}/pinterest', [PinterestComposerController::class, 'show'])
            ->whereNumber('video')
            ->middleware('throttle:social')
            ->name('videos.pinterest');
        Route::post('videos/{video}/pinterest', [PinterestComposerController::class, 'store'])
            ->whereNumber('video')
            ->middleware('throttle:social')
            ->name('videos.pinterest.store');

        Route::post('videos/{video}/distribute', [SocialDistributionController::class, 'distribute'])
            ->middleware('throttle:social')
            ->name('videos.distribute');
        Route::get('publications/{videoPublication}/status', [SocialDistributionController::class, 'status'])
            ->name('publications.status');

        // Aprovação do corte final com liberação de ativos e liquidação do saldo (50/50)
        Route::post('videos/{video}/approve-and-release', [VideoApprovalController::class, 'approveAndRelease'])
            ->name('videos.approve-and-release');
    });
});
