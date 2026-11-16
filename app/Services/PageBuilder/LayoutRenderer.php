<?php

namespace App\Services\PageBuilder;

use App\Services\HtmlSanitizer;
use App\Services\MediaEmbed;
use Illuminate\Support\Str;

/**
 * Renders a NORMALIZED layout (see LayoutNormalizer) to HTML + scoped CSS.
 *
 * All values reaching this class have been type-checked, so CSS is built only
 * from numbers, enumerated options, validated colors and validated URLs, and
 * every text value is HTML-escaped here.
 */
final class LayoutRenderer
{
    private const SHADOWS = [
        'sm' => '0 1px 3px rgba(16,24,40,.12)',
        'md' => '0 6px 16px rgba(16,24,40,.12)',
        'lg' => '0 14px 32px rgba(16,24,40,.16)',
        'xl' => '0 24px 60px rgba(16,24,40,.22)',
    ];

    /** @var array<string, list<string>> CSS rules per device */
    private array $css = ['desktop' => [], 'tablet' => [], 'mobile' => []];

    /** @var array<string, list<string>> stacking rules emitted first in each media block */
    private array $stackCss = ['tablet' => [], 'mobile' => []];

    /** @var array<string, string> heading widget id => anchor */
    private array $anchors = [];

    /** @var list<array{level:int, text:string, anchor:string}> */
    private array $headings = [];

    private function __construct(private array $context) {}

    /**
     * @param  array  $context  editor (bool), url (string), title (string)
     * @return array{html: string, css: string}
     */
    public static function render(array $layout, array $context = []): array
    {
        $renderer = new self($context + ['editor' => false, 'url' => '', 'title' => '']);

        return $renderer->document($layout);
    }

    private function document(array $layout): array
    {
        $this->collectHeadings($layout);

        $html = '';
        foreach ($layout['sections'] ?? [] as $section) {
            $html .= $this->section($section);
        }

        $class = 'ffb'.($this->context['editor'] ? ' ffb-editor' : '');

        return ['html' => $html === '' && ! $this->context['editor'] ? '' : "<div class=\"{$class}\">{$html}</div>", 'css' => $this->stylesheet()];
    }

    // ------------------------------------------------------------------
    // Structure
    // ------------------------------------------------------------------

    private function section(array $section): string
    {
        $s = $section['settings'];
        $id = $section['id'];
        $self = ".ffb .ffb-e-{$id}";

        $this->controlCss(Schema::controls('section'), $s, $self);
        $this->backgroundCss($s, $self);

        // Columns & stacking.
        $stackOn = $s['stack_on'] ?? '';
        $stackDevices = match ($stackOn) {
            'none' => [],
            'tablet' => ['tablet', 'mobile'],
            default => ['mobile'],
        };
        foreach ($stackDevices as $device) {
            $this->stackCss[$device][] = "{$self}>.ffb-container{flex-wrap:".(! empty($s['reverse_stack']) ? 'wrap-reverse' : 'wrap').'}';
            $this->stackCss[$device][] = "{$self}>.ffb-container>.ffb-column{flex:0 0 100%;max-width:100%}";
        }

        $columns = '';
        foreach ($section['columns'] as $column) {
            $columns .= $this->column($column, $self);
        }

        $tag = in_array($s['html_tag'] ?? '', ['div', 'header', 'footer', 'aside', 'article'], true) ? $s['html_tag'] : 'section';
        $classes = ['ffb-section', "ffb-e-{$id}"];
        if (($s['layout'] ?? '') === 'full') {
            $classes[] = 'ffb-section--full';
        }
        $container = 'ffb-container'.(($s['content_width'] ?? '') === 'full' ? ' ffb-container--full' : '');

        return '<'.$tag.$this->attrs('section', $id, $s, $classes).'><div class="'.$container.'">'.$columns.'</div></'.$tag.'>';
    }

