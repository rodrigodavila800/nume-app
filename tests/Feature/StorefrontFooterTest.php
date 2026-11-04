<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rodapé da vitrine: WhatsApp com o número configurado e sem NIF. Dados fictícios.
 */
class StorefrontFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_footer_shows_the_configured_whatsapp_number_and_no_tax_id(): void
    {
        Tenant::create(['name' => 'NUME', 'slug' => 'nume', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        config(['services.whatsapp.contact_number' => '34746055907']);

        $this->get(route('marketplace.index'))
            ->assertOk()
            ->assertSee('+34 746 05 59 07')
            ->assertSee('https://wa.me/34746055907', false)
            ->assertDontSee('664 274 151')
            ->assertDontSee('NIF B50987654');
    }
}
