<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\Crm\LeadAutomation;
use App\Services\Crm\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regras automáticas do funil: cadastro e orçamento movem o lead. Dados fictícios.
 */
class CrmAutomationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'NUME', 'slug' => 'nume', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
    }

    private function quote(array $attributes = []): Quote
    {
        return Quote::create(array_merge([
            'tenant_id' => $this->tenant->id, 'status' => 'draft', 'client_name' => 'Padaria Ficticia', 'client_email' => 'ana@ficticio.test',
            'inputs' => ['videos' => 2], 'calculation' => [], 'subtotal' => 1000, 'tax_rate' => 21, 'tax_amount' => 210, 'total_amount' => 1210,
        ], $attributes));
    }

    public function test_portal_registration_creates_a_new_lead(): void
    {
        $this->post(route('portal.register'), [
            'name' => 'Ana Ficticia', 'email' => 'ana@ficticio.test', 'company_name' => 'Padaria Ficticia',
            'password' => 'Senha-Ficticia-123', 'password_confirmation' => 'Senha-Ficticia-123', 'terms' => '1',
        ])->assertRedirect();

        $lead = Lead::forTenant($this->tenant->id)->sole();
        $this->assertSame('portal_register', $lead->source);
        $this->assertSame('novo', $lead->stage);
        $this->assertSame('contract', $lead->legal_basis);
        $this->assertNotNull($lead->cliente_id);
    }

    public function test_quote_lifecycle_moves_the_lead_through_the_funnel(): void
    {
        $quote = $this->quote();
        $this->assertSame(0, Lead::forTenant($this->tenant->id)->count(), 'rascunho não cria lead');

        $quote->update(['status' => 'sent']);
        $lead = Lead::forTenant($this->tenant->id)->sole();
        $this->assertSame('proposta', $lead->stage);
        $this->assertEquals(1210, $lead->estimated_value);

        $quote->update(['status' => 'accepted']);
        $this->assertSame('ganho', $lead->fresh()->stage);

        // Lead decidido não volta por regra automática.
        $quote->update(['status' => 'rejected']);
        $this->assertSame('ganho', $lead->fresh()->stage);
    }

    public function test_client_request_qualifies_and_rejection_loses_the_lead(): void
    {
        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);
        $quote = $this->quote(['status' => Quote::STATUS_REQUESTED, 'cliente_id' => $cliente->id]);

        $lead = Lead::forTenant($this->tenant->id)->sole();
        $this->assertSame('qualificado', $lead->stage);
        $this->assertSame('quote_request', $lead->source);
        $this->assertSame($cliente->id, $lead->cliente_id);

        $quote->update(['status' => 'rejected']);
        $this->assertSame('perdido', $lead->fresh()->stage);
        $this->assertStringContainsString('recusado', $lead->fresh()->lost_reason);
    }

    public function test_automation_never_breaks_the_quote_flow(): void
    {
        $this->mock(LeadService::class)->shouldReceive('findOrCreate')->andThrow(new \RuntimeException('falha simulada'));
        $this->app->forgetInstance(LeadAutomation::class);

        $quote = $this->quote();
        $quote->update(['status' => 'sent']);

        $this->assertSame('sent', $quote->fresh()->status);
    }
}
