<?php

namespace App\Support;

/**
 * Validação de identificação fiscal espanhola com dígito de controle: NIF/DNI, NIE e CIF.
 * Só o formato e o controle; não consulta a AEAT.
 */
class SpanishTaxId
{
    private const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    public static function normalize(?string $value): string
    {
        return strtoupper(preg_replace('/[\s\-.]/', '', (string) $value));
    }

    public static function isValid(?string $value): bool
    {
        $id = self::normalize($value);

        return self::isDni($id) || self::isNie($id) || self::isCif($id);
    }

    public static function isDni(string $id): bool
    {
        if (! preg_match('/^(\d{8})([A-Z])$/', $id, $m)) {
            return false;
        }

        return self::DNI_LETTERS[(int) $m[1] % 23] === $m[2];
    }

    public static function isNie(string $id): bool
    {
        if (! preg_match('/^([XYZ])(\d{7})([A-Z])$/', $id, $m)) {
            return false;
        }

        $number = (int) (strpos('XYZ', $m[1]).$m[2]);

        return self::DNI_LETTERS[$number % 23] === $m[3];
    }

    public static function isCif(string $id): bool
    {
        if (! preg_match('/^([ABCDEFGHJNPQRSUVW])(\d{7})([0-9A-J])$/', $id, $m)) {
            return false;
        }

        $digits = $m[2];
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $n = (int) $digits[$i];
            if ($i % 2 === 0) {
                $n *= 2;
                $n = intdiv($n, 10) + $n % 10;
            }
            $sum += $n;
        }

        $controlDigit = (10 - $sum % 10) % 10;
        $controlLetter = 'JABCDEFGHI'[$controlDigit];
        $control = $m[3];

        return match (true) {
            str_contains('PQRSNW', $m[1]) => $control === $controlLetter,
            str_contains('ABEH', $m[1]) => $control === (string) $controlDigit,
            default => $control === (string) $controlDigit || $control === $controlLetter,
        };
    }
}
