<?php

namespace App\Traits;

trait HasEmbeddableVideo
{
    /**
     * Retorna o ID do YouTube caso o link seja do YouTube (Watch, Shorts, youtu.be, embed)
     */
    public function getYoutubeIdAttribute(): ?string
    {
        if (empty($this->video_url)) {
            return null;
        }

        // Suporta os formatos do YouTube:
        // - https://youtube.com/shorts/Ovla_x3EAgc?si=o-NQDm1D_rq-zvyJ
        // - https://www.youtube.com/shorts/Ovla_x3EAgc
        // - https://www.youtube.com/watch?v=Ovla_x3EAgc
        // - https://youtu.be/Ovla_x3EAgc
        // - https://www.youtube.com/embed/Ovla_x3EAgc
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $this->video_url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Retorna o ID do Vimeo caso o link seja do Vimeo
     */
    public function getVimeoIdAttribute(): ?string
    {
        if (empty($this->video_url)) {
            return null;
        }

        if (preg_match('/vimeo\.com\/(?:video\/)?([0-9]+)/i', $this->video_url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Retorna o ID do Google Drive caso o link seja do Google Drive
     */
    public function getDriveIdAttribute(): ?string
    {
        if (empty($this->video_url)) {
            return null;
        }

        // 1. Formatos com /file/d/FILE_ID/... (ex: /view, /preview)
        if (preg_match('#drive\.google\.com/file/d/([a-zA-Z0-9_-]+)#i', $this->video_url, $matches)) {
            return $matches[1];
        }

        // 2. Formatos com query param /open?id=FILE_ID ou /uc?id=FILE_ID
        if (preg_match('#drive\.google\.com/(?:open|uc)\?(?:[^\s"\'&]*&)*id=([a-zA-Z0-9_-]+)#i', $this->video_url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Retorna o tipo de player a ser renderizado: 'youtube', 'vimeo', 'drive' ou 'direct'
     */
    public function getVideoTypeAttribute(): string
    {
        if ($this->youtube_id !== null) {
            return 'youtube';
        }

        if ($this->vimeo_id !== null) {
            return 'vimeo';
        }

        if ($this->drive_id !== null) {
            return 'drive';
        }

        return 'direct';
    }

    /**
     * Verifica se o vídeo é no formato vertical (Shorts, Reels, TikTok ou proporção 9:16)
     */
    public function getIsVerticalAttribute(): bool
    {
        // 1. Se a coluna is_vertical estiver presente explicitamente no modelo, respeita a escolha!
        if (array_key_exists('is_vertical', $this->attributes) && $this->attributes['is_vertical'] !== null) {
            return (bool) $this->attributes['is_vertical'];
        }

        if (empty($this->video_url)) {
            return false;
        }

        // 2. Fallbacks inteligentes caso não definido:
        if (str_contains($this->video_url, '/shorts/') || str_contains($this->video_url, '/reel/')) {
            return true;
        }

        if (isset($this->category) && in_array(strtolower($this->category), ['reels', 'shorts', 'tiktok', 'vertical', 'stories'])) {
            return true;
        }

        if (isset($this->platform) && in_array(strtolower($this->platform), ['tiktok', 'instagram'])) {
            return true;
        }

        return false;
    }

    /**
     * Retorna a URL pronta para iframe embed seguro e responsivo
     */
    public function getEmbedUrlAttribute(): ?string
    {
        if ($this->youtube_id) {
            // YouTube no-cookie para máxima compatibilidade e sem bloqueios
            return "https://www.youtube-nocookie.com/embed/{$this->youtube_id}?autoplay=0&rel=0&modestbranding=1&playsinline=1";
        }

        if ($this->vimeo_id) {
            return "https://player.vimeo.com/video/{$this->vimeo_id}?badge=0&autopause=0&player_id=0&app_id=58479";
        }

        if ($this->drive_id) {
            return "https://drive.google.com/file/d/{$this->drive_id}/preview";
        }

        return $this->video_url;
    }

    /**
     * Retorna a thumbnail cadastrada no banco ou infere automaticamente a capa oficial do vídeo (YouTube/Vimeo)
     */
    public function getEffectiveThumbnailUrlAttribute(): ?string
    {
        if (! empty($this->thumbnail_url)) {
            if (str_starts_with($this->thumbnail_url, 'http://') || str_starts_with($this->thumbnail_url, 'https://')) {
                return $this->thumbnail_url;
            }

            return asset('storage/'.ltrim($this->thumbnail_url, '/'));
        }

        if ($this->youtube_id) {
            return "https://img.youtube.com/vi/{$this->youtube_id}/hqdefault.jpg";
        }

        return null;
    }
}
