<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Tenant;
use App\Support\Anamnesis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anamnese do cliente no portal. Dados fictícios.
 */
class AnamnesisTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->cliente = Cliente::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente', 'email' => 'cliente@ficticio.test', 'password' => 'secret12345',
            'company_name' => 'Padaria Ficticia', 'brand_niche' => 'Padaria artesanal', 'website' => 'https://padaria.ficticio.test',
        ]);
    }

    private function answers(array $overrides = []): array
    {
        return array_merge([
            'objective' => 'sell_more',
            'main_offer' => 'Pão de fermentação natural',
            'average_ticket' => '12 €',
            'ideal_customer' => 'Famílias do bairro que querem pão sem conservantes',
            'tone' => 'close',
            'priority_networks' => ['instagram', 'tiktok', 'instagram'],
            'posting_frequency' => '2_week',
            'wants_ads' => '0',
            'ads_monthly_budget' => '500 €',
        ], $overrides);
    }

    public function test_client_answers_the_anamnesis(): void
    {
        $this->actingAs($this->cliente, 'cliente')
            ->put(route('portal.anamnesis.update'), $this->answers())
            ->assertRedirect(route('portal.marketing-plan.index'));

        $cliente = $this->cliente->fresh();
        $this->assertTrue($cliente->hasCompletedAnamnesis());
        $this->assertSame(['instagram', 'tiktok'], $cliente->anamnesis['priority_networks']);
        $this->assertFalse($cliente->anamnesis['wants_ads']);
        $this->assertNull($cliente->anamnesis['ads_monthly_budget'], 'sem anúncio, a verba não é guardada');
        $this->assertSame(Anamnesis::VERSION, $cliente->anamnesis['version']);
        $this->assertSame(8, Anamnesis::monthlyVideos($cliente->anamnesis));
    }

    public function test_invalid_answers_are_refused(): void
    {
        $this->actingAs($this->cliente, 'cliente')
            ->from(route('portal.anamnesis.edit'))
            ->put(route('portal.anamnesis.update'), $this->answers(['objective' => 'hackear', 'priority_networks' => ['orkut']]))
            ->assertRedirect(route('portal.anamnesis.edit'))
            ->assertSessionHasErrors(['objective', 'priority_networks.0']);

        $this->assertFalse($this->cliente->fresh()->hasCompletedAnamnesis());
    }

    public function test_form_shows_saved_answers_and_requires_login(): void
    {
        $this->get(route('portal.anamnesis.edit'))->assertRedirect();

        $this->cliente->forceFill(['anamnesis' => Anamnesis::normalize($this->answers()), 'anamnesis_completed_at' => now()])->save();

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.anamnesis.edit'))
            ->assertOk()
            ->assertSee('Pão de fermentação natural')
            ->assertSee('value="instagram" checked', false);
    }

    public function test_prompt_block_lists_the_answers(): void
    {
        $block = Anamnesis::promptBlock(Anamnesis::normalize($this->answers(['wants_ads' => '1'])));

        $this->assertStringContainsString('- OBJETIVO: Vender mais', $block);
        $this->assertStringContainsString('- REDES PRIORITÁRIAS: instagram, tiktok', $block);
        $this->assertStringContainsString('- QUER ANÚNCIOS: sim (verba mensal: 500 €)', $block);
    }

    public function test_view_has_no_inline_css(): void
    {
        $this->assertStringNotContainsString('style="', file_get_contents(resource_path('views/portal/anamnesis/edit.blade.php')));
    }
}
