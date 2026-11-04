<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use App\Services\MediaEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaEmbedTest extends TestCase
{
    #[DataProvider('mediaUrls')]
    public function test_media_urls_map_to_canonical_embed_urls(string $url, string $expected): void
    {
        $this->assertSame($expected, MediaEmbed::embedUrl($url));
    }

    public static function mediaUrls(): array
    {
        $yt = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';

        return [
            'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s', $yt],
            'youtube watch, v not first' => ['https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ', $yt],
            'youtube mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', $yt],
            'youtu.be share' => ['https://youtu.be/dQw4w9WgXcQ?si=abc123', $yt],
            'youtube shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', $yt],
            'youtube live' => ['https://www.youtube.com/live/dQw4w9WgXcQ?feature=share', $yt],
            'youtube embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', $yt],
            'html-encoded ampersand' => ['https://www.youtube.com/watch?feature=x&amp;v=dQw4w9WgXcQ', $yt],
            'vimeo' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
            'vimeo channel' => ['https://vimeo.com/channels/staffpicks/76979871', 'https://player.vimeo.com/video/76979871'],
            'tiktok' => ['https://www.tiktok.com/@user.name/video/7234567890123456789?lang=en', 'https://www.tiktok.com/player/v1/7234567890123456789'],
            'instagram reel' => ['https://www.instagram.com/reel/C1a2B3c4D5e/?igsh=xyz', 'https://www.instagram.com/p/C1a2B3c4D5e/embed'],
            'facebook page video' => ['https://www.facebook.com/somepage/videos/123456789/', 'https://www.facebook.com/plugins/video.php?href=https%3A%2F%2Fwww.facebook.com%2Fwatch%2F%3Fv%3D123456789&show_text=0'],
            'facebook reel' => ['https://www.facebook.com/reel/123456789', 'https://www.facebook.com/plugins/video.php?href=https%3A%2F%2Fwww.facebook.com%2Fwatch%2F%3Fv%3D123456789&show_text=0'],
            'spotify intl' => ['https://open.spotify.com/intl-fr/track/4uLU6hMCjMI75M1A2tKUQC?si=1', 'https://open.spotify.com/embed/track/4uLU6hMCjMI75M1A2tKUQC'],
        ];
    }

    public function test_non_media_urls_are_not_embedded(): void
    {
        $this->assertNull(MediaEmbed::embedUrl('https://example.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(MediaEmbed::embedUrl('https://evil.example/youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_media_anchor_is_saved_as_embed(): void
    {
        $clean = HtmlSanitizer::clean('<p>Intro</p><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">Watch</a><a href="https://example.com">Source</a>');

        $this->assertStringContainsString('<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"', $clean);
        $this->assertStringNotContainsString('youtube.com/watch', $clean);
        $this->assertStringContainsString('href="https://example.com"', $clean);
    }

    public function test_bare_media_url_on_its_own_line_is_embedded_but_inline_url_is_not(): void
    {
        $clean = HtmlSanitizer::clean("<p>Intro</p>\nhttps://youtu.be/dQw4w9WgXcQ\n<p>https://vimeo.com/76979871</p><p>See https://youtu.be/aaaaaaaaaaa today.</p>");

        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $clean);
        $this->assertStringContainsString('player.vimeo.com/video/76979871', $clean);
        $this->assertStringContainsString('See https://youtu.be/aaaaaaaaaaa today.', $clean);
    }

    public function test_iframe_with_watch_url_is_rebuilt_and_resanitizing_is_stable(): void
    {
        $once = HtmlSanitizer::clean('<iframe src="https://www.youtube.com/watch?v=dQw4w9WgXcQ" onload="x()"></iframe><iframe src="https://www.facebook.com/reel/123"></iframe>');

        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $once);
        $this->assertStringContainsString('facebook.com/plugins/video.php', $once);
        $this->assertStringNotContainsString('onload', $once);
        $this->assertSame($once, HtmlSanitizer::clean($once));
        $this->assertSame($once, MediaEmbed::convert($once));
    }
}
