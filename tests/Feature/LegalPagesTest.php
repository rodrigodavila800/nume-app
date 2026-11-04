<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Páginas públicas exigidas pelas redes para aprovar o app (Google, Meta, TikTok).
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_are_public_and_cross_linked(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('Contas de Redes Sociais Conectadas')
            ->assertSee('Google API Services User Data Policy')
            ->assertSee('Limited Use requirements')
            ->assertSee('myaccount.google.com/permissions')
            ->assertSee(route('legal.data-deletion'), false);

        $this->get(route('legal.terms'))->assertOk()
            ->assertSee('Termos de Serviço do YouTube')
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('legal.data-deletion'), false);

        $this->get(route('legal.data-deletion'))->assertOk()
            ->assertSee('Desconectar')
            ->assertSee('15 dias úteis')
            ->assertSee(route('legal.privacy').'#redes-sociais', false);
    }

    public function test_legal_pages_follow_spanish_data_protection_only(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('RGPD')
            ->assertSee('LOPDGDD')
            ->assertSee('Agencia Española de Protección de Datos')
            ->assertDontSee('LGPD');

        $this->get(route('legal.data-deletion'))->assertOk()->assertDontSee('LGPD');
        $this->get(route('legal.terms'))->assertOk()->assertDontSee('LGPD');
    }

    public function test_urls_are_stable_for_the_app_review_forms(): void
    {
        $this->assertSame('/privacidade', route('legal.privacy', absolute: false));
        $this->assertSame('/termos', route('legal.terms', absolute: false));
        $this->assertSame('/exclusao-de-dados', route('legal.data-deletion', absolute: false));
    }

    public function test_pages_have_no_inline_css(): void
    {
        foreach (['page', 'es/privacy', 'pt/privacy', 'es/cookies', 'pt/cookies', 'es/notice', 'pt/notice', 'es/terms', 'pt/terms', 'es/data-deletion', 'pt/data-deletion'] as $view) {
            $this->assertStringNotContainsString('style="', file_get_contents(resource_path("views/legal/{$view}.blade.php")), $view);
        }
    }

    public function test_privacy_cookies_and_legal_notice_exist_in_spanish(): void
    {
        $this->withSession(['locale' => 'es']);

        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('Política de privacidad')
            ->assertSee('Responsable del tratamiento')
            ->assertSee('Cuentas de redes sociales conectadas')
            ->assertSee('Limited Use requirements')
            ->assertSee('Agencia Española de Protección de Datos')
            ->assertDontSee('cookies analíticos');

        $this->get(route('legal.cookies'))->assertOk()
            ->assertSee('Política de cookies')
            ->assertSee('no usa cookies de análisis, publicidad ni seguimiento', false)
            ->assertSee('nume_cookie_consent')
            ->assertSee((string) config('session.cookie'));

        $this->get(route('legal.notice'))->assertOk()
            ->assertSee('Aviso legal')
            ->assertSee('LSSI-CE');

        $this->get(route('legal.terms'))->assertOk()
            ->assertSee('Términos del servicio de YouTube');

        $this->get(route('legal.data-deletion'))->assertOk()
            ->assertSee('Eliminación de datos')
            ->assertSee('15 días hábiles');
    }

    public function test_the_portal_footer_always_shows_the_legal_links(): void
    {
        $this->get(route('legal.terms'))->assertOk()
            ->assertSee(route('legal.cookies'), false)
            ->assertSee(route('legal.notice'), false)
            ->assertSee('data-open-cookie-settings', false)
            ->assertDontSee('admin@numecreative.com');
    }

    public function test_the_owner_comes_from_the_fiscal_settings_and_sample_values_never_show(): void
    {
        $tenant = \App\Models\Tenant::create(['name' => 'NUME', 'slug' => 'nume', 'status' => 'active']);

        $this->get(route('legal.notice'))->assertOk()
            ->assertDontSee('B12345678')
            ->assertSee('pendente de configuração');

        \App\Models\TenantSetting::set($tenant, 'fiscal_company_name', 'Empresa Ficticia S.L.');
        \App\Models\TenantSetting::set($tenant, 'fiscal_company_cif', 'B00000000');
        \App\Models\TenantSetting::set($tenant, 'fiscal_company_address', 'Calle Ficticia 1');
        \App\Models\TenantSetting::set($tenant, 'fiscal_company_postal_code', '50001');
        \App\Models\TenantSetting::set($tenant, 'fiscal_company_city', 'Zaragoza');

        $this->get(route('legal.notice'))->assertOk()
            ->assertSee('Empresa Ficticia S.L.')
            ->assertSee('B00000000')
            ->assertSee('Calle Ficticia 1, 50001 Zaragoza')
            ->assertSee('numecreative.es@gmail.com');
    }
}
