<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalQuoteListTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Cliente $clienteA;

    private Cliente $clienteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Nexus Filmes',
            'slug' => 'nexus-filmes',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->clienteA = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Alpha S.L.',
            'email' => 'contato@alpha.es',
            'password' => bcrypt('secret123'),
        ]);

        $this->clienteB = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Beta S.L.',
            'email' => 'contato@beta.es',
            'password' => bcrypt('secret123'),
        ]);
    }

    public function test_guest_client_is_redirected_to_portal_login(): void
    {
        $response = $this->get(route('portal.quotes.index'));
        $response->assertRedirect(route('portal.login'));
    }

    public function test_authenticated_client_can_view_own_quotes_list(): void
    {
        $quoteDirect = Quote::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->clienteA->id,
            'number' => 'ORC-ALPHA-01',
            'project_title' => 'Campanha Institucional Alpha',
            'client_name' => 'Cliente Alpha S.L.',
            'client_email' => 'contato@alpha.es',
            'status' => 'sent',
            'valid_until' => now()->addDays(10),
            'approval_token' => Str::random(48),
            'approval_token_expires_at' => now()->addDays(10),
            'subtotal' => 2000.00,
            'tax_rate' => 21.00,
            'tax_amount' => 420.00,
            'total_amount' => 2420.00,
            'inputs' => ['videos' => 2],
            'calculation' => ['total' => 2420.00],
        ]);

        // Orçamento criado sem cliente_id, mas com o mesmo client_email
        $quoteByEmail = Quote::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => null,
            'number' => 'ORC-ALPHA-02',
            'project_title' => 'Vídeos Promocionais Alpha',
            'client_name' => 'Cliente Alpha S.L.',
            'client_email' => 'contato@alpha.es',
            'status' => 'sent',
            'valid_until' => now()->addDays(12),
            'approval_token' => Str::random(48),
            'approval_token_expires_at' => now()->addDays(12),
            'subtotal' => 1500.00,
            'tax_rate' => 21.00,
            'tax_amount' => 315.00,
            'total_amount' => 1815.00,
            'inputs' => ['videos' => 1],
            'calculation' => ['total' => 1815.00],
        ]);

        $response = $this->actingAs($this->clienteA, 'cliente')
            ->get(route('portal.quotes.index'));

        $response->assertOk();
        $response->assertViewIs('portal.quotes.index');
        $response->assertSee('ORC-ALPHA-01');
        $response->assertSee('Campanha Institucional Alpha');
        $response->assertSee('€ 2.420,00');
        $response->assertSee('ORC-ALPHA-02');
        $response->assertSee('Vídeos Promocionais Alpha');
        $response->assertSee('€ 1.815,00');
    }

    public function test_client_cannot_see_quotes_of_another_client_in_same_tenant(): void
    {
        $quoteA = Quote::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->clienteA->id,
            'number' => 'ORC-ALPHA-99',
            'project_title' => 'Projeto Exclusivo Alpha',
            'client_name' => 'Cliente Alpha S.L.',
            'client_email' => 'contato@alpha.es',
            'status' => 'sent',
            'valid_until' => now()->addDays(10),
            'approval_token' => Str::random(48),
            'inputs' => ['videos' => 1],
            'calculation' => ['total' => 1000.00],
        ]);

        $quoteB = Quote::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->clienteB->id,
            'number' => 'ORC-BETA-77',
            'project_title' => 'Projeto Secreto Beta',
            'client_name' => 'Cliente Beta S.L.',
            'client_email' => 'contato@beta.es',
            'status' => 'sent',
            'valid_until' => now()->addDays(10),
            'approval_token' => Str::random(48),
            'inputs' => ['videos' => 1],
            'calculation' => ['total' => 5000.00],
        ]);

        $response = $this->actingAs($this->clienteA, 'cliente')
            ->get(route('portal.quotes.index'));

        $response->assertOk();
        $response->assertSee('ORC-ALPHA-99');
        $response->assertSee('Projeto Exclusivo Alpha');
        $response->assertDontSee('ORC-BETA-77');
        $response->assertDontSee('Projeto Secreto Beta');
    }

    public function test_draft_quotes_never_appear_for_client(): void
    {
        $draftQuote = Quote::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->clienteA->id,
            'number' => 'ORC-DRAFT-00',
            'project_title' => 'Rascunho Interno da Produtora',
            'client_name' => 'Cliente Alpha S.L.',
            'client_email' => 'contato@alpha.es',
            'status' => 'draft',
            'valid_until' => now()->addDays(10),
            'subtotal' => 1000.00,
            'total_amount' => 1210.00,
            'inputs' => ['videos' => 1],
            'calculation' => ['total' => 1210.00],
        ]);

        $response = $this->actingAs($this->clienteA, 'cliente')
            ->get(route('portal.quotes.index'));

        $response->assertOk();
        $response->assertDontSee('ORC-DRAFT-00');
        $response->assertDontSee('Rascunho Interno da Produtora');
    }

    public function test_approval_link_present_when_status_is_sent_and_not_expired(): void
    {
        $token = 'alpha_token_aprovacao_1234567890abcdef';

        $quote = Quote::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->clienteA->id,
            'number' => 'ORC-ALPHA-LINK',
            'project_title' => 'Campanha com Link de Aprovacao',
            'client_name' => 'Cliente Alpha S.L.',
            'client_email' => 'contato@alpha.es',
            'status' => 'sent',
            'valid_until' => now()->addDays(7),
            'approval_token' => $token,
            'approval_token_expires_at' => now()->addDays(7),
            'subtotal' => 3000.00,
            'total_amount' => 3630.00,
            'inputs' => ['videos' => 2],
            'calculation' => ['total' => 3630.00],
        ]);

        $response = $this->actingAs($this->clienteA, 'cliente')
            ->get(route('portal.quotes.index'));

        $response->assertOk();
        $response->assertSee('Aguardando Aprovação');
        $response->assertSee(route('quotes.public.show', $token));
        $response->assertSee('Revisar &amp; Aprovar Proposta', false);
    }

    public function test_production_link_present_when_status_is_accepted_with_production_id(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->clienteA->id,
            'created_by' => $user->id,
            'title' => 'Produção #1 - Campanha Alpha Aceita',
            'status' => 'in_production',
        ]);

        $quote = Quote::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->clienteA->id,
            'production_id' => $production->id,
            'number' => 'ORC-ALPHA-ACCEPTED',
            'project_title' => 'Campanha Alpha Aceita',
            'client_name' => 'Cliente Alpha S.L.',
            'client_email' => 'contato@alpha.es',
            'status' => 'accepted',
            'accepted_at' => now()->subDay(),
            'valid_until' => now()->addDays(7),
            'approval_token' => Str::random(48),
            'subtotal' => 4000.00,
            'total_amount' => 4840.00,
            'inputs' => ['videos' => 3],
            'calculation' => ['total' => 4840.00],
        ]);

        $response = $this->actingAs($this->clienteA, 'cliente')
            ->get(route('portal.quotes.index'));

        $response->assertOk();
        $response->assertSee('Aprovado');
        $response->assertSee(route('portal.productions.show', $production->id));
        $response->assertSee('Acompanhar Produção');
    }

    public function test_recent_quotes_appear_on_portal_dashboard(): void
    {
        $token = 'dashboard_token_xyz987654321';

        $quote = Quote::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->clienteA->id,
            'number' => 'ORC-DASH-01',
            'project_title' => 'Orçamento Visível no Dashboard',
            'client_name' => 'Cliente Alpha S.L.',
            'client_email' => 'contato@alpha.es',
            'status' => 'sent',
            'valid_until' => now()->addDays(5),
            'approval_token' => $token,
            'approval_token_expires_at' => now()->addDays(5),
            'subtotal' => 2000.00,
            'total_amount' => 2420.00,
            'inputs' => ['videos' => 2],
            'calculation' => ['total' => 2420.00],
        ]);

        $response = $this->actingAs($this->clienteA, 'cliente')
            ->get(route('portal.dashboard'));

        $response->assertOk();
        $response->assertSee('Propostas &amp; Orçamentos Recebidos', false);
        $response->assertSee('ORC-DASH-01');
        $response->assertSee('Orçamento Visível no Dashboard');
        $response->assertSee(route('quotes.public.show', $token));
        $response->assertSee(route('portal.quotes.index'));
    }
}
