<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiBrandAnalystAgent;
use App\Ai\AiBudget;
use App\Ai\SofiProvider;
use App\Models\AiUsageLog;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiUsageSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $editor;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora IA Teste',
            'slug' => 'produtora-ia-teste',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin IA',
            'email' => 'admin@ia.com',
            'password' => bcrypt('Senha@Segura123'),
            'role' => 'admin',
        ]);

        $this->editor = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Editor IA',
            'email' => 'editor@ia.com',
            'password' => bcrypt('Senha@Segura123'),
            'role' => 'editor',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Marca Cliente IA',
            'email' => 'marca@ia.com',
            'password' => bcrypt('Senha@Segura123'),
            'company_name' => 'Marca IA Ltda',
        ]);
    }

    public function test_admin_views_usage_by_function_cliente_and_month_with_80_percent_warning(): void
    {
        app(AiBudget::class)->setLimits($this->tenant->id, 1000, 500);

        AiUsageLog::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'agent' => 'sofi_brand_analyst',
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'input_tokens' => 500,
            'output_tokens' => 350,
        ]);

        $otherTenant = Tenant::create(['name' => 'Outra Produtora', 'slug' => 'outra-ia', 'is_active' => true]);
        $otherCliente = Cliente::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Cliente Secreto Outra',
            'email' => 'secreto@outra.com',
            'password' => bcrypt('Senha@Segura123'),
        ]);
        AiUsageLog::create([
            'tenant_id' => $otherTenant->id,
            'cliente_id' => $otherCliente->id,
            'agent' => 'sofi_script_blocks',
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'input_tokens' => 9000,
            'output_tokens' => 1000,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('ai_usage.index'));

        $response->assertOk();
        $response->assertSee('DNA da marca');
        $response->assertSee('Marca Cliente IA');
        $response->assertSee('85%');
        $response->assertSee('No limite');
        $response->assertDontSee('Cliente Secreto Outra');
        $this->assertStringNotContainsString('style="', $response->getContent());
    }

    public function test_admin_updates_limits_in_ui_and_provider_respects_them(): void
    {
        config([
            'sofi.provider' => 'gemini',
            'sofi.keys.gemini' => 'global-gemini-key',
        ]);

        $this->actingAs($this->admin)
            ->post(route('ai_usage.limits.update'), [
                'monthly_token_limit' => 2000,
                'monthly_cliente_token_limit' => 800,
            ])
            ->assertRedirect(route('ai_usage.index'));

        $this->assertSame('2000', TenantSetting::get($this->tenant->id, AiBudget::TENANT_LIMIT_KEY));
        $this->assertSame('800', TenantSetting::get($this->tenant->id, AiBudget::CLIENTE_LIMIT_KEY));

        AiUsageLog::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'agent' => 'sofi_support_chat',
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'input_tokens' => 600,
            'output_tokens' => 250,
        ]);

        $provider = app(SofiProvider::class);
        $this->assertNull($provider->options($this->tenant, $this->cliente->id));
        $this->assertNotNull($provider->options($this->tenant));
    }

    public function test_recording_usage_marks_80_percent_warning_once_per_month(): void
    {
        app(AiBudget::class)->setLimits($this->tenant->id, 100, null);

        SofiBrandAnalystAgent::fake([[
            'positioning' => 'P',
            'value_proposition' => 'V',
            'target_audience' => 'A',
            'tone_of_voice' => 'T',
            'content_pillars' => ['P1', 'P2', 'P3'],
            'recommended_hooks' => ['H1', 'H2', 'H3'],
            'keywords' => ['k1', 'k2', 'k3', 'k4', 'k5'],
            'opportunities' => 'O',
            'risks_and_gaps' => 'R',
        ]]);

        AiUsageLog::create([
            'tenant_id' => $this->tenant->id,
            'agent' => 'sofi_script',
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'input_tokens' => 50,
            'output_tokens' => 30,
        ]);

        (new SofiBrandAnalystAgent($this->tenant->id, $this->cliente->id))->prompt('Analise');

        $this->assertSame(
            now()->format('Y-m'),
            TenantSetting::get($this->tenant->id, AiBudget::WARNED_MONTH_KEY)
        );
    }

    public function test_editor_cannot_view_or_update_ai_usage_settings(): void
    {
        $this->actingAs($this->editor)
            ->get(route('ai_usage.index'))
            ->assertForbidden();

        $this->actingAs($this->editor)
            ->post(route('ai_usage.limits.update'), ['monthly_token_limit' => 5000])
            ->assertForbidden();
    }
}
