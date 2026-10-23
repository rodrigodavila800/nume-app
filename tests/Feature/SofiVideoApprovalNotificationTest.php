<?php

namespace Tests\Feature;

use App\Mail\VideoReadyForApprovalMail;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Services\Notification\SofiVideoNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SofiVideoApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Cliente $cliente;
    private User $adminUser;
    private Production $production;
    private Video $video;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Zaragoza Audiovisual',
            'slug' => 'nume-zaragoza',
            'status' => 'active',
            'social_whatsapp' => '+34 976 111 222',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Fernando Morales',
            'email' => 'fernando@moralesdental.es',
            'phone' => '+34 600 123 456',
            'password' => bcrypt('password123'),
            'brand_niche' => 'Implantologia Oral',
            'website' => 'https://moralesdental.es',
        ]);

        $this->production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Campanha Implantes Zaragoza 2026',
            'status' => 'in_production',
        ]);

        $this->video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'title' => 'Corte Final - Padrão de Interrupção',
            'video_url' => 'https://vimeo.com/987654321',
            'duration_seconds' => 52,
            'status' => 'processing',
        ]);
    }

    public function test_magic_link_authenticates_client_and_redirects_to_production_show(): void
    {
        $magicUrl = $this->video->getMagicApprovalUrl(14);
        $this->assertStringContainsString('/m/videos/' . $this->video->id, $magicUrl);
        $this->assertStringContainsString('signature=', $magicUrl);

        // Cliente não está logado inicialmente
        $this->assertFalse(Auth::guard('cliente')->check());

        // Acessa o Magic Link assinado
        $response = $this->get($magicUrl);

        // Deve redirecionar para a página da produção no portal do cliente
        $response->assertRedirect(route('portal.productions.show', $this->production->uuid));

        // Deve ter efetuado o login automático do cliente no guard 'cliente'
        $this->assertTrue(Auth::guard('cliente')->check());
        $this->assertEquals($this->cliente->id, Auth::guard('cliente')->id());
    }

    public function test_tampered_magic_link_is_rejected_by_hmac_signature(): void
    {
        $magicUrl = $this->video->getMagicApprovalUrl(14);
        $tamperedUrl = $magicUrl . 'tampered';

        $response = $this->get($tamperedUrl);

        $response->assertForbidden();
        $this->assertFalse(Auth::guard('cliente')->check());
    }

    public function test_sofi_service_sends_video_ready_email_with_magic_link(): void
    {
        Mail::fake();

        $service = app(SofiVideoNotificationService::class);
        $sent = $service->sendVideoReadyEmail($this->video);

        $this->assertTrue($sent);

        Mail::assertSent(VideoReadyForApprovalMail::class, function (VideoReadyForApprovalMail $mail) {
            $hasCorrectRecipient = $mail->hasTo($this->cliente->email);
            $hasVideo = $mail->video->id === $this->video->id;
            $hasMagicUrl = ! empty($mail->magicUrl) && str_contains($mail->magicUrl, '/m/videos/');

            return $hasCorrectRecipient && $hasVideo && $hasMagicUrl;
        });
    }

    public function test_staff_can_trigger_email_notification_from_controller(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->adminUser)
            ->post(route('videos.notify-cliente', [
                'production' => $this->production,
                'video' => $this->video,
            ]), [
                'channel' => 'email',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(VideoReadyForApprovalMail::class, function (VideoReadyForApprovalMail $mail) {
            return $mail->hasTo($this->cliente->email);
        });
    }

    public function test_sofi_generates_whatsapp_message_and_url_with_escrow_context(): void
    {
        $pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pacote 50/50 Especial',
            'slug' => 'pacote-50-50-especial',
            'price' => 800.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'billing_mode' => 'split_50_50',
            'deposit_percentage' => 50,
            'video_quantity' => 1,
            'status' => 'active',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $pack->id,
            'cliente_id' => $this->cliente->id,
            'amount' => 800.00,
            'billing_mode' => 'split_50_50',
            'deposit_amount' => 400.00,
            'deposit_paid_at' => now(),
            'balance_amount' => 400.00,
            'balance_status' => 'pending',
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'video_quantity' => 1,
            'remaining_videos' => 1,
        ]);

        $this->production->update(['pack_purchase_id' => $purchase->id]);

        $service = app(SofiVideoNotificationService::class);
        $message = $service->getWhatsAppMessage($this->video);
        $shareUrl = $service->getWhatsAppShareUrl($this->video);

        // Valida que o texto contém elementos da Sofi e aviso do saldo pendente
        $this->assertStringContainsString('Sofi', $message);
        $this->assertStringContainsString('Dr. Fernando Morales', $message);
        $this->assertStringContainsString('400,00 EUR', $message);
        $this->assertStringContainsString('/m/videos/', $message);

        // Valida que o link do WhatsApp direciona com o telefone limpo do cliente
        $this->assertStringContainsString('phone=34600123456', $shareUrl);
        $this->assertStringContainsString('text=', $shareUrl);
    }
}
