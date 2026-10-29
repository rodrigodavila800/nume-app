<?php

namespace Tests\Feature;

use App\Mail\ProductionStatusChangedMail;
use App\Models\Cliente;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notification\SofiProductionNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProductionStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $staff;

    protected Cliente $cliente;

    protected Production $production;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Aurora',
            'slug' => 'produtora-aurora',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->staff = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Editor Chefe',
            'email' => 'editor@aurora.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Marcelo Silva',
            'email' => 'marcelo@clinica.test',
            'phone' => '+34 600 123 456',
            'password' => bcrypt('secret123'),
        ]);

        $this->production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'title' => 'Vídeos Institucionais de Cirurgia',
            'status' => 'draft',
            'created_by' => $this->staff->id,
        ]);
    }

    public function test_service_generates_whatsapp_message_with_portal_link(): void
    {
        $service = app(SofiProductionNotificationService::class);
        $message = $service->getWhatsAppMessage($this->production, 'in_production');

        $this->assertStringContainsString('Vídeos Institucionais de Cirurgia', $message);
        $this->assertStringContainsString('Dr. Marcelo Silva', $message);
        $this->assertStringContainsString('Entrou em Gravação!', $message);
        $this->assertStringContainsString($this->production->uuid, $message);

        $shareUrl = $service->getWhatsAppShareUrl($this->production, 'in_production');
        $this->assertStringContainsString('api.whatsapp.com/send', $shareUrl);
        $this->assertStringContainsString('34600123456', $shareUrl);
    }

    public function test_notify_cliente_route_sends_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->staff)->post(
            route('productions.notify-cliente', $this->production),
            ['channel' => 'email']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(ProductionStatusChangedMail::class, function ($mail) {
            return $mail->hasTo('marcelo@clinica.test')
                && $mail->production->id === $this->production->id;
        });
    }

    public function test_notify_cliente_all_channel_prepares_whatsapp_redirect(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->staff)->post(
            route('productions.notify-cliente', $this->production),
            ['channel' => 'all']
        );

        $response->assertRedirect();
        $response->assertSessionHas('whatsapp_redirect_url');
        Mail::assertSent(ProductionStatusChangedMail::class);
    }

    public function test_update_status_dispatches_email_when_notify_cliente_is_checked(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->staff)->patch(
            route('productions.update-status', $this->production),
            [
                'status' => 'in_production',
                'notify_cliente' => '1',
            ]
        );

        $response->assertRedirect();
        $this->assertEquals('in_production', $this->production->fresh()->status);

        Mail::assertSent(ProductionStatusChangedMail::class, function ($mail) {
            return $mail->newStatus === 'in_production'
                && $mail->previousStatus === 'draft';
        });
    }
}
