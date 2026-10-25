<?php

namespace App\Services\SocialPublishing\Drivers;

/**
 * TikTok (Content Posting API) pelo app próprio da NUME.
 *
 * Ainda indisponível: sem a auditoria do TikTok os envios ficam privados (SELF_ONLY).
 * Até lá, o TikTok segue pelo Upload-Post.
 */
class TikTokDriver extends PendingNativeDriver
{
    public function getName(): string
    {
        return 'tiktok_native';
    }

    public function supportedPlatforms(): array
    {
        return ['tiktok'];
    }

    protected function profilePrefix(): string
    {
        return 'tk';
    }
}
