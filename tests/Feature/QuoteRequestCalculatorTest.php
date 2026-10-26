<?php

namespace Tests\Feature;

use App\Mail\QuoteProposalMail;
use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Quotes\QuoteRequestService;
use App\Services\VideoTypes\VideoTypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Calculadora da equipe com o pedido do cliente como entrada. Dados fictícios.
 */
class QuoteRequestCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => null, 'sofi.keys.gemini' => null]);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->admin = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345', 'company_name' => 'Padaria Ficticia']);
    }

    private function requestQuote(int $videos, ?float $typeHours): Quote
    {
        $type = app(VideoTypeCatalog::class)->active($this->tenant->id)->first();
        $type->update(['edit_hours_per_video' => $typeHours]);
        $plan = MarketingPlan::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'month_year' => '2026-11']);
        $plan->approveBy($this->cliente);

        return app(QuoteRequestService::class)->createFromPortal($this->cliente, $plan, [
            'videos' => $videos, 'video_type_id' => $type->id, 'social_networks' => 2, 'wants_ads' => false, 'ads_budget' => null, 'message' => 'Quero começar já',
        ], $type->fresh(), false);
    }

    public function test_video_type_hours_drive_the_edit_hours_of_the_request(): void
    {
        $this->assertEquals(6 * 4.5, $this->requestQuote(6, 4.5)->inputs['edicao']);
    }

    public function test_type_without_hours_uses_the_producer_default(): void
    {
        $this->assertEquals(6 * 3, $this->requestQuote(6, null)->inputs['edicao']);
    }

    public function test_admin_sets_edit_hours_on_the_video_type(): void
    {
        $type = app(VideoTypeCatalog::class)->active($this->tenant->id)->first();

        $this->actingAs($this->admin)->put(route('video_types.update', $type), [
            'name' => $type->name, 'duration_seconds' => 30, 'edit_hours_per_video' => '2.5', 'is_active' => '1',
            'blocks' => [['name' => 'Gancho', 'start' => 0, 'end' => 30, 'purpose' => 'x']],
        ])->assertRedirect();

        $this->assertSame(2.5, $type->fresh()->edit_hours_per_video);
    }

    public function test_team_opens_the_request_with_the_calculator_filled_and_sends_it(): void
    {
        Mail::fake();
        $quote = $this->requestQuote(8, null);

        $this->actingAs($this->admin)->get(route('quotes.edit', $quote))
            ->assertOk()
            ->assertSee('Pedido do cliente pelo portal: revise os valores e envie a proposta')
            ->assertSee('Vendas (30 s)')
            ->assertSee('Quero começar já');

        $this->actingAs($this->admin)->postJson(route('quotes.send', $quote), ['email' => 'ana@ficticio.test'])->assertOk();

        $quote->refresh();
        $this->assertSame('sent', $quote->status);
        $this->assertNotNull($quote->valid_until, 'validade passa a contar no envio');
        $this->assertNotNull($quote->approval_token);
        Mail::assertSent(QuoteProposalMail::class);
    }

    public function test_quote_cannot_point_to_a_client_of_another_producer(): void
    {
        $outra = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $alheio = Cliente::create(['tenant_id' => $outra->id, 'name' => 'Alheio', 'email' => 'alheio@ficticio.test', 'password' => 'secret12345']);

        $this->actingAs($this->admin)->post(route('quotes.store'), [
            'cliente_id' => $alheio->id, 'client_name' => 'X',
            'inputs' => ['videos' => 1, 'pre' => 1, 'gravacao' => 1, 'edicao' => 1, 'motion' => 0, 'filmmakers' => 1,
                'valor_hora' => 10, 'valor_hora_editor' => 10, 'iva' => 21, 'cambios' => 1, 'prazo' => 10],
        ])->assertSessionHasErrors('cliente_id');

        $this->assertSame(0, Quote::withoutGlobalScopes()->count());
    }
}
