<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRM: funil, ficha, histórico e isolamento por produtora. Dados fictícios.
 */
class CrmLeadsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->editor = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Editora', 'email' => 'editora@ficticio.test', 'password' => 'secret12345', 'role' => 'editor']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Ficticia', 'company' => 'Padaria Ficticia', 'email' => 'ANA@ficticio.test', 'source' => 'referral',
            'estimated_value' => 1500, 'legal_basis' => 'consent', 'consent_given' => '1',
        ], $overrides);
    }

    public function test_team_creates_a_lead_and_it_shows_in_the_new_column(): void
    {
        $this->actingAs($this->editor)->post(route('crm.leads.store'), $this->payload())->assertRedirect();

        $lead = Lead::forTenant($this->tenant->id)->sole();
        $this->assertSame('novo', $lead->stage);
        $this->assertSame('ana@ficticio.test', $lead->email);
        $this->assertNotNull($lead->consent_at, 'consentimento registra data e hora');
        $this->assertSame(1, $lead->activities()->count());

        $this->actingAs($this->editor)->get(route('crm.leads.index'))->assertOk()->assertSee('Padaria Ficticia')->assertSee('Novo');
    }

    public function test_moving_stage_and_logging_contacts_build_the_history(): void
    {
        $lead = app(LeadService::class)->findOrCreate($this->tenant->id, ['name' => 'Ana', 'email' => 'ana@ficticio.test'], 'manual');

        $this->actingAs($this->editor)->post(route('crm.leads.stage', $lead), ['stage' => 'contato'])->assertRedirect();
        $this->actingAs($this->editor)->post(route('crm.leads.activity', $lead), ['type' => 'call', 'body' => 'Ligou pedindo portfólio'])->assertRedirect();

        $lead->refresh();
        $this->assertSame('contato', $lead->stage);
        $this->assertNotNull($lead->last_contact_at, 'ligação conta como contato');
        $this->assertSame(['call', 'stage_change', 'system'], $lead->activities()->pluck('type')->all());

        $this->actingAs($this->editor)->post(route('crm.leads.stage', $lead), ['stage' => 'perdido'])->assertSessionHasErrors('lost_reason');
        $this->actingAs($this->editor)->post(route('crm.leads.stage', $lead), ['stage' => 'perdido', 'lost_reason' => 'Sem verba'])->assertRedirect();
        $this->assertSame('Sem verba', $lead->fresh()->lost_reason);

        $this->actingAs($this->editor)->get(route('crm.leads.show', $lead))->assertOk()->assertSee('Ligou pedindo portfólio')->assertSee('Em contato → Perdido · Sem verba');
    }

    public function test_lead_without_contact_for_a_week_is_flagged(): void
    {
        $lead = app(LeadService::class)->findOrCreate($this->tenant->id, ['name' => 'Ana', 'email' => 'ana@ficticio.test'], 'manual');
        Lead::forTenant($this->tenant->id)->whereKey($lead->id)->update(['created_at' => now()->subDays(8)]);

        $this->assertTrue($lead->fresh()->isStale());
        $this->actingAs($this->editor)->get(route('crm.leads.index'))->assertSee('Sem contato há mais de 7 dias');
    }

    public function test_find_or_create_does_not_duplicate_by_email(): void
    {
        $service = app(LeadService::class);
        $first = $service->findOrCreate($this->tenant->id, ['name' => 'Ana', 'email' => 'Ana@Ficticio.test'], 'storefront');
        $second = $service->findOrCreate($this->tenant->id, ['name' => 'Ana', 'email' => 'ana@ficticio.test'], 'quote_request');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('storefront', $second->source, 'mantém a origem do primeiro contato');
    }

    public function test_leads_are_isolated_by_producer_and_viewer_has_no_access(): void
    {
        $outra = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $alheio = app(LeadService::class)->findOrCreate($outra->id, ['name' => 'Concorrente', 'email' => 'x@ficticio.test'], 'manual');
        $viewer = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Visitante', 'email' => 'viewer@ficticio.test', 'password' => 'secret12345', 'role' => 'viewer']);

        $this->actingAs($this->editor)->get(route('crm.leads.show', $alheio))->assertNotFound();
        $this->actingAs($this->editor)->post(route('crm.leads.stage', $alheio), ['stage' => 'ganho'])->assertNotFound();
        $this->actingAs($viewer)->get(route('crm.leads.index'))->assertForbidden();
        $this->assertSame('novo', $alheio->fresh()->stage);
    }

    public function test_owner_must_be_from_the_same_producer(): void
    {
        $outra = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $estranho = User::create(['tenant_id' => $outra->id, 'name' => 'Estranho', 'email' => 'estranho@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);

        $this->actingAs($this->editor)->post(route('crm.leads.store'), $this->payload(['owner_id' => $estranho->id]))->assertSessionHasErrors('owner_id');
    }

    public function test_views_have_no_inline_css(): void
    {
        foreach (['index', 'form', 'show', '_privacy'] as $view) {
            $this->assertStringNotContainsString('style="', file_get_contents(resource_path("views/crm/{$view}.blade.php")), $view);
        }
    }
}
