<?php

namespace App\Services\Billing;

use App\Support\Money;

class TaxCalculationService
{
    /**
     * Países da União Europeia (ISO-2).
     */
    public const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
        'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
        'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    /**
     * Alíquota padrão do IVA geral na Espanha.
     */
    public const DEFAULT_SPANISH_IVA_RATE = 21.00;

    /**
     * Calcula o desmembramento fiscal completo (IVA 21%, IRPF e Total Líquido).
     *
     * @param float $subtotal Base imponible (Preço do Pack + Add-ons)
     * @param string $countryCode Código ISO do país do comprador (ex: 'ES', 'BR')
     * @param string|null $postalCode Código postal para verificação de regimes especiais (Canárias, Ceuta, Melilla)
     * @param string|null $taxId NIF, CIF, NIE ou VAT intra-comunitário
     * @param bool $isCompany Se o comprador é pessoa jurídica / autônomo
     * @param float $irpfRate Alíquota de retenção IRPF (se o emissor for autônomo espanhol, padrão 0.00)
     * @return array
     */
    public function calculate(
        float $subtotal,
        string $countryCode = 'ES',
        ?string $postalCode = null,
        ?string $taxId = null,
        bool $isCompany = false,
        float $irpfRate = 0.00
    ): array {
        $country = strtoupper(trim($countryCode));
        $taxRate = self::DEFAULT_SPANISH_IVA_RATE;
        $taxLabel = 'IVA (21%)';
        $isReverseCharge = false;
        $isExempt = false;

        $cleanPostalCode = $postalCode ? trim($postalCode) : '';

        // 1. Territórios Especiais Espanhóis (Canárias, Ceuta e Melilla)
        if ($country === 'ES' && ! empty($cleanPostalCode)) {
            $prefix2 = substr($cleanPostalCode, 0, 2);
            if (in_array($prefix2, ['35', '38'], true)) {
                // Ilhas Canárias (IGIC)
                $taxRate = 0.00;
                $taxLabel = 'Exento de IVA (Régimen Especial Islas Canarias)';
                $isExempt = true;
            } elseif (in_array($prefix2, ['51', '52'], true)) {
                // Ceuta e Melilla (IPSI)
                $taxRate = 0.00;
                $taxLabel = 'Exento de IVA (Ceuta y Melilla)';
                $isExempt = true;
            }
        }

        // 2. Operações Intracomunitárias B2B (UE fora da Espanha)
        if (! $isExempt && $country !== 'ES' && in_array($country, self::EU_COUNTRIES, true)) {
            if ($isCompany && ! empty($taxId)) {
                // Inversión del sujeto pasivo (Reverse Charge Art. 196 Diretiva IVA)
                $taxRate = 0.00;
                $taxLabel = 'IVA 0% - Inversión del Sujeto Pasivo (Operación Intracomunitaria UE)';
                $isReverseCharge = true;
            } else {
                // B2C na UE
                $taxRate = self::DEFAULT_SPANISH_IVA_RATE;
                $taxLabel = 'IVA (21% - UE)';
            }
        }

        // 3. Exportação de Serviços para Fora da União Europeia (ex: Brasil, EUA, UK)
        if (! $isExempt && ! in_array($country, self::EU_COUNTRIES, true)) {
            $taxRate = 0.00;
            $taxLabel = 'Exento de IVA (Exportación de Servicios fuera de la UE)';
            $isExempt = true;
        }

        // Cálculos monetários
        $subtotal = round(max(0, $subtotal), 2);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        // Retenção IRPF (aplicável apenas em B2B dentro da Espanha se o emissor for autônomo)
        $irpfAmount = 0.00;
        if ($country === 'ES' && $isCompany && $irpfRate > 0) {
            $irpfAmount = round($subtotal * ($irpfRate / 100), 2);
        } else {
            $irpfRate = 0.00;
        }

        $totalAmount = round($subtotal + $taxAmount - $irpfAmount, 2);

        return [
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'tax_label' => $taxLabel,
            'irpf_rate' => $irpfRate,
            'irpf_amount' => $irpfAmount,
            'total_amount' => $totalAmount,
            'is_reverse_charge' => $isReverseCharge,
            'is_exempt' => $isExempt,
            'currency' => 'EUR',
            'amount_in_cents' => Money::cents($totalAmount),
        ];
    }
}
