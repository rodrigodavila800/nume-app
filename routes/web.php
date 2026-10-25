<?php

use App\Http\Controllers\AddonController;
use App\Http\Controllers\Admin\BankTransferConfirmationController;
use App\Http\Controllers\Admin\GatewaySettingController;
use App\Http\Controllers\Admin\InvoiceManagementController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SubscriptionManagementController;
use App\Http\Controllers\Admin\TenantBankTransferSettingsController;
use App\Http\Controllers\Admin\TenantFiscalSettingsController;
use App\Http\Controllers\Admin\TenantRedsysSettingsController;
use App\Http\Controllers\Admin\TenantSettingsController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\SofiChatController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\BatchContentPlannerController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\MagicApprovalController;
use App\Http\Controllers\MagicVideoController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\PackController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\QuoteApprovalController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RoteiroController;
use App\Http\Controllers\ShowcaseVideoController;
use App\Http\Controllers\SocialDistributionManagementController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StripeConnectController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\Webhooks\RedsysWebhookController;
use App\Http\Controllers\Webhooks\UploadPostWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['pt_BR', 'es'])) {
        session()->put('locale', $locale);
    }

    return back();
})->name('lang.switch');

Route::get('/locale/{locale}', function ($locale) {
    if (in_array($locale, ['pt_BR', 'es'])) {
        session()->put('locale', $locale);
    }

    return back();
})->name('locale.change');

Route::get('/privacidade', function () {
    return view('legal.privacy');
})->name('legal.privacy');

Route::get('/', [MarketplaceController::class, 'index'])->name('marketplace.index');

// Vitrine pÃºblica de cada produtora: portfÃ³lio, pacotes e checkout para visitantes.
Route::get('/vitrine/{tenant:slug}', [StorefrontController::class, 'show'])->name('storefront.show');
Route::post('/vitrine/{tenant:slug}/sofi-chat', [StorefrontController::class, 'sofiChat'])
    ->middleware('throttle:sofi-chat')
    ->name('storefront.sofi-chat');
Route::post('/vitrine/{tenant:slug}/sofi-chat/reset', [StorefrontController::class, 'resetSofiChat'])
    ->middleware('throttle:sofi-chat-reset')
    ->name('storefront.sofi-chat.reset');
Route::post('/vitrine/{tenant:slug}/pacotes/{pack}/checkout', [StorefrontController::class, 'checkout'])
    ->middleware('throttle:10,1')
    ->name('storefront.checkout');
Route::get('/vitrine/{tenant:slug}/compra-confirmada', [StorefrontController::class, 'success'])
    ->name('storefront.success');
Route::get('/vitrine/{tenant:slug}/transferencia-bancaria/{purchase}', [StorefrontController::class, 'bankTransfer'])
    ->name('storefront.bank_transfer');

