<?php

namespace App\Services\PageBuilder;

use App\Models\Article;
use Illuminate\Support\Str;

final class PageBuilder
{
    /**
     * Normalize untrusted layout input and render it.
     *
     * @return array{layout: array, html: string, css: string}
     */
    public static function build(mixed $layout, array $context = []): array
    {
        if (is_string($layout)) {
            $layout = json_decode($layout, true, 64);
        }

        $layout = LayoutNormalizer::normalize($layout);

        return ['layout' => $layout] + LayoutRenderer::render($layout, $context);
    }

    /** Render context (share URLs, title) for an article. */
    public static function context(Article $article, bool $editor = false): array
    {
        return [
            'editor' => $editor,
            'url' => $article->slug ? route('articles.show', $article->slug) : '',
            'title' => (string) $article->title,
        ];
    }

    /** Wrap a legacy HTML body in a single-section layout so it can be edited visually. */
    public static function fromHtml(?string $html): array
    {
        $html = trim((string) $html);

        return LayoutNormalizer::normalize([
            'sections' => $html === '' ? [] : [[
                'columns' => [[
                    'widgets' => [['type' => 'text', 'settings' => ['content' => $html]]],
                ]],
            ]],
        ]);
    }

    /** Layout used for brand new articles. */
    public static function starter(): array
    {
        return LayoutNormalizer::normalize([
            'sections' => [[
                'columns' => [[
                    'widgets' => [['type' => 'text']],
                ]],
            ]],
        ]);
    }

    /** Schema in a JS-friendly form (option maps become ordered [value, label] pairs). */
    public static function schemaForEditor(): array
    {
        $schema = Schema::all();
        $convert = function (array $controls) use (&$convert): array {
            return array_map(function (array $control) use ($convert) {
                if (isset($control['options'])) {
                    $control['options'] = array_map(
                        fn ($value, $label) => [(string) $value, $label],
                        array_keys($control['options']),
                        $control['options']
                    );
                }
                if (isset($control['fields'])) {
                    $control['fields'] = $convert($control['fields']);
                }
                unset($control['selectors'], $control['map'], $control['pattern']);

                return $control;
            }, $controls);
        };

        $schema['section'] = $convert($schema['section']);
        $schema['column'] = $convert($schema['column']);
        foreach ($schema['widgets'] as $type => $widget) {
            $schema['widgets'][$type]['controls'] = $convert($widget['controls']);
        }
        $schema['categories'] = ['basic' => 'Basic', 'general' => 'General', 'media' => 'Media', 'article' => 'Article'];

        return $schema;
    }

    public static function plainText(string $html): string
    {
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $text)), ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    public static function excerpt(string $html, int $limit = 220): string
    {
        return Str::limit(self::plainText($html), $limit);
    }
}
