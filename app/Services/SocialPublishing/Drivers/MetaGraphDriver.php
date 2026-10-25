<?php

namespace App\Services\SocialPublishing\Drivers;

/**
 * Meta (Instagram Reels / Facebook) pelo app próprio da NUME.
 *
 * Ainda indisponível: depende do Meta App Review (instagram_content_publish) e da verificação
 * da empresa. Até lá, Instagram e Facebook seguem pelo Upload-Post.
 */
class MetaGraphDriver extends PendingNativeDriver
{
    public function getName(): string
    {
        return 'meta_graph';
    }

    public function supportedPlatforms(): array
    {
        return ['instagram', 'facebook'];
    }

    protected function profilePrefix(): string
    {
        return 'meta';
    }
}
