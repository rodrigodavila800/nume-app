<?php

namespace App\Services\SocialPublishing;

use App\Models\Video;
use App\Models\VideoPublication;

/**
 * Legenda do post a partir do título da publicação e do SEO da Sofi, dentro dos limites da rede.
 */
class CaptionBuilder
{
    public function build(VideoPublication $publication, Video $video, int $maxChars, int $maxHashtags): string
    {
        $clean = fn (?string $text) => trim(str_replace(['<', '>'], '', (string) $text));

        $title = $clean($publication->titulo) ?: $clean($video->sofi_seo_title) ?: $clean($video->title);
        $description = $clean($video->sofi_seo_description);

        $hashtags = collect($video->sofi_hashtags ?? [])
            ->filter(fn ($tag) => is_string($tag) && trim($tag, " #\t\n") !== '')
            ->map(fn (string $tag) => '#'.preg_replace('/\s+/', '', ltrim($clean($tag), '#')))
            ->unique()
            ->take($maxHashtags)
            ->implode(' ');

        $body = trim(implode("\n\n", array_filter([$title, $description !== $title ? $description : null])));
        $suffix = $hashtags === '' ? '' : "\n\n".$hashtags;

        // As hashtags ficam sempre inteiras; o texto é que é cortado se faltar espaço.
        $room = max(0, $maxChars - mb_strlen($suffix));

        return trim(mb_substr($body, 0, $room).$suffix);
    }
}