// Rotas autenticadas - apenas para usuários logados
Route::middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return redirect()->route('productions.index');
    })->name('dashboard');

    // Analytics & BI (Sofi Intelligence Feed)
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/sofi-data-feed', [AnalyticsController::class, 'sofiDataFeed'])->name('analytics.sofi-feed');

    // Billing / Stripe
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::get('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');

    // Clientes da produtora (compradores de pacotes)
    Route::resource('clientes', ClienteController::class)->except(['show']);

    // Taxonomia e Categorias
    Route::resource('categories', CategoryController::class)->except(['show']);

    // Catálogo de Serviços Opcionais e Add-ons
    Route::post('addons/{addon}/toggle', [AddonController::class, 'toggleActive'])->name('addons.toggle');
    Route::resource('addons', AddonController::class)->except(['show']);

    // Pacotes de vídeos vendidos pela produtora
    Route::post('/packs/sofi-generate', [PackController::class, 'sofiGenerate'])->name('packs.sofi_generate');
    Route::resource('packs', PackController::class)->except(['show']);
    Route::resource('showcase-videos', ShowcaseVideoController::class)->except(['show']);

    // Admin Planos de Assinatura Recorrente (Retainers / Modo Criador)
    Route::get('/admin/planos', [PlanController::class, 'index'])
        ->name('admin.plans.index');
    Route::get('/admin/planos/criar', [PlanController::class, 'create'])
        ->name('admin.plans.create');
    Route::post('/admin/planos', [PlanController::class, 'store'])
        ->name('admin.plans.store');
    Route::get('/admin/planos/{plan}/editar', [PlanController::class, 'edit'])
        ->name('admin.plans.edit');
    Route::put('/admin/planos/{plan}', [PlanController::class, 'update'])
        ->name('admin.plans.update');
    Route::delete('/admin/planos/{plan}', [PlanController::class, 'destroy'])
        ->name('admin.plans.destroy');

    // Admin Assinaturas & Contratos dos Clientes (MRR & Cotas)
    Route::get('/admin/assinaturas', [SubscriptionManagementController::class, 'index'])
        ->name('admin.subscriptions.index');
    Route::get('/admin/assinaturas/{subscription}', [SubscriptionManagementController::class, 'show'])
        ->name('admin.subscriptions.show');
    Route::post('/admin/assinaturas/{subscription}/cancel', [SubscriptionManagementController::class, 'cancel'])
        ->name('admin.subscriptions.cancel');
    Route::post('/admin/assinaturas/{subscription}/renew', [SubscriptionManagementController::class, 'renew'])
        ->name('admin.subscriptions.renew');

    // Stripe Connect (onboarding da produtora para receber pagamentos)
    Route::get('/stripe-connect', [StripeConnectController::class, 'index'])->name('stripe-connect.index');
    Route::get('/stripe-connect/connect', [StripeConnectController::class, 'connect'])->name('stripe-connect.connect');
    Route::get('/stripe-connect/return', [StripeConnectController::class, 'return'])->name('stripe-connect.return');

    // Admin Settings / Gateways (Criptografados no Banco)
    Route::get('/admin/settings/gateways', [GatewaySettingController::class, 'index'])
        ->name('admin.settings.gateways');
    Route::post('/admin/settings/gateways', [GatewaySettingController::class, 'update'])
        ->name('admin.settings.gateways.update');

    // Admin Faturamento & Faturas Oficiais AEAT (Gestoría)
    Route::get('/admin/invoices', [InvoiceManagementController::class, 'index'])
        ->name('admin.invoices.index');
    Route::get('/admin/invoices/export', [InvoiceManagementController::class, 'export'])
        ->name('admin.invoices.export');
    Route::get('/admin/invoices/{purchase}/download', [InvoiceManagementController::class, 'download'])
        ->name('admin.invoices.download');
    Route::post('/admin/purchases/{purchase}/confirm-bank-transfer', [BankTransferConfirmationController::class, 'confirm'])
        ->name('admin.purchases.confirm-bank-transfer');

    // Orçamentos & Propostas de Produção (Presupuestos)
    Route::post('/orcamentos/estimar', [QuoteController::class, 'estimate'])
        ->middleware('throttle:30,1')
        ->name('quotes.estimate');
    Route::get('/orcamentos/{quote}/pdf', [QuoteController::class, 'pdf'])
        ->name('quotes.pdf');
    Route::post('/orcamentos/{quote}/enviar', [QuoteController::class, 'send'])
        ->middleware('throttle:10,1')
        ->name('quotes.send');
    Route::patch('/orcamentos/{quote}/status', [QuoteController::class, 'updateStatus'])
        ->name('quotes.update-status');
    Route::resource('orcamentos', QuoteController::class)
        ->parameters(['orcamentos' => 'quote'])
        ->names('quotes');

    // Admin Settings / Redes Sociais & SEO Sofi
    Route::get('/admin/settings/platform', [TenantSettingsController::class, 'index'])
        ->name('admin.settings.platform');
    Route::post('/admin/settings/platform', [TenantSettingsController::class, 'update'])
        ->name('admin.settings.platform.update');
    Route::post('/admin/settings/platform/regenerate-seo', [TenantSettingsController::class, 'regenerateSeo'])
        ->name('admin.settings.platform.regenerate-seo');

    // Admin Settings / Perfil Fiscal por Produtora
    Route::get('/admin/settings/fiscal', [TenantFiscalSettingsController::class, 'index'])
        ->name('admin.settings.fiscal');
    Route::post('/admin/settings/fiscal', [TenantFiscalSettingsController::class, 'update'])
        ->name('admin.settings.fiscal.update');

    // Admin Settings / TPV Redsys & Bizum por Produtora
    Route::get('/admin/settings/redsys', [TenantRedsysSettingsController::class, 'index'])
        ->name('admin.settings.redsys');
    Route::post('/admin/settings/redsys', [TenantRedsysSettingsController::class, 'update'])
        ->name('admin.settings.redsys.update');

    // Admin Settings / Transferência Bancária por Produtora (IBAN/BIC)
    Route::get('/admin/settings/bank-transfer', [TenantBankTransferSettingsController::class, 'index'])
        ->name('admin.settings.bank-transfer');
    Route::post('/admin/settings/bank-transfer', [TenantBankTransferSettingsController::class, 'update'])
        ->name('admin.settings.bank-transfer.update');

    // Productions
    Route::resource('productions', ProductionController::class);
    Route::post('productions/{production}/mark-approved', [ProductionController::class, 'markAsApproved'])
        ->name('productions.mark-approved');
    Route::patch('productions/{production}/status', [ProductionController::class, 'updateStatus'])
        ->name('productions.update-status');

    // Monitoramento e Auditoria de Distribuição Social
    Route::get('/productions-distribuicoes', [SocialDistributionManagementController::class, 'index'])
        ->name('distributions.index');
    Route::post('/productions-distribuicoes/{videoPublication}/refresh', [SocialDistributionManagementController::class, 'refresh'])
        ->name('distributions.refresh');

    // Calendário Geral de Conteúdo e Prazos (Modo Criador Moat)
    Route::get('/calendario', [CalendarController::class, 'index'])
        ->name('calendar.index');

    // Sofi Batch Content Planner (Planejador Mensal em Lote)
    Route::get('/batch-planner', [BatchContentPlannerController::class, 'index'])
        ->name('batch_planner.index');
    Route::post('/batch-planner/generate', [BatchContentPlannerController::class, 'generate'])
        ->name('batch_planner.generate');
    Route::post('/batch-planner/commit', [BatchContentPlannerController::class, 'commit'])
        ->name('batch_planner.commit');

    // Gestão de Chaves de API para Desenvolvedores (B2B API)
    Route::get('/configuracoes/api-keys', [ApiKeyController::class, 'index'])
        ->name('api_keys.index');
    Route::post('/configuracoes/api-keys', [ApiKeyController::class, 'store'])
        ->name('api_keys.store');
    Route::delete('/configuracoes/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])
        ->name('api_keys.destroy');

    // Roteiros
    Route::scopeBindings()->prefix('productions/{production}')->group(function () {
        Route::post('generate-sofi-script', [RoteiroController::class, 'generateSofiScript'])
            ->name('roteiros.generate-sofi');
        Route::resource('roteiros', RoteiroController::class);
        Route::post('roteiros/{roteiro}/submit', [RoteiroController::class, 'submit'])
            ->name('roteiros.submit');
        Route::post('roteiros/{roteiro}/approve', [RoteiroController::class, 'approve'])
            ->name('roteiros.approve');
        Route::post('roteiros/{roteiro}/reject', [RoteiroController::class, 'reject'])
            ->name('roteiros.reject');
        Route::post('roteiros/{roteiro}/mark-in-production', [RoteiroController::class, 'markAsInProduction'])
            ->name('roteiros.mark-in-production');
    });

    // Videos
    Route::scopeBindings()->prefix('productions/{production}')->group(function () {
        Route::resource('videos', VideoController::class);
        Route::post('videos/{video}/mark-ready', [VideoController::class, 'markAsReady'])
            ->name('videos.mark-ready');
        Route::post('videos/{video}/publish', [VideoController::class, 'publish'])
            ->name('videos.publish');
        Route::post('videos/{video}/register-platform', [VideoController::class, 'registerPlatformId'])
            ->name('videos.register-platform');
        Route::post('videos/{video}/distribute', [VideoController::class, 'distribute'])
            ->name('videos.distribute');
        Route::post('videos/{video}/notify-cliente', [VideoController::class, 'notifyCliente'])
            ->name('videos.notify-cliente');
    });
});

