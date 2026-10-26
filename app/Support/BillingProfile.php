<?php

namespace App\Support;

use App\Models\Cliente;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Dados de faturação do cliente: regras, gravação criptografada no cliente e cópia para a compra
 * (a fatura emitida guarda os dados em claro no snapshot, como documento fiscal).
 */
class BillingProfile
{
    public const TYPES = ['empresa', 'autonomo', 'particular'];

    /**
     * Regras com prefixo opcional (ex.: 'billing.' quando o formulário agrupa os campos).
     */
    public static function rules(string $prefix = ''): array
    {
        $type = $prefix.'billing_type';
        $country = $prefix.'billing_country';
        $business = fn () => in_array(request()->input($type), ['empresa', 'autonomo'], true);

        return [
            $type => ['required', Rule::in(self::TYPES)],
            $prefix.'billing_legal_name' => ['required', 'string', 'max:255'],
            $prefix.'billing_tax_id' => [
                Rule::requiredIf($business),
                'nullable', 'string', 'max:30',
                function (string $attribute, mixed $value, Closure $fail) use ($country) {
                    if (filled($value) && strtoupper((string) request()->input($country, 'ES')) === 'ES' && ! SpanishTaxId::isValid($value)) {
                        $fail(__('NIF, NIE ou CIF inválido. Confira as letras e os números.'));
                    }
                },
            ],
            $prefix.'billing_address' => [Rule::requiredIf($business), 'nullable', 'string', 'max:255'],
            $prefix.'billing_postal_code' => [Rule::requiredIf($business), 'nullable', 'string', 'max:20'],
            $prefix.'billing_city' => [Rule::requiredIf($business), 'nullable', 'string', 'max:100'],
            $country => ['required', 'string', 'size:2', 'alpha'],
        ];
    }

    public static function save(Cliente $cliente, array $data): void
    {
        $country = strtoupper($data['billing_country'] ?? 'ES');
        $taxId = filled($data['billing_tax_id'] ?? null)
            ? ($country === 'ES' ? SpanishTaxId::normalize($data['billing_tax_id']) : trim($data['billing_tax_id']))
            : null;

        $cliente->forceFill([
            'billing_type' => $data['billing_type'],
            'billing_legal_name' => trim($data['billing_legal_name']),
            'billing_tax_id' => $taxId,
            'billing_address' => $data['billing_address'] ?? null,
            'billing_postal_code' => $data['billing_postal_code'] ?? null,
            'billing_city' => $data['billing_city'] ?? null,
            'billing_country' => $country,
            'billing_updated_at' => now(),
        ])->save();
    }

    /**
     * Campos billing_* da compra (pack_purchases usa 'particular' ou 'empresa_autonomo').
     */
    public static function purchaseAttributes(Cliente $cliente): array
    {
        if (! $cliente->hasBillingProfile()) {
            return [];
        }

        return [
            'billing_type' => $cliente->billing_type === 'particular' ? 'particular' : 'empresa_autonomo',
            'billing_legal_name' => $cliente->billing_legal_name,
            'billing_tax_id' => $cliente->billing_tax_id,
            'billing_address' => $cliente->billing_address,
            'billing_postal_code' => $cliente->billing_postal_code,
            'billing_city' => $cliente->billing_city,
            'billing_country' => $cliente->billing_country ?: 'ES',
        ];
    }

    /**
     * Valores atuais para preencher formulários.
     */
    public static function formValues(?Cliente $cliente): array
    {
        return [
            'billing_type' => $cliente?->billing_type ?? 'empresa',
            'billing_legal_name' => $cliente?->billing_legal_name ?? ($cliente?->company_name ?? ''),
            'billing_tax_id' => $cliente?->billing_tax_id ?? '',
            'billing_address' => $cliente?->billing_address ?? '',
            'billing_postal_code' => $cliente?->billing_postal_code ?? '',
            'billing_city' => $cliente?->billing_city ?? '',
            'billing_country' => $cliente?->billing_country ?? 'ES',
        ];
    }
}
