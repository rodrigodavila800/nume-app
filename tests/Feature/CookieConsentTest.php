<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aviso de cookies (RGPD/LSSI) e players externos só com aceite. A lógica de navegador fica em
 * public/js/cookie-consent.js; aqui se confere a marcação servida. Dados fictícios.
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_banner_is_served_on_the_storefront_and_the_legal_pages(): void
    {
        Tenant::create(['name' => 'NUME', 'slug' => 'nume', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);

        foreach ([route('marketplace.index'), route('legal.privacy'), route('legal.cookies')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('id="nume-cookie-consent"', false)
                ->assertSee('data-consent-accept', false)
                ->assertSee('data-consent-reject', false)
                ->assertSee('data-consent-configure', false)
                ->assertSee('js/cookie-consent.js', false)
                ->assertSee(route('legal.cookies'), false);
        }
    }

    public function test_the_banner_speaks_spanish_for_spanish_visitors(): void
    {
        $this->withSession(['locale' => 'es'])->get(route('legal.cookies'))->assertOk()
            ->assertSee('Cookies y contenido externo')
            ->assertSee('Rechazar');
    }

    public function test_assets_exist_and_external_content_starts_disabled(): void
    {
        $script = file_get_contents(public_path('js/cookie-consent.js'));

        $this->assertFileExists(public_path('css/cookie-consent.css'));
        $this->assertStringContainsString("const COOKIE = 'nume_cookie_consent'", $script);
        $this->assertStringContainsString('return !!(consent && consent.external);', $script, 'sem escolha = conteúdo externo bloqueado');
        $this->assertStringNotContainsString('style="', file_get_contents(resource_path('views/partials/cookie-consent.blade.php')));
    }

    public function test_client_facing_players_go_through_the_consent_check(): void
    {
        foreach (['magic/video', 'portal/productions/show'] as $view) {
            $html = file_get_contents(resource_path("views/{$view}.blade.php"));
            $this->assertStringContainsString('data-consent-src="{{ $video->embed_url }}"', $html, $view);
            $this->assertStringNotContainsString('src="{{ $video->embed_url }}"', str_replace('data-consent-src', '', $html), $view);
        }

        foreach (['storefront/show', 'portal/packs/index', 'portal/plans/index'] as $view) {
            $this->assertStringContainsString('NumeConsent.loadEmbed(iframe', file_get_contents(resource_path("views/{$view}.blade.php")), $view);
        }
    }
}
