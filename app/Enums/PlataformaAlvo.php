<?php

namespace App\Enums;

enum PlataformaAlvo: string
{
    case INSTAGRAM = 'instagram';
    case YOUTUBE = 'youtube';
    case TIKTOK = 'tiktok';
    case FACEBOOK = 'facebook';
    case LINKEDIN = 'linkedin';
    case X = 'x';
    case THREADS = 'threads';
    case PINTEREST = 'pinterest';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::INSTAGRAM => 'Instagram Reels & Feed',
            self::YOUTUBE => 'YouTube Shorts & Vídeos',
            self::TIKTOK => 'TikTok',
            self::FACEBOOK => 'Facebook Reels & Vídeos',
            self::LINKEDIN => 'LinkedIn',
            self::X => 'X (Twitter)',
            self::THREADS => 'Threads',
            self::PINTEREST => 'Pinterest',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::INSTAGRAM => '📸',
            self::YOUTUBE => '▶️',
            self::TIKTOK => '🎵',
            self::FACEBOOK => '👥',
            self::LINKEDIN => '💼',
            self::X => '𝕏',
            self::THREADS => '🧵',
            self::PINTEREST => '📌',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::INSTAGRAM => 'bg-pink-50 text-pink-700 border-pink-200',
            self::YOUTUBE => 'bg-red-50 text-red-700 border-red-200',
            self::TIKTOK => 'bg-zinc-900 text-white border-zinc-700',
            self::FACEBOOK => 'bg-blue-50 text-blue-700 border-blue-200',
            self::LINKEDIN => 'bg-sky-50 text-sky-700 border-sky-200',
            self::X => 'bg-zinc-100 text-zinc-900 border-zinc-300',
            self::THREADS => 'bg-zinc-100 text-zinc-900 border-zinc-300',
            self::PINTEREST => 'bg-rose-50 text-rose-700 border-rose-200',
        };
    }
}
