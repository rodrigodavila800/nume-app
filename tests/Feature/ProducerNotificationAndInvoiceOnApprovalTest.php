<?php

namespace Tests\Feature;

use App\Mail\InvoiceIssuedMail;
use App\Mail\ProducerVideoApprovedMail;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProducerNotificationAndInvoiceOnApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Cliente $cliente;
    private User $editor;
    private User $admin;
    private Pack $pack;
    private PackPurchase $purchase;
    private Production $production;
    private Video $video;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Studio Zaragoza Audiovisual',
            'slug' => 'studio-zaragoza',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->editor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lucas Editor',
            'email' => 'lucas@studiozaragoza.es',
            'role' => 'editor',
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Beatriz Admin',
            'email' => 'beatriz@studiozaragoza.es',
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Santiago Ramos',
            'email' => 'santiago@clinicaramos.es',
            'password' => bcrypt('password123'),
            'brand_niche' => 'Dermatologia Estética',
            'website' => 'https://clinicaramos.es',
        ]);

        $this->pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Campanha Reels 50/50',
            'slug' => 'pack-campanha-reels-50-50',
            'price' => 700.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'billing_mode' => 'split_50_50',
            'deposit_percentage' => 50,
            'video_quantity' => 1,
            'status' => 'active',
        ]);

        $this->purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $this->pack->id,
            'cliente_id' => $this->cliente->id,
            'amount' => 700.00,
            'billing_mode' => 'split_50_50',
            'deposit_amount' => 350.00,
            'deposit_paid_at' => now(),
            'balance_amount' => 350.00,
            'balance_status' => 'pending',
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'video_quantity' => 1,
            'remaining_videos' => 1,
        ]);

        $this->production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->editor->id,
            'pack_purchase_id' => $this->purchase->id,
            'title' => 'Campanha Rejuvenescimento Facial',
            'status' => 'in_production',
        ]);

        $this->video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'title' => 'Corte 1 - Antes e Depois',
            'video_url' => 'https://vimeo.com/77665544',
            'status' => 'processing',
        ]);
    }

    public function test_approval_and_release_issues_invoice_to_client_and_notifies_producer_team(): void
    {
        Mail::fake();

        $this->assertTrue($this->video->isAssetLocked());

        // Cliente clica em aprovar o corte e liquidar o saldo
        $response = $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.videos.approve-and-release', $this->video));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->purchase->refresh();
        $this->video->refresh();

        // 1. Saldo liquidado e ativo liberado
        $this->assertEquals('paid', $this->purchase->balance_status);
        $this->assertFalse($this->video->isAssetLocked());
        $this->assertEquals('ready', $this->video->status);

        // 2. Fatura oficial gerada
        $this->assertNotNull($this->purchase->invoice_number);

        // 3. E-mail de fatura emitido para o cliente
        Mail::assertSent(InvoiceIssuedMail::class, function (InvoiceIssuedMail $mail) {
            return $mail->hasTo($this->cliente->email)
                && $mail->purchase->id === $this->purchase->id;
        });

        // 4. Notificação reversa enviada para o editor e o administrador da produtora
        Mail::assertSent(ProducerVideoApprovedMail::class, function (ProducerVideoApprovedMail $mail) {
            $isToEditor = $mail->hasTo($this->editor->email);
            $isToAdmin = $mail->hasTo($this->admin->email);

            return ($isToEditor || $isToAdmin)
                && $mail->video->id === $this->video->id;
        });
    }

    public function test_standard_approval_also_notifies_producer_team(): void
    {
        Mail::fake();

        // Compra no modelo padrão (100% upfront já quitado)
        $standardPurchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $this->pack->id,
            'cliente_id' => $this->cliente->id,
            'amount' => 500.00,
            'billing_mode' => 'full_upfront',
            'balance_status' => 'not_applicable',
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'video_quantity' => 1,
            'remaining_videos' => 1,
        ]);

        $this->production->update(['pack_purchase_id' => $standardPurchase->id]);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.videos.approve-and-release', $this->video));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('ready', $this->video->fresh()->status);

        Mail::assertSent(ProducerVideoApprovedMail::class);
    }
}
