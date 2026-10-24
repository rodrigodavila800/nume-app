<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalRegisterValidationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::firstOrCreate(
            ['slug' => 'nume'],
            [
                'name' => 'NUME Creative',
                'status' => 'active',
                'primary_contact_email' => 'contato@numecreative.com',
                'subscription_plan' => 'enterprise',
            ]
        );
    }

    public function test_password_symbols_validation_error_is_translated_in_spanish(): void
    {
        $response = $this->withSession(['locale' => 'es'])
            ->from(route('portal.register'))
            ->post(route('portal.register'), [
                'name' => 'Cliente Madrid',
                'email' => 'cliente.madrid@example.es',
                'password' => 'Password123', // Falta símbolo
                'password_confirmation' => 'Password123',
                'terms' => '1',
            ]);

        $response->assertSessionHasErrors('password');
        $response->assertRedirect(route('portal.register'));

        // Garante que a chave crua 'validation.password.symbols' NÃO seja exibida
        $errors = session('errors')->get('password');
        $this->assertNotEmpty($errors);
        $this->assertStringNotContainsString('validation.password', $errors[0]);
        $this->assertStringContainsString('símbolo', $errors[0]);

        $followResponse = $this->withSession(['locale' => 'es'])->get(route('portal.register'));
        $followResponse->assertOk();
        $followResponse->assertDontSee('validation.password.symbols');
        $followResponse->assertSee('símbolo');
    }

    public function test_password_symbols_validation_error_is_translated_in_portuguese(): void
    {
        $response = $this->withSession(['locale' => 'pt_BR'])
            ->from(route('portal.register'))
            ->post(route('portal.register'), [
                'name' => 'Cliente Brasil',
                'email' => 'cliente.brasil@example.com',
                'password' => 'Password123', // Falta símbolo
                'password_confirmation' => 'Password123',
                'terms' => '1',
            ]);

        $response->assertSessionHasErrors('password');

        $errors = session('errors')->get('password');
        $this->assertNotEmpty($errors);
        $this->assertStringNotContainsString('validation.password', $errors[0]);
        $this->assertStringContainsString('símbolo', $errors[0]);

        $followResponse = $this->withSession(['locale' => 'pt_BR'])->get(route('portal.register'));
        $followResponse->assertOk();
        $followResponse->assertDontSee('validation.password.symbols');
        $followResponse->assertSee('símbolo');
    }

    public function test_client_registers_successfully_with_valid_secure_password(): void
    {
        $response = $this->withSession(['locale' => 'es'])
            ->post(route('portal.register'), [
                'name' => 'Cliente Valido',
                'email' => 'cliente.valido@example.es',
                'password' => 'Password123!', // Contém maiúscula, minúscula, número e símbolo
                'password_confirmation' => 'Password123!',
                'terms' => '1',
            ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticated('cliente');
        $this->assertDatabaseHas('clientes', [
            'tenant_id' => $this->tenant->id,
            'email' => 'cliente.valido@example.es',
        ]);
    }
}
