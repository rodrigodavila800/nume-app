<?php

namespace App\Support;

/**
 * Conversão única de valores monetários para centavos.
 *
 * Usada ao criar a cobrança (line items da Stripe, cupom de IRPF, pedido Redsys) e ao
 * conferir o valor pago nos webhooks: os dois lados precisam do mesmo cálculo.
 */
final class Money
{
    public static function cents(float|int|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Soma dos line items de uma Checkout Session (unit_amount × quantity), em centavos.
     *
     * @param  array<int, array{price_data: array{unit_amount: int}, quantity?: int}>  $lineItems
     */
    public static function lineItemsTotal(array $lineItems): int
    {
        return array_sum(array_map(
            fn (array $item): int => (int) $item['price_data']['unit_amount'] * (int) ($item['quantity'] ?? 1),
            $lineItems,
        ));
    }

    public static function sameCurrency(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && strtolower(trim($a)) === strtolower(trim($b));
    }
}
