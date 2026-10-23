<?php

namespace Tests\Feature;

use App\Mail\InvoiceIssuedMail;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Services\Billing\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Cliente $cliente;
    protected Pack $pack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Studio Zaragoza',
            'slug' => 'nume-zaragoza',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->cliente = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Carlos Hernández',
            'email' => 'carlos@empresa.es',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->pack = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Reels Retención',
            'slug' => 'pack-reels-retencion',
            'price' => 1000.00,
            'video_quantity' => 8,
            'status' => 'active',
        ]);
    }

    public function test_invoice_service_sends_email_with_pdf_attachment(): void
    {
        Mail::fake();

        $purchase = PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 8,
            'amount' => 1210.00,
            'subtotal' => 1000.00,
            'tax_rate' => 21.0,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'payment_gateway' => 'bizum',
            'invoice_number' => 'NUM-2026-00042',
            'billing_legal_name' => 'Carlos Hernández SL',
            'billing_tax_id' => 'B50999111',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $invoiceService = app(InvoiceService::class);
        $sent = $invoiceService->sendInvoiceEmail($purchase);

        $this->assertTrue($sent);

        Mail::assertSent(InvoiceIssuedMail::class, function (InvoiceIssuedMail $mail) {
            $this->assertTrue($mail->hasTo('carlos@empresa.es'));
            $this->assertStringContainsString('NUM-2026-00042', $mail->envelope()->subject);

            $attachments = $mail->attachments();
            $this->assertCount(1, $attachments);

            return true;
        });
    }

    public function test_issue_and_notify_generates_file_and_sends_mail(): void
    {
        Mail::fake();

        $purchase = PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 8,
            'amount' => 1210.00,
            'subtotal' => 1000.00,
            'tax_rate' => 21.0,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $invoiceService = app(InvoiceService::class);
        $filePath = $invoiceService->issueAndNotify($purchase);

        $purchase->refresh();

        $this->assertNotNull($purchase->invoice_number);
        $this->assertNotNull($purchase->invoice_path);
        $this->assertEquals($filePath, $purchase->invoice_path);

        Mail::assertSent(InvoiceIssuedMail::class, function (InvoiceIssuedMail $mail) use ($purchase) {
            return $mail->hasTo('carlos@empresa.es')
                && str_contains($mail->envelope()->subject, $purchase->invoice_number);
        });
    }
}