    private function column(array $column, string $sectionSelector): string
    {
        $s = $column['settings'];
        $id = $column['id'];
        $self = ".ffb .ffb-e-{$id}";

        $this->controlCss(Schema::controls('column'), $s, $self);
        $this->backgroundCss($s, $self);

        foreach ((array) ($s['width'] ?? []) as $device => $width) {
            $fraction = $this->num(1 - $width / 100);
            $this->css[$device][] = "{$sectionSelector}>.ffb-container>.ffb-e-{$id}{flex:0 0 calc({$this->num($width)}% - var(--ffb-gap,20px) * {$fraction});max-width:{$this->num($width)}%}";
        }

        $widgets = '';
        foreach ($column['widgets'] as $widget) {
            $widgets .= $this->widget($widget);
        }
        if ($widgets === '' && $this->context['editor']) {
            $widgets = '<div class="ffb-empty" data-ffb-placeholder><i class="fa-solid fa-plus"></i><span>Drag a widget here</span></div>';
        }

        return '<div'.$this->attrs('column', $id, $s, ['ffb-column', "ffb-e-{$id}"]).'><div class="ffb-column-inner">'.$widgets.'</div></div>';
    }

    private function widget(array $widget): string
    {
        $s = $widget['settings'];
        $id = $widget['id'];
        $type = $widget['type'];
        $self = ".ffb .ffb-e-{$id}";

        $this->controlCss(Schema::controls('widget', $type), $s, $self);

        $method = 'w'.Str::studly($type);
        $inner = method_exists($this, $method) ? $this->{$method}($s, $id, $self) : '';

        if ($inner === '' && $this->context['editor']) {
            $title = Schema::all()['widgets'][$type]['title'] ?? $type;
            $icon = Schema::all()['widgets'][$type]['icon'] ?? 'fa-cube';
            $inner = '<div class="ffb-widget-placeholder"><i class="fa-solid '.$this->e($icon).'"></i> '.$this->e($title).': add content in the panel</div>';
        }

        return '<div'.$this->attrs('widget', $id, $s, ['ffb-widget', 'ffb-widget-'.str_replace('_', '-', $type), "ffb-e-{$id}"], $type).'>'.$inner.'</div>';
    }

    /** Common wrapper attributes: classes, anchor id, visibility, animation, editor hooks. */
    private function attrs(string $el, string $id, array $s, array $classes, ?string $type = null): string
    {
        foreach (Schema::DEVICES as $device) {
            if (! empty($s["_hide_{$device}"])) {
                $classes[] = "ffb-hide-{$device}";
            }
        }
        if (! empty($s['_animation'])) {
            $classes[] = 'ffb-anim';
            $classes[] = 'ffb-anim--'.$s['_animation'];
        }
        if (! empty($s['overlay_color'])) {
            $classes[] = 'ffb-has-overlay';
        }
        if (! empty($s['_css_classes'])) {
            $classes = [...$classes, ...preg_split('/\s+/', trim($s['_css_classes']))];
        }

        $attrs = ' class="'.$this->e(implode(' ', array_filter($classes))).'"';
        if (! empty($s['_css_id'])) {
            $attrs .= ' id="'.$this->e($s['_css_id']).'"';
        }
        if ($this->context['editor']) {
            $attrs .= ' data-ffb-id="'.$id.'" data-ffb-el="'.$el.'"'.($type ? ' data-ffb-type="'.$this->e($type).'"' : '');
        }

        return $attrs;
    }

    // ------------------------------------------------------------------
    // CSS generation
    // ------------------------------------------------------------------

    private function controlCss(?array $controls, array $s, string $self): void
    {
        foreach ($controls ?? [] as $control) {
            if (empty($control['selectors']) || ! array_key_exists($control['name'], $s) || ! $this->conditionMet($control, $s)) {
                continue;
            }

            $value = $s[$control['name']];
            $perDevice = ! empty($control['responsive']) && is_array($value) && $control['type'] !== 'multiselect'
                ? $value
                : ['desktop' => $value];

            foreach ($perDevice as $device => $deviceValue) {
                foreach ($control['selectors'] as $selector => $template) {
                    $declaration = $this->declaration($control, $template, $deviceValue);
                    if ($declaration !== null) {
                        $this->css[$device][] = str_replace('{{SELF}}', $self, $selector).'{'.$declaration.'}';
                    }
                }
            }
        }
    }

    private function declaration(array $control, string $template, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if ($control['type'] === 'dimensions') {
            $parts = [];
            foreach ((array) $value as $side => $n) {
                $parts[] = "{$template}-{$side}:{$this->num($n)}px";
            }

            return implode(';', $parts) ?: null;
        }

        if ($control['type'] === 'switch') {
            return $value === true ? $template : null;
        }

        $value = match ($control['map'] ?? null) {
            'font' => Schema::FONT_STACKS[$value] ?? null,
            'shadow' => self::SHADOWS[$value] ?? null,
            'percent' => $this->num($value / 100),
            default => is_int($value) || is_float($value) ? $this->num($value) : $value,
        };

        if (! is_string($value) || $value === '' || preg_match('/[;{}<>\\\\]/', $value)) {
            return null;
        }

        return str_replace('{{VALUE}}', $value, $template);
    }

