<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RGPD no CRM: exportar, anonimizar e prazo de conservação de leads perdidos. Dados fictícios.
 */
class CrmPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $editor;

    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->admin = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->editor = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Editora', 'email' => 'editora@ficticio.test', 'password' => 'secret12345', 'role' => 'editor']);

        $service = app(LeadService::class);
        $this->lead = $service->findOrCreate($this->tenant->id, ['name' => 'Ana Ficticia', 'email' => 'ana@ficticio.test', 'phone' => '600000000'], 'manual');
        $service->log($this->lead, 'call', 'Ana pediu proposta por telefone');
    }

    public function test_admin_exports_the_lead_data(): void
    {
        $response = $this->actingAs($this->admin)->get(route('crm.leads.export', $this->lead))->assertOk();

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('ana@ficticio.test', $response->json('lead.email'));
        $this->assertContains('Ana pediu proposta por telefone', array_column($response->json('history'), 'body'));
    }

    public function test_editor_cannot_export_or_anonymize(): void
    {
        $this->actingAs($this->editor)->get(route('crm.leads.export', $this->lead))->assertForbidden();
        $this->actingAs($this->editor)->post(route('crm.leads.anonymize', $this->lead), ['confirm' => '1'])->assertForbidden();
        $this->assertNull($this->lead->fresh()->anonymized_at);
    }

    public function test_anonymize_removes_personal_data_and_history_text(): void
    {
        $this->actingAs($this->admin)->post(route('crm.leads.anonymize', $this->lead))->assertSessionHasErrors('confirm');

        $this->actingAs($this->admin)->post(route('crm.leads.anonymize', $this->lead), ['confirm' => '1'])->assertRedirect();

        $lead = $this->lead->fresh();
        $this->assertNotNull($lead->anonymized_at);
        $this->assertNull($lead->email);
        $this->assertNull($lead->phone);
        $this->assertSame('Anonimizado', $lead->name);
        $this->assertFalse($lead->activities()->where('body', 'like', '%Ana%')->exists());
        $this->actingAs($this->admin)->get(route('crm.leads.edit', $lead))->assertForbidden();
    }

    public function test_lost_leads_past_the_retention_period_are_anonymized(): void
    {
        app(LeadService::class)->moveTo($this->lead, 'perdido', null, 'Sem verba');
        $recent = app(LeadService::class)->findOrCreate($this->tenant->id, ['name' => 'Recente', 'email' => 'recente@ficticio.test'], 'manual');
        app(LeadService::class)->moveTo($recent, 'perdido', null, 'Sem verba');

        Lead::forTenant($this->tenant->id)->whereKey($this->lead->id)->update(['stage_changed_at' => now()->subMonths(25)]);

        $this->artisan('crm:anonymize-lost')->expectsOutputToContain('Leads anonimizados: 1')->assertSuccessful();

        $this->assertNotNull($this->lead->fresh()->anonymized_at);
        $this->assertNull($recent->fresh()->anonymized_at);
    }
}
