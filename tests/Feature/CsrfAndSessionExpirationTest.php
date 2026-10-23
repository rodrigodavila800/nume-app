<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CsrfAndSessionExpirationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Teste Zaragoza',
            'slug' => 'produtora-teste-zaragoza',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        // Rota de teste que reproduz fielmente a expiração de sessão / CSRF
        Route::post('/test-session-expired', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        Route::post('/portal/test-session-expired', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });
    }

    public function test_web_post_on_expired_session_redirects_back_with_friendly_warning(): void
    {
        $response = $this->actingAs($this->user)
            ->from('/admin/invoices')
            ->post('/test-session-expired', [
                'sample_data' => 'valor_enviado',
            ]);

        // Deve interceptar a exceção 419 e redirecionar com aviso amigável
        $response->assertStatus(302);
        $response->assertRedirect('/admin/invoices');
        $response->assertSessionHas('warning', 'Sua sessão expirou por inatividade. O formulário foi atualizado, por favor tente novamente.');
    }

    public function test_portal_web_post_on_expired_session_redirects_to_portal_fallback(): void
    {
        // Simulando POST vindo de rota sem referer prévio no portal
        $response = $this->post('/portal/test-session-expired');

        $response->assertStatus(302);
        $response->assertSessionHas('warning');
    }

    public function test_json_post_on_expired_session_returns_friendly_419_json(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/test-session-expired', [
                'sample_data' => 'teste',
            ]);

        $response->assertStatus(419);
        $response->assertJsonStructure([
            'message',
            'csrf_token',
        ]);
        $response->assertJsonFragment([
            'message' => 'Sua sessão expirou por inatividade. Por favor, atualize a página e tente novamente.',
        ]);
    }

    public function test_custom_419_error_view_renders_successfully(): void
    {
        $view = $this->view('errors.419');

        $view->assertSee('419');
        $view->assertSee('Sessão Expirada');
        $view->assertDontSee('style='); // Garantia da Regra de Ouro: zero CSS inline
    }
}