    private function backgroundCss(array $s, string $self): void
    {
        $type = $s['bg_type'] ?? '';
        $rules = [];

        if ($type === 'classic') {
            if (! empty($s['bg_color'])) {
                $rules[] = "background-color:{$s['bg_color']}";
            }
            if (! empty($s['bg_image'])) {
                $rules[] = 'background-image:url("'.$s['bg_image'].'")';
                $rules[] = 'background-position:'.($s['bg_position'] ?? 'center center');
                $rules[] = 'background-size:'.($s['bg_size'] ?? 'cover');
                $rules[] = 'background-repeat:'.($s['bg_repeat'] ?? 'no-repeat');
                if (($s['bg_attachment'] ?? '') === 'fixed') {
                    $rules[] = 'background-attachment:fixed';
                }
            }
        } elseif ($type === 'gradient') {
            $a = $s['bg_color'] ?? '#0b3b60';
            $b = $s['bg_color_b'] ?? '#b99146';
            $rules[] = ($s['bg_gradient_type'] ?? '') === 'radial'
                ? "background-image:radial-gradient(circle,{$a},{$b})"
                : 'background-image:linear-gradient('.$this->num($s['bg_angle'] ?? 180)."deg,{$a},{$b})";
        }

        if ($rules !== []) {
            $this->css['desktop'][] = $self.'{'.implode(';', $rules).'}';
        }

        if (! empty($s['overlay_color'])) {
            $opacity = $this->num(($s['overlay_opacity'] ?? 50) / 100);
            $this->css['desktop'][] = "{$self}::before{background:{$s['overlay_color']};opacity:{$opacity}}";
        }
    }

    private function conditionMet(array $control, array $s): bool
    {
        foreach ($control['condition'] ?? [] as $field => $allowed) {
            $value = $s[$field] ?? '';
            if (is_bool($value)) {
                $value = $value ? '1' : '';
            }
            if (! in_array((string) (is_array($value) ? ($value['desktop'] ?? '') : $value), array_map('strval', $allowed), true)) {
                return false;
            }
        }

        return true;
    }

