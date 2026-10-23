<?php

use App\Http\Controllers\Api\V1\DocumentationController;
use App\Http\Controllers\Api\V1\SocialApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Nume Social Engine - Public Developer API (v1)
|--------------------------------------------------------------------------
|
| API White-label para integração de desenvolvedores e automações externas
| (Make.com, Zapier, n8n, ERPs proprietários de produtoras).
|
*/

// Documentação Pública da API (Swagger / OpenAPI & No-Code Templates)
Route::prefix('v1')->group(function () {
    Route::get('/docs', [DocumentationController::class, 'index'])->name('api.v1.docs');
    Route::get('/docs.json', [DocumentationController::class, 'openApiJson'])->name('api.v1.docs.json');
    Route::get('/integrations/make', [DocumentationController::class, 'downloadMake'])->name('api.v1.integrations.make');
    Route::get('/integrations/n8n', [DocumentationController::class, 'downloadN8n'])->name('api.v1.integrations.n8n');
});

// Endpoints protegidos por API Key (Header 'Authorization: Bearer nume_live_...' ou 'X-API-Key')
Route::prefix('v1/social')
    ->middleware(['auth.api_key'])
    ->group(function () {
        Route::post('/connect-url', [SocialApiController::class, 'connectUrl'])->name('api.v1.social.connect_url');
        Route::post('/publish', [SocialApiController::class, 'publish'])->name('api.v1.social.publish');
        Route::get('/publications/{id}', [SocialApiController::class, 'showPublication'])->name('api.v1.social.publication');
        Route::get('/profiles/{id}', [SocialApiController::class, 'profile'])->name('api.v1.social.profile');
    });

// Conector Oficial de IA para ChatGPT / Claude (Sofi Ops Assistant)
Route::prefix('v1/assistant')->group(function () {
    Route::get('/openapi.json', [\App\Http\Controllers\Api\AiAssistantApiController::class, 'openApiSchema'])->name('api.assistant.openapi');
    Route::get('/instructions', [\App\Http\Controllers\Api\AiAssistantApiController::class, 'promptInstructions'])->name('api.assistant.instructions');

    Route::middleware(['auth.api_key'])->group(function () {
        Route::get('/overview', [\App\Http\Controllers\Api\AiAssistantApiController::class, 'overview'])->name('api.assistant.overview');
        Route::get('/pending-approvals', [\App\Http\Controllers\Api\AiAssistantApiController::class, 'pendingApprovals'])->name('api.assistant.pending_approvals');
        Route::get('/schedule', [\App\Http\Controllers\Api\AiAssistantApiController::class, 'schedule'])->name('api.assistant.schedule');
    });
});
