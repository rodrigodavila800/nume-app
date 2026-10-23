<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\SofiCopywriterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantSettingsController extends Controller
{
    public function __construct(
        protected SofiCopywriterService $sofiService
    ) {}

    /**
     * Exibe a tela de configuração de Redes Sociais e SEO da Produtora
     */
    public function index(Request $request): View
    {
        $tenant = $request->user()->tenant;

        return view('admin.settings.tenant', compact('tenant'));
    }

    /**
     * Salva as redes sociais e os dados de SEO
     */
    public function update(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'social_instagram' => ['nullable', 'string', 'max:255'],
            'social_youtube'   => ['nullable', 'string', 'max:255'],
            'social_linkedin'  => ['nullable', 'string', 'max:255'],
            'social_tiktok'    => ['nullable', 'string', 'max:255'],
            'social_whatsapp'  => ['nullable', 'string', 'max:50'],
            'social_facebook'  => ['nullable', 'string', 'max:255'],
            'seo_meta_title'   => ['nullable', 'string', 'max:70'],
            'seo_meta_description' => ['nullable', 'string', 'max:160'],
            'seo_og_description'   => ['nullable', 'string', 'max:200'],
            'seo_og_image'         => ['nullable', 'string', 'max:255'],
            'seo_schema_keywords'  => ['nullable', 'string'],
            'seo_canonical_url'    => ['nullable', 'string', 'max:255'],
        ]);

        $tenant->update($validated);

        return redirect()->route('admin.settings.platform')
            ->with('status', 'Configurações de Redes Sociais e SEO da plataforma atualizadas com sucesso!');
    }

    /**
     * Sofi AI gera o SEO completo da plataforma com base no perfil do Tenant
     */
    public function regenerateSeo(Request $request): JsonResponse
    {
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
}
