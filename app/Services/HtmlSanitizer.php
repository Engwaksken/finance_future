<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * HTML sanitizer for user-authored article bodies, backed by
 * symfony/html-sanitizer (a real HTML5 parser).
 *
 * Unlike the previous regex/whitelist implementation, this uses an explicit
 * element/attribute allowlist and a proper URL scheme policy, so it is not
 * bypassable by encoding/parser-differential tricks.
 */
final class HtmlSanitizer
{
    /**
     * Upper bound (in bytes) on the size of HTML the sanitizer will process.
     *
     * Symfony's own default (20 KB) is far too small for long-form article
     * bodies and would silently truncate them during re-sanitization. One
     * megabyte is ample for CMS content while still bounding parser memory.
     */
    public const MAX_INPUT_LENGTH = 1_000_000;

    private static ?SymfonyHtmlSanitizer $sanitizer = null;

    public static function clean(string $html): string
    {
        if (strlen($html) > self::MAX_INPUT_LENGTH) {
            throw new \LengthException('Article body exceeds the 1,000,000-byte sanitization limit.');
        }

        // Only permit the embed host used by the editor. Never allow arbitrary
        // third-party frames in user-authored article content.
        $html = preg_replace_callback('/<iframe\b([^>]*)>(.*?)<\/iframe\s*>/is', static function (array $match): string {
            preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $match[1], $src);

            return isset($src[2]) && preg_match('~^https://www\.youtube-nocookie\.com/embed/[A-Za-z0-9_-]{11}$~D', html_entity_decode($src[2], ENT_QUOTES | ENT_HTML5))
                ? $match[0]
                : '';
        }, $html) ?? $html;

        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): SymfonyHtmlSanitizer
    {
        return self::$sanitizer ??= new SymfonyHtmlSanitizer(self::config());
    }

    private static function config(): HtmlSanitizerConfig
    {
        return (new HtmlSanitizerConfig)
            // Structure & inline text.
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowElement('br')
            ->allowElement('p')
            ->allowElement('div')
            ->allowElement('span')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('h5')
            ->allowElement('h6')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('del')
            // Lists & quotes.
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote', ['cite'])
            // Code & separators.
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('hr')
            // Media.
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height'])
            // Editors insert embeds only from YouTube URLs.
            ->allowElement('iframe', ['src', 'title', 'width', 'height', 'allow', 'allowfullscreen'])
            ->allowElement('figure')
            ->allowElement('figcaption')
            // Tables.
            ->allowElement('table')
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tfoot')
            ->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan'])
            ->allowElement('td', ['colspan', 'rowspan'])
            // Typography.
            ->allowElement('sup')
            ->allowElement('sub')
            // Harden every link: no tab-nabbing, no referrer leakage, no SEO juice.
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            // URL policy: links may be http(s)/mailto/tel or relative; media may
            // be http(s) or relative. This blocks javascript:/data:/vbscript:/file:.
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withMaxInputLength(self::MAX_INPUT_LENGTH);
    }
}
