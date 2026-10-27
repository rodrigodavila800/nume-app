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
            self::INSTAGRAM => 'IG',
            self::YOUTUBE => 'YT',
            self::TIKTOK => 'TT',
            self::FACEBOOK => 'FB',
            self::LINKEDIN => 'IN',
            self::X => 'X',
            self::THREADS => 'TH',
            self::PINTEREST => 'PI',
        };
    }

    /**
     * Coluna do DNA da Marca (clientes) que guarda o perfil digitado à mão para esta rede.
     */
    public function clienteField(): string
    {
        return match ($this) {
            self::INSTAGRAM => 'instagram_handle',
            self::YOUTUBE => 'youtube_channel',
            self::TIKTOK => 'tiktok_handle',
            self::FACEBOOK => 'facebook_url',
            self::LINKEDIN => 'linkedin_url',
            self::X => 'x_handle',
            self::THREADS => 'threads_handle',
            self::PINTEREST => 'pinterest_url',
        };
    }

    /**
     * Exemplo de preenchimento exibido no campo do DNA.
     */
    public function placeholder(): string
    {
        return match ($this) {
            self::INSTAGRAM => '@suamarca',
            self::YOUTUBE => 'youtube.com/@suamarca',
            self::TIKTOK => '@suamarca',
            self::FACEBOOK => 'facebook.com/suamarca',
            self::LINKEDIN => 'linkedin.com/company/suamarca',
            self::X => '@suamarca',
            self::THREADS => '@suamarca',
            self::PINTEREST => 'pinterest.com/suamarca',
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
