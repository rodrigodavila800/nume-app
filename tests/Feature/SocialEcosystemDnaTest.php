<?php

namespace Tests\Feature;

use App\Enums\PlataformaAlvo;
use App\Models\Cliente;
use App\Models\SocialProfile;
use App\Models\Tenant;
use App\Services\SofiCopilotChatService;
use App\Services\SofiCopywriterService;
use App\Services\UploadPost\SocialAccounts;
use App\Services\UploadPost\SocialDistributionService;
use App\Services\UploadPost\UploadPostClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DNA da Marca x Ecossistema de Redes (Upload-Post). Dados fictícios, sem rede real.
 */
class SocialEcosystemDnaTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $tenant = Tenant::create(['name' => 'Produtora Aurora', 'slug' => 'produtora-aurora', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->cliente = Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Ficticio',
            'email' => 'cliente@ficticio.test',
            'password' => bcrypt('secret123'),
            'company_name' => 'Clinica Ficticia',
            'brand_niche' => 'Saude',
        ]);
    }

    public function test_only_platforms_with_real_content_count_as_connected(): void
    {
        $accounts = SocialAccounts::connected([
            'instagram' => ['username' => 'clinica.ficticia', 'display_name' => 'Clinica Ficticia'],
            'tiktok' => ['handle' => '@clinica.tt'],
            'youtube' => 'Canal Ficticio',
            'facebook' => null,
            'linkedin' => '',
            'x' => ['connected' => false, 'username' => 'nao.conta'],
            'threads' => [],
        ]);

        $this->assertSame(['instagram', 'tiktok', 'youtube'], array_keys($accounts));
        $this->assertSame('clinica.ficticia', $accounts['instagram']['handle']);
        $this->assertSame('Clinica Ficticia', $accounts['instagram']['display_name']);
        $this->assertSame('@clinica.tt', $accounts['tiktok']['handle']);
        $this->assertSame('Canal Ficticio', $accounts['youtube']['handle']);
    }

    public function test_simulation_mode_never_stores_fake_handles(): void
    {
        $this->useUploadPost(apiKey: '');
        $this->existingProfile();

        $profile = app(SocialDistributionService::class)->syncConnectionStatus($this->cliente);

        // O modo simulação marca plataformas como conectadas para a demo, mas os @ dele são fictícios.
        $this->assertTrue($profile->connected);
        $this->assertNull($profile->account_details);
        $this->assertNull($this->cliente->fresh()->getEffectiveSocialHandle(PlataformaAlvo::INSTAGRAM));
    }

    public function test_real_sync_stores_public_details_and_ignores_null_platforms(): void
    {
        $this->useUploadPost(apiKey: 'chave-de-teste');
        $this->existingProfile();

        Http::fake(['api.upload-post.com/*' => Http::response(['profile' => [
            'username' => 'uuid-ficticio',
            'social_accounts' => [
                'instagram' => ['username' => 'clinica.ficticia'],
                'facebook' => null,
                'linkedin' => '',
            ],
        ]])]);

        $profile = app(SocialDistributionService::class)->syncConnectionStatus($this->cliente);

        $this->assertSame(['instagram'], $profile->platforms_connected);
        $this->assertSame('clinica.ficticia', $profile->account_details['instagram']['handle']);
        $this->assertFalse($profile->isPlatformConnected('facebook'));
    }

    public function test_webhook_does_not_count_null_platforms_as_connected(): void
    {
        $profile = $this->existingProfile();

        $this->postJson(route('upload-post.webhook'), [
            'user' => $profile->upload_post_username,
            'social_accounts' => [
                'tiktok' => ['handle' => '@clinica.tt'],
                'pinterest' => null,
            ],
        ])->assertOk();

        $profile->refresh();
        $this->assertSame(['tiktok'], $profile->platforms_connected);
        $this->assertSame('@clinica.tt', $profile->connectedHandle('tiktok'));
    }

    public function test_effective_handle_prefers_ecosystem_and_never_overwrites_the_dna(): void
    {
        $this->cliente->update(['instagram_handle' => '@digitado.no.dna', 'x_handle' => '@x.digitado']);
        $this->existingProfile(['instagram'], ['instagram' => ['handle' => '@conectado.oauth', 'display_name' => null, 'avatar' => null]]);

        $cliente = $this->cliente->fresh();
        $this->assertSame('@conectado.oauth', $cliente->getEffectiveSocialHandle(PlataformaAlvo::INSTAGRAM));
        $this->assertSame('@x.digitado', $cliente->getEffectiveSocialHandle(PlataformaAlvo::X));
        $this->assertSame('@digitado.no.dna', $cliente->instagram_handle);

        // Desconectada no ecossistema: detalhe antigo é ignorado e volta o valor do DNA.
        $cliente->socialProfile->update(['platforms_connected' => [], 'connected' => false]);
        $this->assertSame('@digitado.no.dna', $cliente->fresh()->getEffectiveSocialHandle(PlataformaAlvo::INSTAGRAM));
    }

    public function test_sofi_uses_the_ecosystem_handle_and_never_invents_one(): void
    {
        $copywriter = app(SofiCopywriterService::class);
        $copilot = app(SofiCopilotChatService::class);
        $describe = fn (Cliente $c) => (fn () => $this->describeSocialChannels($c))->call($copilot);

        // Sem nenhum canal: o chat não pode citar um @ inventado.
        $this->assertStringNotContainsString('@marca', $describe($this->cliente->fresh()));
        $this->assertStringContainsString('Nenhum informado', $describe($this->cliente->fresh()));

        // Conectado via ecossistema, sem nada digitado no DNA.
        $this->existingProfile(['instagram'], ['instagram' => ['handle' => 'clinica.ficticia', 'display_name' => null, 'avatar' => null]]);
        $cliente = $this->cliente->fresh();

        $this->assertStringContainsString('clinica.ficticia', $describe($cliente));
        $result = $copywriter->generate('Implantes sem dor', null, null, 'pattern_interrupt', 'persuasive', 'pt_BR', $cliente);
        $this->assertStringContainsString('@clinica.ficticia', json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    public function test_dna_form_saves_the_four_new_networks_and_shows_all_eight(): void
    {
        $this->existingProfile(['instagram'], ['instagram' => ['handle' => '@conectado.oauth', 'display_name' => null, 'avatar' => null]]);

        $this->actingAs($this->cliente, 'cliente')->put(route('portal.profile.update'), [
            'name' => 'Cliente Ficticio',
            'brand_niche' => 'Saude',
            'facebook_url' => 'facebook.com/clinicaficticia',
            'x_handle' => '@clinicaficticia',
            'threads_handle' => '@clinica.threads',
            'pinterest_url' => 'pinterest.com/clinicaficticia',
        ])->assertRedirect();

        $cliente = $this->cliente->fresh();
        $this->assertSame('@clinicaficticia', $cliente->x_handle);
        $this->assertSame('pinterest.com/clinicaficticia', $cliente->pinterest_url);

        $response = $this->actingAs($cliente, 'cliente')->get(route('portal.profile.edit'))->assertOk();
        foreach (PlataformaAlvo::cases() as $plataforma) {
            $response->assertSee('name="'.$plataforma->clienteField().'"', false);
        }
        $response->assertSee('Conectado via Ecossistema')
            ->assertSee('@conectado.oauth')
            ->assertSee('data-social-fill="instagram_handle"', false);
    }

    private function useUploadPost(string $apiKey): void
    {
        config(['upload_post.api_key' => $apiKey, 'upload_post.base_url' => 'https://api.upload-post.com/api']);
        $this->app->forgetInstance(UploadPostClient::class);
        $this->app->forgetInstance(SocialDistributionService::class);
    }

    private function existingProfile(array $platforms = [], ?array $details = null): SocialProfile
    {
        return SocialProfile::create([
            'tenant_id' => $this->cliente->tenant_id,
            'cliente_id' => $this->cliente->id,
            'upload_post_username' => (string) Str::uuid(),
            'connected' => $platforms !== [],
            'platforms_connected' => $platforms,
            'account_details' => $details,
        ]);
    }
}