    private function stylesheet(): string
    {
        $out = implode('', array_unique($this->css['desktop']));
        foreach (Schema::BREAKPOINTS as $device => $max) {
            $rules = [...$this->stackCss[$device], ...$this->css[$device]];
            if ($rules !== []) {
                $out .= "@media (max-width:{$max}px){".implode('', array_unique($rules)).'}';
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Table of contents support
    // ------------------------------------------------------------------

    private function collectHeadings(array $layout): void
    {
        $used = [];
        foreach ($layout['sections'] ?? [] as $section) {
            foreach ($section['columns'] as $column) {
                foreach ($column['widgets'] as $widget) {
                    if ($widget['type'] !== 'heading') {
                        continue;
                    }
                    $s = $widget['settings'];
                    $anchor = $s['_css_id'] ?? '';
                    if ($anchor === '') {
                        $base = Str::slug(Str::limit($s['text'] ?? '', 60, '')) ?: 'section';
                        $anchor = $base;
                        for ($i = 2; isset($used[$anchor]); $i++) {
                            $anchor = "{$base}-{$i}";
                        }
                    }
                    $used[$anchor] = true;
                    $this->anchors[$widget['id']] = $anchor;

                    $tag = $s['tag'] ?? 'h2';
                    if (preg_match('/^h([2-4])$/', $tag, $m) && ($s['show_in_toc'] ?? true) && trim($s['text'] ?? '') !== '') {
                        $this->headings[] = ['level' => (int) $m[1], 'text' => $s['text'], 'anchor' => $anchor];
                    }
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Widgets
    // ------------------------------------------------------------------

    private function wHeading(array $s, string $id): string
    {
        $tag = in_array($s['tag'] ?? 'h2', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span'], true) ? ($s['tag'] ?? 'h2') : 'h2';
        $text = nl2br($this->e($s['text'] ?? ''), false);
        if ($text === '') {
            return '';
        }
        if (! empty($s['link'])) {
            $text = '<a href="'.$this->e($s['link']).'">'.$text.'</a>';
        }
        $anchorAttr = empty($s['_css_id']) && isset($this->anchors[$id]) ? ' id="'.$this->e($this->anchors[$id]).'"' : '';
        $size = ! empty($s['size']) ? ' ffb-size-'.$s['size'] : '';

        return "<{$tag} class=\"ffb-heading{$size}\"{$anchorAttr}>{$text}</{$tag}>";
    }

    private function wText(array $s): string
    {
        $content = $s['content'] ?? '';
        if (trim(strip_tags($content, '<img><iframe>')) === '') {
            return '';
        }

        return '<div class="ffb-text'.(! empty($s['drop_cap']) ? ' ffb-dropcap' : '').'">'.$content.'</div>';
    }

    private function wImage(array $s, string $id): string
    {
        if (empty($s['src'])) {
            return '';
        }

        $img = '<img src="'.$this->e($s['src']).'" alt="'.$this->e($s['alt'] ?? '').'" loading="lazy" decoding="async">';
        $link = $s['link_to'] ?? '';
        if ($link === 'file') {
            $img = '<a href="'.$this->e($s['src']).'" data-ffb-lightbox="'.$id.'">'.$img.'</a>';
        } elseif ($link === 'custom' && ! empty($s['link'])) {
            $img = '<a href="'.$this->e($s['link']).'"'.$this->target($s).'>'.$img.'</a>';
        }
        $hover = ! empty($s['hover']) ? ' ffb-hover-'.$s['hover'] : '';
        $caption = ! empty($s['caption']) ? '<figcaption>'.$this->e($s['caption']).'</figcaption>' : '';

        return '<figure class="ffb-image'.$hover.'">'.$img.$caption.'</figure>';
    }

    private function wVideo(array $s): string
    {
        $embed = ! empty($s['url']) ? MediaEmbed::embedUrl($s['url']) : null;
        if (! $embed) {
            return '';
        }

        $isAudio = str_starts_with($embed, 'https://open.spotify.com/');
        $caption = ! empty($s['caption']) ? '<figcaption>'.$this->e($s['caption']).'</figcaption>' : '';

        return '<figure class="ffb-video'.($isAudio ? ' ffb-video--audio' : '').'"><iframe src="'.$this->e($embed).'" title="'.($isAudio ? 'Embedded audio' : 'Embedded video').'" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe>'.$caption.'</figure>';
    }

    private function wButton(array $s): string
    {
        $text = $this->e($s['text'] ?? '');
        $icon = ! empty($s['icon']) ? '<i class="'.$this->e($s['icon']).'" aria-hidden="true"></i>' : '';
        $label = ($s['icon_position'] ?? '') === 'before' ? $icon.'<span>'.$text.'</span>' : '<span>'.$text.'</span>'.$icon;
        $classes = 'ffb-btn ffb-btn--'.(($s['variant'] ?? '') ?: 'filled').(! empty($s['size']) ? ' ffb-btn--'.$s['size'] : '').(! empty($s['full_width']) ? ' ffb-btn--block' : '');

        return '<a class="'.$classes.'" href="'.$this->e(($s['link'] ?? '') ?: '#').'"'.$this->target($s).'>'.$label.'</a>';
    }

    private function wDivider(array $s): string
    {
        $align = ' ffb-divider--'.(($s['align'] ?? '') ?: 'center');
        $label = ! empty($s['text']) ? '<span class="ffb-divider-label">'.$this->e($s['text']).'</span><span class="ffb-divider-line"></span>' : '';

        return '<div class="ffb-divider'.$align.'" role="separator"><span class="ffb-divider-line"></span>'.$label.'</div>';
    }

    private function wSpacer(): string
    {
        return '<div class="ffb-spacer" aria-hidden="true"></div>';
    }

    private function wIcon(array $s): string
    {
        if (empty($s['icon'])) {
            return '';
        }
        $view = ! empty($s['view']) ? ' ffb-icon--'.$s['view'] : '';
        $icon = '<span class="ffb-icon'.$view.'"><i class="'.$this->e($s['icon']).'" aria-hidden="true"></i></span>';

        return ! empty($s['link']) ? '<a href="'.$this->e($s['link']).'" aria-label="Icon link">'.$icon.'</a>' : $icon;
    }

    private function wIconBox(array $s): string
    {
        $tag = in_array($s['title_tag'] ?? 'h3', ['h2', 'h3', 'h4', 'h5', 'div'], true) ? ($s['title_tag'] ?? 'h3') : 'h3';
        $title = $this->e($s['title'] ?? '');
        if (! empty($s['link']) && $title !== '') {
            $title = '<a href="'.$this->e($s['link']).'">'.$title.'</a>';
        }
        $icon = ! empty($s['icon']) ? '<div class="ffb-iconbox__icon"><i class="'.$this->e($s['icon']).'" aria-hidden="true"></i></div>' : '';
        $position = ! empty($s['position']) ? ' ffb-iconbox--'.$s['position'] : '';

        return '<div class="ffb-iconbox'.$position.'">'.$icon.'<div class="ffb-iconbox__content">'
            .($title !== '' ? "<{$tag} class=\"ffb-iconbox__title\">{$title}</{$tag}>" : '')
            .(! empty($s['description']) ? '<p class="ffb-iconbox__desc">'.nl2br($this->e($s['description']), false).'</p>' : '')
            .'</div></div>';
    }

    private function wImageBox(array $s): string
    {
        $img = ! empty($s['src']) ? '<img src="'.$this->e($s['src']).'" alt="'.$this->e($s['title'] ?? '').'" loading="lazy">' : '';
        $title = $this->e($s['title'] ?? '');
        if (! empty($s['link'])) {
            $img = $img !== '' ? '<a href="'.$this->e($s['link']).'">'.$img.'</a>' : '';
            $title = $title !== '' ? '<a href="'.$this->e($s['link']).'">'.$title.'</a>' : '';
        }

        return '<div class="ffb-imagebox">'.$img
            .($title !== '' ? '<h3 class="ffb-imagebox__title">'.$title.'</h3>' : '')
            .(! empty($s['description']) ? '<p class="ffb-imagebox__desc">'.nl2br($this->e($s['description']), false).'</p>' : '')
            .'</div>';
    }

    private function wQuote(array $s): string
    {
        if (trim($s['text'] ?? '') === '') {
            return '';
        }
        $variant = ' ffb-quote--'.(($s['variant'] ?? '') ?: 'border');
        $cite = ! empty($s['cite']) ? '<cite>'.$this->e($s['cite']).'</cite>' : '';

        return '<blockquote class="ffb-quote'.$variant.'"><p>'.nl2br($this->e($s['text']), false).'</p>'.$cite.'</blockquote>';
    }

    private function wIconList(array $s): string
    {
        $items = '';
        foreach ($s['items'] ?? [] as $item) {
            $icon = ! empty($item['icon']) ? '<i class="'.$this->e($item['icon']).'" aria-hidden="true"></i>' : '';
            $text = '<span>'.$this->e($item['text'] ?? '').'</span>';
            $content = ! empty($item['link']) ? '<a href="'.$this->e($item['link']).'">'.$icon.$text.'</a>' : $icon.$text;
            $items .= '<li>'.$content.'</li>';
        }
        if ($items === '') {
            return '';
        }
        $classes = 'ffb-iconlist'.(($s['layout'] ?? '') === 'inline' ? ' ffb-iconlist--inline' : '').(! empty($s['divider']) ? ' ffb-iconlist--divided' : '');

        return '<ul class="'.$classes.'">'.$items.'</ul>';
    }

    private function wGallery(array $s, string $id): string
    {
        $items = '';
        foreach ($s['images'] ?? [] as $image) {
            if (empty($image['src'])) {
                continue;
            }
            $img = '<img src="'.$this->e($image['src']).'" alt="'.$this->e($image['alt'] ?? '').'" loading="lazy" decoding="async">';
            if ($s['lightbox'] ?? true) {
                $img = '<a href="'.$this->e($image['src']).'" data-ffb-lightbox="'.$id.'"'.(! empty($image['caption']) ? ' data-caption="'.$this->e($image['caption']).'"' : '').'>'.$img.'</a>';
            }
            $caption = ! empty($s['show_captions']) && ! empty($image['caption']) ? '<figcaption>'.$this->e($image['caption']).'</figcaption>' : '';
            $items .= '<figure>'.$img.$caption.'</figure>';
        }

        return $items === '' ? '' : '<div class="ffb-gallery">'.$items.'</div>';
    }

    private function wAccordion(array $s, string $id): string
    {
        $items = '';
        $faq = [];
        foreach (array_values($s['items'] ?? []) as $i => $item) {
            $open = $i === 0 && ($s['open_first'] ?? true) ? ' open' : '';
            $name = ($s['mode'] ?? '') === 'toggle' ? '' : ' name="ffb-acc-'.$id.'"';
            $items .= '<details'.$name.$open.'><summary>'.$this->e($item['title'] ?? '').'<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><div class="ffb-acc__body">'.($item['content'] ?? '').'</div></details>';
            $faq[] = ['@type' => 'Question', 'name' => $item['title'] ?? '', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(html_entity_decode(strip_tags($item['content'] ?? ''), ENT_QUOTES | ENT_HTML5))]];
        }
        if ($items === '') {
            return '';
        }

        $schema = '';
        if (! empty($s['faq_schema']) && ! $this->context['editor']) {
            $json = json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $schema = '<script type="application/ld+json">'.$json.'</script>';
        }

        return '<div class="ffb-accordion">'.$items.'</div>'.$schema;
    }

    private function wTabs(array $s, string $id): string
    {
        $tabs = '';
        $panels = '';
        foreach (array_values($s['items'] ?? []) as $i => $item) {
            $tabId = "ffb-tab-{$id}-{$i}";
            $panelId = "ffb-panel-{$id}-{$i}";
            $selected = $i === 0 ? 'true' : 'false';
            $tabs .= '<button type="button" class="ffb-tabs__tab" role="tab" id="'.$tabId.'" aria-controls="'.$panelId.'" aria-selected="'.$selected.'"'.($i === 0 ? '' : ' tabindex="-1"').'>'.$this->e($item['title'] ?? '').'</button>';
            $panels .= '<div class="ffb-tabs__panel" role="tabpanel" id="'.$panelId.'" aria-labelledby="'.$tabId.'"'.($i === 0 ? '' : ' hidden').'>'.($item['content'] ?? '').'</div>';
        }
        if ($tabs === '') {
            return '';
        }

        return '<div class="ffb-tabs'.(($s['orientation'] ?? '') === 'vertical' ? ' ffb-tabs--vertical' : '').'" data-ffb-tabs><div class="ffb-tabs__list" role="tablist">'.$tabs.'</div><div class="ffb-tabs__panels">'.$panels.'</div></div>';
    }

    private function wCounter(array $s): string
    {
        $end = (float) ($s['end'] ?? 100);
        $decimals = (int) ($s['decimals'] ?? 0);
        $separator = $s['separator'] ?? true;
        $formatted = number_format($end, $decimals, '.', $separator ? ',' : '');

        return '<div class="ffb-counter"><div class="ffb-counter__number">'
            .(! empty($s['prefix']) ? '<span class="ffb-counter__prefix">'.$this->e($s['prefix']).'</span>' : '')
            .'<span class="ffb-counter__value" data-ffb-counter data-from="'.$this->num($s['start'] ?? 0).'" data-to="'.$this->num($end).'" data-decimals="'.$decimals.'" data-separator="'.($separator ? ',' : '').'" data-duration="'.(int) ($s['duration'] ?? 2000).'">'.$formatted.'</span>'
            .(! empty($s['suffix']) ? '<span class="ffb-counter__suffix">'.$this->e($s['suffix']).'</span>' : '')
            .'</div>'.(! empty($s['title']) ? '<div class="ffb-counter__title">'.$this->e($s['title']).'</div>' : '').'</div>';
    }

    private function wProgress(array $s): string
    {
        $percent = (int) max(0, min(100, $s['percent'] ?? 50));
        $title = $this->e($s['title'] ?? '');

        return '<div class="ffb-progress"><div class="ffb-progress__head"><span>'.$title.'</span>'.(($s['show_percent'] ?? true) ? '<span>'.$percent.'%</span>' : '').'</div>'
            .'<div class="ffb-progress__track" role="progressbar" aria-label="'.$title.'" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'.$percent.'"><div class="ffb-progress__fill" style="width:'.$percent.'%"></div></div></div>';
    }

    private function wTestimonial(array $s): string
    {
        $stars = '';
        if (! empty($s['rating'])) {
            $rating = (float) $s['rating'];
            for ($i = 1; $i <= 5; $i++) {
                $stars .= '<i class="'.($rating >= $i ? 'fa-solid fa-star' : ($rating >= $i - 0.5 ? 'fa-solid fa-star-half-stroke' : 'fa-regular fa-star')).'" aria-hidden="true"></i>';
            }
            $stars = '<div class="ffb-stars" role="img" aria-label="Rated '.$this->num($rating).' out of 5">'.$stars.'</div>';
        }
        $image = ! empty($s['image']) ? '<img src="'.$this->e($s['image']).'" alt="'.$this->e($s['name'] ?? '').'" loading="lazy">' : '';

        return '<figure class="ffb-testimonial">'.$stars
            .'<blockquote class="ffb-testimonial__content">'.nl2br($this->e($s['content'] ?? ''), false).'</blockquote>'
            .'<figcaption class="ffb-testimonial__author">'.$image.'<span><strong class="ffb-testimonial__name">'.$this->e($s['name'] ?? '').'</strong>'
            .(! empty($s['role']) ? '<span class="ffb-testimonial__role">'.$this->e($s['role']).'</span>' : '').'</span></figcaption></figure>';
    }

    private function wCallout(array $s): string
    {
        $kind = ($s['kind'] ?? '') ?: 'info';
        $icons = ['info' => 'fa-circle-info', 'success' => 'fa-circle-check', 'warning' => 'fa-triangle-exclamation', 'danger' => 'fa-circle-xmark', 'note' => 'fa-note-sticky'];
        $icon = ($s['show_icon'] ?? true) ? '<i class="ffb-callout__icon fa-solid '.($icons[$kind] ?? 'fa-circle-info').'" aria-hidden="true"></i>' : '';

        return '<div class="ffb-callout ffb-callout--'.$kind.'" role="note">'.$icon.'<div>'
            .(! empty($s['title']) ? '<strong class="ffb-callout__title">'.$this->e($s['title']).'</strong>' : '')
            .'<div class="ffb-callout__content">'.($s['content'] ?? '').'</div></div></div>';
    }

    private function wCta(array $s, string $id, string $self): string
    {
        if (! empty($s['image'])) {
            $this->css['desktop'][] = "{$self} .ffb-cta{background-image:linear-gradient(rgba(8,31,50,.65),rgba(8,31,50,.65)),url(\"{$s['image']}\");background-size:cover;background-position:center;color:#fff}";
        }

        return '<div class="ffb-cta'.(($s['layout'] ?? '') === 'row' ? ' ffb-cta--row' : '').'"><div class="ffb-cta__content">'
            .(! empty($s['title']) ? '<h3 class="ffb-cta__title">'.$this->e($s['title']).'</h3>' : '')
            .(! empty($s['description']) ? '<p>'.nl2br($this->e($s['description']), false).'</p>' : '')
            .'</div>'.(! empty($s['button_text']) ? '<a class="ffb-btn ffb-btn--filled" href="'.$this->e(($s['link'] ?? '') ?: '#').'">'.$this->e($s['button_text']).'</a>' : '').'</div>';
    }

    private function wTable(array $s): string
    {
        $rows = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $s['data'] ?? '')), fn ($line) => $line !== ''));
        if ($rows === []) {
            return '';
        }

        $html = '';
        $header = $s['header'] ?? true;
        foreach ($rows as $i => $line) {
            $cellTag = $header && $i === 0 ? 'th' : 'td';
            $cells = array_map(fn ($cell) => "<{$cellTag}>".$this->e(trim($cell))."</{$cellTag}>", explode('|', $line));
            $row = '<tr>'.implode('', $cells).'</tr>';
            $html .= $header && $i === 0 ? '<thead>'.$row.'</thead><tbody>' : $row;
        }
        $html .= $header ? '</tbody>' : '';
        $caption = ! empty($s['caption']) ? '<caption>'.$this->e($s['caption']).'</caption>' : '';
        $classes = 'ffb-table'.(($s['striped'] ?? true) ? ' ffb-table--striped' : '');

        return '<div class="ffb-table-wrap"><table class="'.$classes.'">'.$caption.($header ? $html : '<tbody>'.$html.'</tbody>').'</table></div>';
    }

    private function wToc(array $s): string
    {
        $levels = array_map(fn ($l) => (int) substr($l, 1), explode(',', ($s['levels'] ?? '') ?: 'h2,h3'));
        $items = array_values(array_filter($this->headings, fn ($h) => in_array($h['level'], $levels, true)));

        $list = '';
        if ($items === []) {
            if (! $this->context['editor']) {
                return '';
            }
            $list = '<p class="ffb-toc__empty">Add Heading widgets (H2–H4) to build the table of contents.</p>';
        } else {
            $tag = ! empty($s['numbered']) ? 'ol' : 'ul';
            $min = min(array_column($items, 'level'));
            $list = "<{$tag}>";
            foreach ($items as $item) {
                $indent = $item['level'] - $min;
                $list .= '<li class="ffb-toc__l'.$indent.'"><a href="#'.$this->e($item['anchor']).'">'.$this->e($item['text']).'</a></li>';
            }
            $list .= "</{$tag}>";
        }

        $title = $this->e(($s['title'] ?? '') ?: 'Contents');

        return ! empty($s['collapsible'])
            ? '<details class="ffb-toc" open><summary class="ffb-toc__title">'.$title.'</summary>'.$list.'</details>'
            : '<nav class="ffb-toc" aria-label="'.$title.'"><div class="ffb-toc__title">'.$title.'</div>'.$list.'</nav>';
    }

    private function wShare(array $s): string
    {
        $url = rawurlencode($this->context['url'] ?: url('/'));
        $title = rawurlencode($this->context['title'] ?: config('app.name'));
        $networks = [
            'x' => ['X', 'fa-brands fa-x-twitter', "https://twitter.com/intent/tweet?url={$url}&text={$title}"],
            'facebook' => ['Facebook', 'fa-brands fa-facebook-f', "https://www.facebook.com/sharer/sharer.php?u={$url}"],
            'linkedin' => ['LinkedIn', 'fa-brands fa-linkedin-in', "https://www.linkedin.com/sharing/share-offsite/?url={$url}"],
            'whatsapp' => ['WhatsApp', 'fa-brands fa-whatsapp', "https://wa.me/?text={$title}%20{$url}"],
            'telegram' => ['Telegram', 'fa-brands fa-telegram', "https://t.me/share/url?url={$url}&text={$title}"],
            'reddit' => ['Reddit', 'fa-brands fa-reddit-alien', "https://www.reddit.com/submit?url={$url}&title={$title}"],
            'email' => ['Email', 'fa-solid fa-envelope', "mailto:?subject={$title}&body={$url}"],
        ];

        $links = '';
        foreach ($s['networks'] ?? [] as $network) {
            if (! isset($networks[$network])) {
                continue;
            }
            [$name, $icon, $href] = $networks[$network];
            $links .= '<a class="ffb-share__link ffb-share--'.$network.'" href="'.$this->e($href).'" target="_blank" rel="noopener noreferrer" aria-label="Share on '.$name.'"><i class="'.$icon.'" aria-hidden="true"></i>'.(! empty($s['show_names']) ? '<span>'.$name.'</span>' : '').'</a>';
        }
        if ($links === '') {
            return '';
        }
        $skin = ! empty($s['skin']) ? ' ffb-share--skin-'.$s['skin'] : '';

        return '<div class="ffb-share'.$skin.'">'.(! empty($s['label']) ? '<span class="ffb-share__label">'.$this->e($s['label']).'</span>' : '').'<div class="ffb-share__links">'.$links.'</div></div>';
    }

    private function wCode(array $s): string
    {
        if (($s['code'] ?? '') === '') {
            return '';
        }

        return '<div class="ffb-code'.(($s['dark'] ?? true) ? ' ffb-code--dark' : '').'">'
            .(! empty($s['language']) ? '<div class="ffb-code__lang">'.$this->e($s['language']).'</div>' : '')
            .'<pre><code>'.$this->e($s['code']).'</code></pre></div>';
    }

    private function wHtml(array $s): string
    {
        $html = $s['html'] ?? '';
        if (trim($html) === '') {
            return '';
        }

        try {
            return HtmlSanitizer::clean($html);
        } catch (\LengthException) {
            return '';
        }
    }

    private function wMap(array $s): string
    {
        if (empty($s['address'])) {
            return '';
        }
        $src = 'https://maps.google.com/maps?q='.rawurlencode($s['address']).'&z='.(int) ($s['zoom'] ?? 12).'&output=embed';

        return '<div class="ffb-map"><iframe src="'.$this->e($src).'" title="Map of '.$this->e($s['address']).'" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function target(array $s): string
    {
        return ! empty($s['new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function num(int|float $n): string
    {
        if (is_int($n)) {
            return (string) $n;
        }

        $s = rtrim(rtrim(sprintf('%.4F', $n), '0'), '.');

        return $s === '-0' ? '0' : $s;
    }
}
