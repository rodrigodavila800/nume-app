<?php

namespace App\Services\UploadPost;

/**
 * Interpreta o mapa `social_accounts` devolvido pelo Upload-Post (perfil e webhook).
 *
 * O formato de cada conta não está fixado na documentação a que temos acesso: plataforma
 * não conectada pode vir como null, string vazia ou com `connected: false`. Por isso só
 * conta como conectada a entrada com conteúdo de verdade, e o handle é lido de forma
 * tolerante. Guarda apenas dados públicos (nunca tokens).
 */
final class SocialAccounts
{
    private const HANDLE_KEYS = ['handle', 'username', 'user_name', 'screen_name'];

    private const NAME_KEYS = ['display_name', 'name', 'title'];

    private const AVATAR_KEYS = ['avatar', 'avatar_url', 'profile_picture', 'social_images'];

    /**
     * @return array<string, array{handle: ?string, display_name: ?string, avatar: ?string}>
     */
    public static function connected(mixed $accounts): array
    {
        if (! is_array($accounts)) {
            return [];
        }

        $result = [];

        foreach ($accounts as $platform => $account) {
            if (! is_string($platform) || ! self::isConnected($account)) {
                continue;
            }

            $result[strtolower($platform)] = is_array($account)
                ? [
                    'handle' => self::firstText($account, self::HANDLE_KEYS),
                    'display_name' => self::firstText($account, self::NAME_KEYS),
                    'avatar' => self::firstUrl($account, self::AVATAR_KEYS),
                ]
                : ['handle' => self::clean((string) $account), 'display_name' => null, 'avatar' => null];
        }

        return $result;
    }

    private static function isConnected(mixed $account): bool
    {
        if (is_array($account)) {
            return $account !== [] && ($account['connected'] ?? true) !== false;
        }

        return is_string($account) && trim($account) !== '';
    }

    private static function firstText(array $account, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (is_string($account[$key] ?? null) && ($value = self::clean($account[$key])) !== null) {
                return $value;
            }
        }

        return null;
    }

    private static function firstUrl(array $account, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $account[$key] ?? null;
            $value = is_array($value) ? reset($value) : $value;

            if (is_string($value) && str_starts_with($value, 'https://') && mb_strlen($value) <= 500) {
                return $value;
            }
        }

        return null;
    }

    private static function clean(string $value): ?string
    {
        $value = trim(strip_tags($value));

        return $value === '' ? null : mb_substr($value, 0, 100);
    }
}
