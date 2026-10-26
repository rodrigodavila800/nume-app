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
        foreach (['privacy', 'terms', 'data-deletion'] as $view) {
            $this->assertStringNotContainsString('style="', file_get_contents(resource_path("views/legal/{$view}.blade.php")), $view);
        }
    }
}
