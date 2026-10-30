<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SocialPublishing\Connectors\ConnectorRegistry;
use App\Services\SofiCopywriterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantSettingsController extends Controller
{
    public function __construct(
        protected SofiCopywriterService $sofiService,
        protected ConnectorRegistry $connectorRegistry,
    ) {}

    /**
     * Exibe a tela de configuração de Redes Sociais e SEO da Produtora
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $tenant = $request->user()->tenant;
        Setting::loadIntoConfig();

        $oauthProviders = $this->buildOauthProvidersViewData();

        return view('admin.settings.tenant', compact('tenant', 'oauthProviders'));
    }

    /**
     * Salva as redes sociais, os dados de SEO e as credenciais OAuth do Nume Social Engine.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'social_instagram' => ['nullable', 'string', 'max:255'],
            'social_youtube' => ['nullable', 'string', 'max:255'],
            'social_linkedin' => ['nullable', 'string', 'max:255'],
            'social_tiktok' => ['nullable', 'string', 'max:255'],
            'social_whatsapp' => ['nullable', 'string', 'max:50'],
            'social_facebook' => ['nullable', 'string', 'max:255'],
            'seo_meta_title' => ['nullable', 'string', 'max:70'],
            'seo_meta_description' => ['nullable', 'string', 'max:160'],
            'seo_og_description' => ['nullable', 'string', 'max:200'],
            'seo_og_image' => ['nullable', 'string', 'max:255'],
            'seo_schema_keywords' => ['nullable', 'string'],
            'seo_canonical_url' => ['nullable', 'string', 'max:255'],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            'meta_client_id' => ['nullable', 'string', 'max:255'],
            'meta_client_secret' => ['nullable', 'string', 'max:255'],
            'meta_config_id' => ['nullable', 'string', 'max:255'],
            'tiktok_client_key' => ['nullable', 'string', 'max:255'],
            'tiktok_client_secret' => ['nullable', 'string', 'max:255'],
            'linkedin_client_id' => ['nullable', 'string', 'max:255'],
            'linkedin_client_secret' => ['nullable', 'string', 'max:255'],
            'x_client_id' => ['nullable', 'string', 'max:255'],
            'x_client_secret' => ['nullable', 'string', 'max:255'],
            'threads_client_id' => ['nullable', 'string', 'max:255'],
            'threads_client_secret' => ['nullable', 'string', 'max:255'],
            'meta_instagram_config_id' => ['nullable', 'string', 'max:255'],
            'pinterest_client_id' => ['nullable', 'string', 'max:255'],
            'pinterest_client_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $tenantFields = [
            'social_instagram',
            'social_youtube',
            'social_linkedin',
            'social_tiktok',
            'social_whatsapp',
            'social_facebook',
            'seo_meta_title',
            'seo_meta_description',
            'seo_og_description',
            'seo_og_image',
            'seo_schema_keywords',
            'seo_canonical_url',
        ];

        $tenant->update(collect($validated)->only($tenantFields)->all());

        $idFields = [
            'google_client_id',
            'meta_client_id',
            'meta_config_id',
            'tiktok_client_key',
            'linkedin_client_id',
            'x_client_id',
            'threads_client_id',
            'meta_instagram_config_id',
            'pinterest_client_id',
        ];

        foreach ($idFields as $field) {
            if ($request->has($field)) {
                $val = trim((string) $request->input($field, ''));
                Setting::set($field, $val !== '' ? $val : null, 'social_oauth', true);
            }
        }

        $secretFields = [
            'google_client_secret',
            'meta_client_secret',
            'tiktok_client_secret',
            'linkedin_client_secret',
            'x_client_secret',
            'threads_client_secret',
            'pinterest_client_secret',
        ];

        foreach ($secretFields as $field) {
            if ($request->filled($field)) {
                Setting::set($field, trim((string) $request->input($field)), 'social_oauth', true);
            }
        }

        Setting::loadIntoConfig();

        return redirect()->route('admin.settings.platform')
            ->with('status', __('Configurações de Redes Sociais, OAuth e SEO da plataforma atualizadas com sucesso!'));
    }

    /**
     * Sofi AI gera o SEO completo da plataforma com base no perfil do Tenant
     */
    public function regenerateSeo(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $tenant = $request->user()->tenant;

        $seoData = $this->sofiService->generateTenantSeo($tenant);
        $seoData['seo_last_generated_at'] = now();

        $tenant->update($seoData);

        return response()->json([
            'status' => 'success',
            'message' => 'SEO gerado pela Sofi com olhar de autoridade e conversão!',
            'data' => $seoData,
        ]);
    }

    /**
     * Monta os metadados das 7 integrações OAuth nativas sem expor secrets descriptografados na view.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildOauthProvidersViewData(): array
    {
        return [
            [
                'key' => 'meta',
                'label' => 'Meta (Instagram Reels & Facebook Reels)',
                'id_field' => 'meta_client_id',
                'id_label' => 'Meta App ID (Client ID)',
                'id_value' => (string) (Setting::get('meta_client_id') ?: config('services.meta.client_id', '')),
                'secret_field' => 'meta_client_secret',
                'secret_label' => 'Meta App Secret',
                'has_secret' => ! empty(Setting::get('meta_client_secret') ?: config('services.meta.client_secret')),
                'extras' => [
                    [
                        'field' => 'meta_config_id',
                        'label' => 'Config ID (Login for Business — opcional)',
                        'value' => (string) (Setting::get('meta_config_id') ?: config('services.meta.config_id', '')),
                        'placeholder' => __('Opcional — usado no Meta Login for Business'),
                    ],
                    [
                        'field' => 'meta_instagram_config_id',
                        'label' => 'Config ID do Instagram (login unificado, sem Página)',
                        'value' => (string) (Setting::get('meta_instagram_config_id') ?: config('services.meta.instagram_config_id', '')),
                        'placeholder' => __('Instagram API > API setup with Unified login > configuração'),
                    ],
                ],
                'configured' => $this->connectorRegistry->for('meta')?->isConfigured() ?? false,
                'callbacks' => [
                    route('portal.social.oauth.callback', 'meta'),
                    route('portal.social.oauth.callback', 'instagram'),
                ],
            ],
            [
                'key' => 'google',
                'label' => 'Google / YouTube (Shorts & Vídeos)',
                'id_field' => 'google_client_id',
                'id_label' => 'Google Client ID',
                'id_value' => (string) (Setting::get('google_client_id') ?: config('services.google.client_id', '')),
                'secret_field' => 'google_client_secret',
                'secret_label' => 'Google Client Secret',
                'has_secret' => ! empty(Setting::get('google_client_secret') ?: config('services.google.client_secret')),
                'configured' => $this->connectorRegistry->for('youtube')?->isConfigured() ?? false,
                'callbacks' => [
                    route('portal.social.oauth.callback', 'youtube'),
                ],
            ],
            [
                'key' => 'tiktok',
                'label' => 'TikTok (Content Posting API v2)',
                'id_field' => 'tiktok_client_key',
                'id_label' => 'TikTok Client Key',
                'id_value' => (string) (Setting::get('tiktok_client_key') ?: config('services.tiktok.client_key', '')),
                'secret_field' => 'tiktok_client_secret',
                'secret_label' => 'TikTok Client Secret',
                'has_secret' => ! empty(Setting::get('tiktok_client_secret') ?: config('services.tiktok.client_secret')),
                'configured' => $this->connectorRegistry->for('tiktok')?->isConfigured() ?? false,
                'callbacks' => [
                    route('portal.social.oauth.callback', 'tiktok'),
                ],
            ],
            [
                'key' => 'linkedin',
                'label' => 'LinkedIn (Videos API)',
                'id_field' => 'linkedin_client_id',
                'id_label' => 'LinkedIn Client ID',
                'id_value' => (string) (Setting::get('linkedin_client_id') ?: config('services.linkedin.client_id', '')),
                'secret_field' => 'linkedin_client_secret',
                'secret_label' => 'LinkedIn Client Secret',
                'has_secret' => ! empty(Setting::get('linkedin_client_secret') ?: config('services.linkedin.client_secret')),
                'configured' => $this->connectorRegistry->for('linkedin')?->isConfigured() ?? false,
                'callbacks' => [
                    route('portal.social.oauth.callback', 'linkedin'),
                ],
            ],
            [
                'key' => 'x',
                'label' => 'X / Twitter (API v2 OAuth 2.0 PKCE)',
                'id_field' => 'x_client_id',
                'id_label' => 'X Client ID',
                'id_value' => (string) (Setting::get('x_client_id') ?: config('services.x.client_id', '')),
                'secret_field' => 'x_client_secret',
                'secret_label' => 'X Client Secret',
                'has_secret' => ! empty(Setting::get('x_client_secret') ?: config('services.x.client_secret')),
                'configured' => $this->connectorRegistry->for('x')?->isConfigured() ?? false,
                'callbacks' => [
                    route('portal.social.oauth.callback', 'x'),
                ],
            ],
            [
                'key' => 'threads',
                'label' => 'Threads (Meta Threads Publishing API)',
                'id_field' => 'threads_client_id',
                'id_label' => 'Threads App ID (Client ID)',
                'id_value' => (string) (Setting::get('threads_client_id') ?: config('services.threads.client_id', '')),
                'secret_field' => 'threads_client_secret',
                'secret_label' => 'Threads App Secret',
                'has_secret' => ! empty(Setting::get('threads_client_secret') ?: config('services.threads.client_secret')),
                'configured' => $this->connectorRegistry->for('threads')?->isConfigured() ?? false,
                'callbacks' => [
                    route('portal.social.oauth.callback', 'threads'),
                ],
            ],
            [
                'key' => 'pinterest',
                'label' => 'Pinterest (API v5)',
                'id_field' => 'pinterest_client_id',
                'id_label' => 'Pinterest App ID (Client ID)',
                'id_value' => (string) (Setting::get('pinterest_client_id') ?: config('services.pinterest.client_id', '')),
                'secret_field' => 'pinterest_client_secret',
                'secret_label' => 'Pinterest App Secret',
                'has_secret' => ! empty(Setting::get('pinterest_client_secret') ?: config('services.pinterest.client_secret')),
                'configured' => $this->connectorRegistry->for('pinterest')?->isConfigured() ?? false,
                'callbacks' => [
                    route('portal.social.oauth.callback', 'pinterest'),
                ],
            ],
        ];
    }
}
