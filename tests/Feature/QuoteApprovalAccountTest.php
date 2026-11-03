<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\PackPurchase;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Conta obrigatória para pagar a proposta pública. Dados fictícios; pagamento por transferência (sem gateway).
 */
class QuoteApprovalAccountTest extends TestCase
{
    use RefreshDatabase;

    private const BILLING = ['payment_method' => 'bank_transfer', 'billing_type' => 'particular', 'billing_legal_name' => 'Ana Ficticia', 'billing_country' => 'ES'];

    private Tenant $tenant;

    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        TenantSetting::set($this->tenant, 'bank_transfer_iban', 'ES9121000418450200051332');
        TenantSetting::set($this->tenant, 'bank_transfer_enabled', '1');

        $this->quote = Quote::create([
            'tenant_id' => $this->tenant->id, 'status' => 'sent', 'client_name' => 'Padaria Ficticia', 'client_contact' => 'Ana', 'client_email' => 'ana@ficticio.test',
            'inputs' => ['videos' => 2], 'calculation' => [], 'subtotal' => 1000, 'tax_rate' => 21, 'tax_amount' => 210, 'total_amount' => 1210,
            'deposit_amount' => 605, 'balance_amount' => 605, 'valid_until' => now()->addDays(5),
            'approval_token' => str_repeat('c', 48), 'approval_token_expires_at' => now()->addDays(5),
        ]);
    }

    private function approve(array $account = []): TestResponse
    {
        return $this->post(route('quotes.public.approve', $this->quote->approval_token), self::BILLING + $account);
    }

    private function existingCliente(string $email = 'ana@ficticio.test'): Cliente
    {
        return Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => $email, 'password' => 'senha-da-ana-1']);
    }

    public function test_a_new_email_must_create_an_account_before_paying(): void
    {
        $this->approve()->assertSessionHasErrors(['account_name', 'password']);
        $this->approve(['account_name' => 'Ana', 'password' => 'curta', 'password_confirmation' => 'curta'])->assertSessionHasErrors('password');
        $this->approve(['account_name' => 'Ana', 'password' => 'senha-nova-123', 'password_confirmation' => 'outra-senha-1'])->assertSessionHasErrors('password');

        $this->assertSame(0, Cliente::withoutGlobalScopes()->count());
        $this->assertSame(0, PackPurchase::withoutGlobalScopes()->count());
        $this->assertGuest('cliente');
    }

    public function test_creating_the_account_logs_the_client_in_and_links_the_quote(): void
    {
        $this->approve(['account_name' => 'Ana Responsável', 'password' => 'senha-nova-123', 'password_confirmation' => 'senha-nova-123'])->assertRedirect();

        $cliente = Cliente::withoutGlobalScopes()->where('email', 'ana@ficticio.test')->sole();
        $this->assertSame('Ana Responsável', $cliente->name);
        $this->assertSame('Padaria Ficticia', $cliente->company_name);
        $this->assertTrue(Hash::check('senha-nova-123', $cliente->password), 'a senha é a escolhida pelo cliente');
        $this->assertAuthenticatedAs($cliente, 'cliente');
        $this->assertSame($cliente->id, $this->quote->fresh()->cliente_id);
        $this->assertSame($cliente->id, PackPurchase::withoutGlobalScopes()->sole()->cliente_id);
    }

    public function test_an_existing_account_requires_its_own_password(): void
    {
        $cliente = $this->existingCliente();

        $this->approve(['password' => 'senha-errada-1'])->assertSessionHas('error');
        $this->assertGuest('cliente');
        $this->assertSame(0, PackPurchase::withoutGlobalScopes()->count());

        $this->approve(['password' => 'senha-da-ana-1'])->assertRedirect();
        $this->assertAuthenticatedAs($cliente, 'cliente');
        $this->assertSame(1, PackPurchase::withoutGlobalScopes()->where('cliente_id', $cliente->id)->count());
        $this->assertSame(1, Cliente::withoutGlobalScopes()->count(), 'não cria conta duplicada');
    }

    public function test_the_right_logged_in_client_pays_without_typing_the_password_again(): void
    {
        $cliente = $this->existingCliente();

        $this->actingAs($cliente, 'cliente')->approve()->assertRedirect();

        $this->assertSame(1, PackPurchase::withoutGlobalScopes()->where('cliente_id', $cliente->id)->count());
    }

    public function test_being_logged_in_as_someone_else_never_pays_with_the_wrong_account(): void
    {
        $owner = $this->existingCliente();
        $intruder = $this->existingCliente('outra@ficticio.test');

        $this->actingAs($intruder, 'cliente')->approve()->assertSessionHasErrors('password');
        $this->assertSame(0, PackPurchase::withoutGlobalScopes()->count());

        $this->approve(['password' => 'senha-da-ana-1'])->assertRedirect();
        $this->assertAuthenticatedAs($owner, 'cliente');
        $this->assertSame($owner->id, PackPurchase::withoutGlobalScopes()->sole()->cliente_id);
    }

    public function test_the_page_shows_the_right_account_step(): void
    {
        $page = fn () => $this->get(route('quotes.public.show', $this->quote->approval_token))->assertOk();

        $page()->assertSee('Crea tu cuenta')->assertSee('name="password_confirmation"', false);

        $cliente = $this->existingCliente();
        $page()->assertSee('Ya tienes cuenta')->assertDontSee('name="password_confirmation"', false);

        $this->actingAs($cliente, 'cliente');
        $page()->assertSee('Conectado como')->assertDontSee('name="password"', false);
    }

    public function test_password_attempts_are_rate_limited(): void
    {
        $this->existingCliente();

        foreach (range(1, 10) as $attempt) {
            $this->approve(['password' => "errada-{$attempt}"]);
        }

        $this->approve(['password' => 'senha-da-ana-1'])->assertStatus(429);
        $this->assertSame(0, PackPurchase::withoutGlobalScopes()->count());
    }
}