// Sofi Magic Links (Assinados / 1 Toque sem Senha)
Route::get('/m/videos/{video}', [MagicVideoController::class, 'show'])
    ->name('magic.videos.show')
    ->middleware('signed');

// Arquivo de vídeo hospedado na NUME: URL assinada e temporária (reprodução com Range; download com login do dono).
Route::get('/media/videos/{video}', [\App\Http\Controllers\VideoMediaController::class, 'show'])
    ->whereNumber('video')
    ->middleware(['signed', 'throttle:300,1'])
    ->name('videos.media');

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');
Route::post('/webhooks/redsys', [RedsysWebhookController::class, 'handle'])->name('redsys.webhook');
Route::post('/webhooks/upload-post', [UploadPostWebhookController::class, 'handle'])->name('upload-post.webhook');
Route::get('/pagamento/redsys/sucesso', [StorefrontController::class, 'redsysSuccess'])->name('redsys.success');
Route::get('/pagamento/redsys/cancelado', [StorefrontController::class, 'redsysCancel'])->name('redsys.cancel');

// API Sofi Translator (Motor Próprio de Tradução)
Route::post('/api/v1/translate', [TranslationController::class, 'translate'])->name('api.translate');

// API Sofi Copilot Chat & Atendimento
Route::post('/api/v1/sofi/chat', [SofiChatController::class, 'chat'])->name('api.sofi.chat');
Route::get('/api/v1/sofi/conversations/{conversation}/messages', [SofiChatController::class, 'messages'])->name('api.sofi.messages');

// Magic Links de Aprovação Sem Senha (Zero Friction) - Assinatura Criptográfica HMAC SHA-256
Route::prefix('magic')->name('magic.')->middleware('signed')->group(function () {
    Route::get('/roteiros/{roteiro}', [MagicApprovalController::class, 'showRoteiro'])
        ->name('roteiros.show');
    Route::post('/roteiros/{roteiro}/approve', [MagicApprovalController::class, 'approveRoteiro'])
        ->name('roteiros.approve');
    Route::post('/roteiros/{roteiro}/reject', [MagicApprovalController::class, 'rejectRoteiro'])
        ->name('roteiros.reject');

    Route::get('/videos/{video}', [MagicApprovalController::class, 'showVideo'])
        ->name('videos.approval-legacy');
    Route::post('/videos/{video}/approve', [MagicApprovalController::class, 'approveVideo'])
        ->name('videos.approve');
});

// Aprovação e Checkout Público de Orçamento via Token
Route::prefix('orcamento')->name('quotes.public.')->group(function () {
    Route::get('/{token}', [QuoteApprovalController::class, 'show'])->name('show');
    Route::post('/{token}/aprovar', [QuoteApprovalController::class, 'approve'])->name('approve');
    Route::post('/{token}/recusar', [QuoteApprovalController::class, 'reject'])->name('reject');
});

require __DIR__.'/auth.php';
require __DIR__.'/portal.php';
