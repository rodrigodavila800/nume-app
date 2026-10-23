<?php

namespace Tests\Unit;

use App\Models\Video;
use PHPUnit\Framework\TestCase;

class VideoEmbedTest extends TestCase
{
    public function test_it_parses_youtube_shorts_url(): void
    {
        $video = new Video(['video_url' => 'https://www.youtube.com/shorts/Ovla_x3EAgc']);

        $this->assertEquals('Ovla_x3EAgc', $video->youtube_id);
        $this->assertEquals('youtube', $video->video_type);
        $this->assertTrue($video->is_vertical);
        $this->assertEquals('https://www.youtube-nocookie.com/embed/Ovla_x3EAgc?autoplay=0&rel=0&modestbranding=1&playsinline=1', $video->embed_url);
        $this->assertEquals('https://img.youtube.com/vi/Ovla_x3EAgc/hqdefault.jpg', $video->effective_thumbnail_url);
    }

    public function test_it_parses_standard_youtube_url(): void
    {
        $video = new Video(['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->assertEquals('dQw4w9WgXcQ', $video->youtube_id);
        $this->assertEquals('youtube', $video->video_type);
        $this->assertFalse($video->is_vertical);
        $this->assertEquals('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=0&rel=0&modestbranding=1&playsinline=1', $video->embed_url);
    }

    public function test_it_parses_vimeo_url(): void
    {
        $video = new Video(['video_url' => 'https://vimeo.com/76979871']);

        $this->assertEquals('76979871', $video->vimeo_id);
        $this->assertEquals('vimeo', $video->video_type);
        $this->assertEquals('https://player.vimeo.com/video/76979871?badge=0&autopause=0&player_id=0&app_id=58479', $video->embed_url);
    }

    public function test_it_parses_direct_video_url(): void
    {
        $video = new Video(['video_url' => 'https://storage.googleapis.com/videos/sample.mp4']);

        $this->assertNull($video->youtube_id);
        $this->assertNull($video->vimeo_id);
        $this->assertNull($video->drive_id);
        $this->assertEquals('direct', $video->video_type);
        $this->assertEquals('https://storage.googleapis.com/videos/sample.mp4', $video->embed_url);
    }

    public function test_it_parses_google_drive_file_view_url(): void
    {
        $video = new Video(['video_url' => 'https://drive.google.com/file/d/1A2b3C-4d_5e6F7g8h9I/view?usp=sharing']);

        $this->assertEquals('1A2b3C-4d_5e6F7g8h9I', $video->drive_id);
        $this->assertEquals('drive', $video->video_type);
        $this->assertEquals('https://drive.google.com/file/d/1A2b3C-4d_5e6F7g8h9I/preview', $video->embed_url);
    }

    public function test_it_parses_google_drive_file_preview_url(): void
    {
        $video = new Video(['video_url' => 'https://drive.google.com/file/d/1A2b3C-4d_5e6F7g8h9I/preview']);

        $this->assertEquals('1A2b3C-4d_5e6F7g8h9I', $video->drive_id);
        $this->assertEquals('drive', $video->video_type);
        $this->assertEquals('https://drive.google.com/file/d/1A2b3C-4d_5e6F7g8h9I/preview', $video->embed_url);
    }

    public function test_it_parses_google_drive_open_url(): void
    {
        $video = new Video(['video_url' => 'https://drive.google.com/open?id=1A2b3C-4d_5e6F7g8h9I']);

        $this->assertEquals('1A2b3C-4d_5e6F7g8h9I', $video->drive_id);
        $this->assertEquals('drive', $video->video_type);
        $this->assertEquals('https://drive.google.com/file/d/1A2b3C-4d_5e6F7g8h9I/preview', $video->embed_url);
    }

    public function test_it_parses_google_drive_uc_url(): void
    {
        $video = new Video(['video_url' => 'https://drive.google.com/uc?id=1A2b3C-4d_5e6F7g8h9I']);

        $this->assertEquals('1A2b3C-4d_5e6F7g8h9I', $video->drive_id);
        $this->assertEquals('drive', $video->video_type);
        $this->assertEquals('https://drive.google.com/file/d/1A2b3C-4d_5e6F7g8h9I/preview', $video->embed_url);
    }

    public function test_it_handles_invalid_or_empty_drive_urls(): void
    {
        $emptyVideo = new Video(['video_url' => '']);
        $this->assertNull($emptyVideo->drive_id);
        $this->assertEquals('direct', $emptyVideo->video_type);

        $invalidVideo = new Video(['video_url' => 'https://drive.google.com/drive/folders/123456789']);
        $this->assertNull($invalidVideo->drive_id);
        $this->assertEquals('direct', $invalidVideo->video_type);
    }
}
