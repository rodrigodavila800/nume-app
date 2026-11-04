<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\PackBuilderController;
use App\Jobs\SendQuoteProposalDocuments;
use App\Models\Addon;
use App\Models\Cliente;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\VideoType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Monte seu pacote" no portal do cliente. Dados fictícios; PDF na fila simulada.
 */
class PortalPackBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Cliente $cliente;

    private VideoType $reels;

    private Addon $drone;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendQuoteProposalDocuments::class]);
        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha', 'status' => 'active', 'subscription_expires_at' => now()->addMonth()]);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'company_name' => 'Padaria Ficticia', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);
        $this->reels = VideoType::create(['tenant_id' => $this->tenant->id, 'name' => 'Reels 30 s', 'slug' => 'reels', 'duration_seconds' => 30, 'blocks' => [], 'builder_price' => 100, 'is_active' => true]);
        $this->drone = Addon::create(['tenant_id' => $this->tenant->id, 'name' => 'Drone', 'slug' => 'drone', 'default_price' => 150, 'currency' => 'eur', 'is_active' => true, 'in_builder' => true]);
    }

    public function test_the_page_lists_the_tenant_catalog_with_prices(): void
    {
        $other = Tenant::create(['name' => 'Produtora Beta', 'slug' => 'produtora-beta']);
        VideoType::create(['tenant_id' => $other->id, 'name' => 'Da outra produtora', 'slug' => 'outra', 'duration_seconds' => 30, 'blocks' => [], 'builder_price' => 9, 'is_active' => true]);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.builder'))
            ->assertOk()
            ->assertSee('Reels 30 s')
            ->assertSee('100,00 €')
            ->assertSee('Drone')
            ->assertDontSee('Da outra produtora');
    }

    public function test_confirming_creates_the_proposal_for_the_client_and_goes_to_payment(): void
    {
        $response = $this->actingAs($this->cliente, 'cliente')->post(route('portal.builder.store'), [
            'items' => [$this->reels->id => 4],
            'addons' => [$this->drone->id],
        ]);

        $quote = Quote::forTenant($this->tenant->id)->sole();
        $response->assertRedirect(route('quotes.public.show', $quote->approval_token));

        $this->assertSame('sent', $quote->status);
        $this->assertSame($this->cliente->id, $quote->cliente_id);
        $this->assertSame('Padaria Ficticia', $quote->client_name);
        $this->assertSame('portal', $quote->request_details['source']);
        $this->assertSame(4, $quote->inputs['videos']);
        $this->assertEquals(550.00, $quote->subtotal);
        $this->assertEquals(665.50, $quote->total_amount);
        $this->assertEquals(332.75, $quote->deposit_amount);
        Queue::assertPushed(SendQuoteProposalDocuments::class, fn ($job) => $job->quoteId === $quote->id && $job->whatsAppTo === null);

        $this->get(route('portal.quotes.index'))->assertSee($quote->number);
    }

    public function test_the_same_package_twice_reuses_the_proposal(): void
    {
        $payload = ['items' => [$this->reels->id => 2]];

        $this->actingAs($this->cliente, 'cliente')->post(route('portal.builder.store'), $payload);
        $this->actingAs($this->cliente, 'cliente')->post(route('portal.builder.store'), $payload);

        $this->assertSame(1, Quote::forTenant($this->tenant->id)->count());
        Queue::assertPushed(SendQuoteProposalDocuments::class, 1);
    }

    public function test_an_empty_package_is_refused(): void
    {
        $this->actingAs($this->cliente, 'cliente')
            ->from(route('portal.builder'))
            ->post(route('portal.builder.store'), ['items' => [$this->reels->id => 0], 'addons' => [$this->drone->id]])
            ->assertRedirect(route('portal.builder'))
            ->assertSessionHas('error');

        $this->assertSame(0, Quote::count());
    }

    public function test_a_simulation_saved_in_the_session_comes_back_prefilled(): void
    {
        $this->actingAs($this->cliente, 'cliente')
            ->withSession([PackBuilderController::SESSION_KEY => ['items' => [$this->reels->id => 3], 'addons' => [$this->drone->id]]])
            ->get(route('portal.builder'))
            ->assertOk()
            ->assertSee('name="items['.$this->reels->id.']"', false)
            ->assertSee('value="3"', false);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('portal.builder'))->assertRedirect(route('portal.login'));
    }
}
