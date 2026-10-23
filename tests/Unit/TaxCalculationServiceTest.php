<?php

namespace Tests\Unit;

use App\Services\Billing\TaxCalculationService;
use PHPUnit\Framework\TestCase;

class TaxCalculationServiceTest extends TestCase
{
    private TaxCalculationService $taxService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taxService = new TaxCalculationService();
    }

    public function test_calculates_standard_spanish_iva_21_percent_for_mainland(): void
    {
        // 1.000 € subtotal em Zaragoza (Espanha)
        $result = $this->taxService->calculate(
            subtotal: 1000.00,
            countryCode: 'ES',
            postalCode: '50001', // Zaragoza
            isCompany: false
        );

        $this->assertEquals(1000.00, $result['subtotal']);
        $this->assertEquals(21.00, $result['tax_rate']);
        $this->assertEquals(210.00, $result['tax_amount']);
        $this->assertEquals(1210.00, $result['total_amount']);
        $this->assertEquals(121000, $result['amount_in_cents']);
        $this->assertFalse($result['is_exempt']);
        $this->assertFalse($result['is_reverse_charge']);
    }

    public function test_canary_islands_are_exempt_from_iva(): void
    {
        // Ilhas Canárias (Las Palmas - 35xxx)
        $result = $this->taxService->calculate(
            subtotal: 500.00,
            countryCode: 'ES',
            postalCode: '35001',
            isCompany: true
        );

        $this->assertEquals(500.00, $result['subtotal']);
        $this->assertEquals(0.00, $result['tax_rate']);
        $this->assertEquals(0.00, $result['tax_amount']);
        $this->assertEquals(500.00, $result['total_amount']);
        $this->assertTrue($result['is_exempt']);
    }

    public function test_eu_b2b_with_vat_id_applies_reverse_charge_0_percent(): void
    {
        // Empresa na França (FR) com VAT
        $result = $this->taxService->calculate(
            subtotal: 800.00,
            countryCode: 'FR',
            postalCode: '75001',
            taxId: 'FR12345678901',
            isCompany: true
        );

        $this->assertEquals(800.00, $result['subtotal']);
        $this->assertEquals(0.00, $result['tax_rate']);
        $this->assertEquals(0.00, $result['tax_amount']);
        $this->assertEquals(800.00, $result['total_amount']);
        $this->assertTrue($result['is_reverse_charge']);
    }

    public function test_export_outside_eu_is_exempt(): void
    {
        // Cliente no Brasil (BR)
        $result = $this->taxService->calculate(
            subtotal: 1200.00,
            countryCode: 'BR',
            postalCode: '01310-100',
            taxId: '12.345.678/0001-90',
            isCompany: true
        );

        $this->assertEquals(1200.00, $result['subtotal']);
        $this->assertEquals(0.00, $result['tax_rate']);
        $this->assertEquals(0.00, $result['tax_amount']);
        $this->assertEquals(1200.00, $result['total_amount']);
        $this->assertTrue($result['is_exempt']);
    }

    public function test_applies_irpf_retention_when_configured(): void
    {
        // Empresa espanhola comprando de profissional autônomo com retenção de 15%
        $result = $this->taxService->calculate(
            subtotal: 1000.00,
            countryCode: 'ES',
            postalCode: '28001',
            taxId: 'B12345678',
            isCompany: true,
            irpfRate: 15.00
        );

        $this->assertEquals(1000.00, $result['subtotal']);
        $this->assertEquals(21.00, $result['tax_rate']);
        $this->assertEquals(210.00, $result['tax_amount']);
        $this->assertEquals(15.00, $result['irpf_rate']);
        $this->assertEquals(150.00, $result['irpf_amount']);
        // 1000 + 210 - 150 = 1060
        $this->assertEquals(1060.00, $result['total_amount']);
        $this->assertEquals(106000, $result['amount_in_cents']);
    }
}
