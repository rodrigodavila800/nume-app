<?php

namespace Tests\Feature;

use App\Ai\Tools\CreateQuoteFromPackTool;
use App\Jobs\SendQuoteProposalDocuments;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Proposta feita antes da conta (Sofi no WhatsApp) passa para a conta criada depois com o mesmo e-mail.
 * Dados fictícios.
 */
class QuoteOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Pack $pack;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendQuoteProposalDocuments::class]);
        $this->tenant = Tenant::create(['name' => 'NUME', 'slug' => 'nume', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->pack = Pack::create(['tenant_id' => $this->tenant->id, 'name' => 'Reels Starter', 'slug' => 'reels-starter', 'price' => 500, 'vat_rate' => 21, 'video_quantity' => 4, 'status' => 'active']);
    }

    private function sofiQuote(string $email = 'ana@ficticio.test'): Quote
    {
        (new CreateQuoteFromPackTool($this->tenant->id, null, '34600000001'))->handle(new Request([
            'pack_id' => $this->pack->id, 'client_email' => $email, 'responsible_name' => 'Ana', 'company_name' => 'Padaria Ficticia',
        ]));

        return Quote::forTenant($this->tenant->id)->latest('id')->first();
    }

    public function test_registering_in_the_portal_later_brings_the_proposal_to_the_new_account(): void
    {
        $quote = $this->sofiQuote();
        $this->assertNull($quote->cliente_id);

        $this->post(route('portal.register'), [
            'name' => 'Ana', 'email' => 'Ana@Ficticio.test', 'company_name' => 'Padaria Ficticia',
            'password' => 'Senha-Forte-2026!', 'password_confirmation' => 'Senha-Forte-2026!', 'terms' => '1',
        ])->assertRedirect(route('portal.quotes.index'));

        $cliente = Cliente::withoutGlobalScopes()->where('email', 'ana@ficticio.test')->sole();
        $this->assertSame($cliente->id, $quote->fresh()->cliente_id);

        $this->get(route('portal.quotes.index'))->assertOk()->assertSee($quote->number);
    }

    public function test_only_open_proposals_of_the_same_tenant_change_owner(): void
    {
        $open = $this->sofiQuote();
        $accepted = Quote::create(['tenant_id' => $this->tenant->id, 'status' => 'accepted', 'client_name' => 'X', 'client_email' => 'ana@ficticio.test', 'inputs' => [], 'calculation' => []]);
        $other = Tenant::create(['name' => 'Produtora Beta', 'slug' => 'produtora-beta']);
        $foreign = Quote::create(['tenant_id' => $other->id, 'status' => 'sent', 'client_name' => 'X', 'client_email' => 'ana@ficticio.test', 'inputs' => [], 'calculation' => []]);

        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);

        $this->assertSame($cliente->id, $open->fresh()->cliente_id);
        $this->assertNull($accepted->fresh()->cliente_id);
        $this->assertNull($foreign->fresh()->cliente_id);
    }

    public function test_the_sofi_links_the_proposal_to_an_existing_account_found_by_email(): void
    {
        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);

        $quote = $this->sofiQuote('ANA@ficticio.test');

        $this->assertSame($cliente->id, $quote->cliente_id);
    }
}
