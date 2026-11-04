<?php

namespace App\Services;

/**
 * Turns links to supported media platforms into embedded players.
 *
 * Conversion happens server-side so a media link becomes a player no matter
 * how it got into the article body: the editor's "Insert link" button, a
 * pasted URL on its own line, a hand-written <a> tag, or an article saved
 * before embeds were supported.
 */
final class MediaEmbed
{
    /**
     * Canonical embed URLs that may appear as an iframe src. Anything else is
     * stripped by the sanitizer.
     */
    private const ALLOWED_SRC = [
        '~^https://www\.youtube-nocookie\.com/embed/[A-Za-z0-9_-]{11}$~D',
        '~^https://player\.vimeo\.com/video/\d+$~D',
        '~^https://www\.tiktok\.com/player/v1/\d+$~D',
        '~^https://www\.instagram\.com/p/[\w-]+/embed$~D',
        '~^https://www\.facebook\.com/plugins/video\.php\?href=https%3A%2F%2Fwww\.facebook\.com%2Fwatch%2F%3Fv%3D\d+&show_text=0$~D',
        '~^https://open\.spotify\.com/embed/(?:track|album|playlist|episode|show)/[A-Za-z0-9]+$~D',
    ];

    public static function isAllowedSrc(string $url): bool
    {
        foreach (self::ALLOWED_SRC as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map a public media URL (watch page, share link, or embed URL) to its
     * canonical embed URL, or null when the URL is not a supported media link.
     */
    public static function embedUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));

        if (self::isAllowedSrc($url)) {
            return $url;
        }

        if (preg_match('~^(?:https?://)?(?:(?:www|m|music)\.)?(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/|v/)|youtube-nocookie\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&#/].*)?$~i', $url, $m)) {
            return "https://www.youtube-nocookie.com/embed/{$m[1]}";
        }

        if (preg_match('~^https?://(?:www\.|player\.)?vimeo\.com/(?:video/|channels/[\w-]+/|groups/[\w-]+/videos/)?(\d+)(?:[?/#].*)?$~i', $url, $m)) {
            return "https://player.vimeo.com/video/{$m[1]}";
        }

        if (preg_match('~^https?://(?:www\.|m\.)?tiktok\.com/(?:@[\w.-]+/video/|player/v1/|embed/(?:v2/)?)(\d+)(?:[?/#].*)?$~i', $url, $m)) {
            return "https://www.tiktok.com/player/v1/{$m[1]}";
        }

        if (preg_match('~^https?://(?:www\.)?instagram\.com/(?:[\w.]+/)?(?:p|reel|reels|tv)/([\w-]+)/?(?:embed/?)?(?:[?#].*)?$~i', $url, $m)) {
            return "https://www.instagram.com/p/{$m[1]}/embed";
        }

        if (preg_match('~^https?://(?:www\.|m\.|web\.)?facebook\.com/(?:watch/?\?(?:[^#]*&)?v=|reel/|[\w.-]+/videos/(?:[\w.-]+/)?|video\.php\?(?:[^#]*&)?v=)(\d+)(?:[?&#/].*)?$~i', $url, $m)) {
            return 'https://www.facebook.com/plugins/video.php?href='
                .rawurlencode("https://www.facebook.com/watch/?v={$m[1]}").'&show_text=0';
        }

        if (preg_match('~^https?://open\.spotify\.com/(?:intl-[a-z-]+/)?(?:embed/)?(track|album|playlist|episode|show)/([A-Za-z0-9]+)(?:[?#].*)?$~i', $url, $m)) {
            return 'https://open.spotify.com/embed/'.strtolower($m[1])."/{$m[2]}";
        }

        return null;
    }

    public static function iframe(string $embedUrl): string
    {
        $isAudio = str_starts_with($embedUrl, 'https://open.spotify.com/');
        $src = htmlspecialchars($embedUrl, ENT_QUOTES | ENT_HTML5);

        return sprintf(
            '<iframe src="%s" title="%s" width="560" height="%d" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe>',
            $src,
            $isAudio ? 'Embedded audio' : 'Embedded video',
            $isAudio ? 152 : 315,
        );
    }

    /**
     * Replace media links in an HTML fragment with embedded players:
     *  - <a href="MEDIA_URL">…</a> anchors, and
     *  - bare media URLs standing alone on a line or as the only content of a
     *    paragraph/div (URLs inside running text are left alone).
     */
    public static function convert(string $html): string
    {
        $html = preg_replace_callback(
            '~<a\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1[^>]*>.*?</a\s*>~is',
            static fn (array $m): string => ($embed = self::embedUrl($m[2])) ? self::iframe($embed) : $m[0],
            $html
        ) ?? $html;

        return preg_replace_callback(
            '~(^|\n|<br\s*/?>|<p\b[^>]*>|<div\b[^>]*>)([ \t]*)(https?://[^\s<>"\']+)(?=[ \t]*(?:\r?\n|$|<br\s*/?>|</p>|</div>))~i',
            static fn (array $m): string => ($embed = self::embedUrl($m[3])) ? $m[1].$m[2].self::iframe($embed) : $m[0],
            $html
        ) ?? $html;
    }
}
