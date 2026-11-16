<?php

namespace App\Services\PageBuilder;

/**
 * Single source of truth for the visual article builder.
 *
 * Every element (section, column, widget) is described by a list of controls.
 * The same definitions are used to:
 *  - generate the editor's settings panels (exported to JS as JSON),
 *  - validate/normalize untrusted layout JSON on save (LayoutNormalizer),
 *  - turn style values into scoped CSS (LayoutRenderer).
 *
 * A control may declare `selectors`: a map of CSS selector => declaration
 * template. `{{SELF}}` is the element's scoped class and `{{VALUE}}` is the
 * validated value. Only typed, validated values ever reach the CSS, never
 * free text.
 */
final class Schema
{
    public const DEVICES = ['desktop', 'tablet', 'mobile'];

    public const BREAKPOINTS = ['tablet' => 1024, 'mobile' => 767];

    public const FONTS = [
        '' => 'Default',
        'manrope' => 'Manrope',
        'dmsans' => 'DM Sans',
        'serif' => 'Serif (Georgia)',
        'mono' => 'Monospace',
        'system' => 'System UI',
    ];

    public const FONT_STACKS = [
        'manrope' => 'Manrope,sans-serif',
        'dmsans' => '"DM Sans",sans-serif',
        'serif' => 'Georgia,"Times New Roman",serif',
        'mono' => 'ui-monospace,SFMono-Regular,Menlo,Consolas,monospace',
        'system' => 'system-ui,-apple-system,"Segoe UI",Roboto,sans-serif',
    ];

    public const ICONS = [
        '' => 'None', 'fa-solid fa-check' => 'Check', 'fa-solid fa-circle-check' => 'Check circle',
        'fa-solid fa-arrow-right' => 'Arrow right', 'fa-solid fa-chevron-right' => 'Chevron right',
        'fa-solid fa-star' => 'Star', 'fa-solid fa-heart' => 'Heart', 'fa-solid fa-bolt' => 'Bolt',
        'fa-solid fa-chart-line' => 'Chart line', 'fa-solid fa-chart-pie' => 'Chart pie',
        'fa-solid fa-coins' => 'Coins', 'fa-solid fa-sack-dollar' => 'Money bag', 'fa-solid fa-wallet' => 'Wallet',
        'fa-solid fa-building-columns' => 'Bank', 'fa-solid fa-piggy-bank' => 'Piggy bank',
        'fa-solid fa-bitcoin-sign' => 'Bitcoin', 'fa-solid fa-microchip' => 'Chip', 'fa-solid fa-robot' => 'Robot',
        'fa-solid fa-rocket' => 'Rocket', 'fa-solid fa-lightbulb' => 'Lightbulb', 'fa-solid fa-globe' => 'Globe',
        'fa-solid fa-shield-halved' => 'Shield', 'fa-solid fa-lock' => 'Lock', 'fa-solid fa-user' => 'User',
        'fa-solid fa-users' => 'Users', 'fa-solid fa-envelope' => 'Envelope', 'fa-solid fa-phone' => 'Phone',
        'fa-solid fa-location-dot' => 'Location', 'fa-solid fa-calendar' => 'Calendar', 'fa-solid fa-clock' => 'Clock',
        'fa-solid fa-download' => 'Download', 'fa-solid fa-link' => 'Link', 'fa-solid fa-play' => 'Play',
        'fa-solid fa-circle-info' => 'Info', 'fa-solid fa-triangle-exclamation' => 'Warning',
        'fa-solid fa-quote-left' => 'Quote', 'fa-solid fa-newspaper' => 'Newspaper', 'fa-solid fa-gem' => 'Gem',
        'fa-solid fa-trophy' => 'Trophy', 'fa-solid fa-handshake' => 'Handshake', 'fa-solid fa-leaf' => 'Leaf',
    ];

    private static ?array $cache = null;

    /** Full schema, as exported to the editor. */
    public static function all(): array
    {
        return self::$cache ??= [
            'devices' => self::DEVICES,
            'breakpoints' => self::BREAKPOINTS,
            'section' => self::sectionControls(),
            'column' => self::columnControls(),
            'widgets' => self::widgets(),
        ];
    }

    /** Controls for an element kind (section/column/widget type), or null if unknown. */
    public static function controls(string $kind, ?string $type = null): ?array
    {
        $all = self::all();

        return match ($kind) {
            'section' => $all['section'],
            'column' => $all['column'],
            'widget' => $all['widgets'][$type]['controls'] ?? null,
            default => null,
        };
    }

    // ------------------------------------------------------------------
    // Control helpers
    // ------------------------------------------------------------------

    private static function c(string $name, string $type, string $label, array $extra = []): array
    {
        return ['name' => $name, 'type' => $type, 'label' => $label] + $extra + ['tab' => 'content'];
    }

    private static function heading(string $label, string $tab = 'content', array $extra = []): array
    {
        return ['name' => '_h_'.md5($label.$tab.json_encode($extra)), 'type' => 'heading', 'label' => $label, 'tab' => $tab] + $extra;
    }

    private static function align(string $name, string $selector, string $tab = 'style', string $label = 'Alignment'): array
    {
        return self::c($name, 'choose', $label, [
            'tab' => $tab, 'responsive' => true,
            'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right', 'justify' => 'Justify'],
            'icons' => ['left' => 'fa-align-left', 'center' => 'fa-align-center', 'right' => 'fa-align-right', 'justify' => 'fa-align-justify'],
            'selectors' => [$selector => 'text-align:{{VALUE}}'],
        ]);
    }

    private static function typography(string $prefix, string $selector, string $label = 'Typography', array $extra = []): array
    {
        $s = ['tab' => 'style'] + $extra;

        return [
            self::heading($label, 'style', $extra),
            self::c("{$prefix}font_family", 'select', 'Font family', $s + ['options' => self::FONTS, 'selectors' => [$selector => 'font-family:{{VALUE}}'], 'map' => 'font']),
            self::c("{$prefix}font_size", 'number', 'Size (px)', $s + ['responsive' => true, 'min' => 6, 'max' => 200, 'selectors' => [$selector => 'font-size:{{VALUE}}px']]),
            self::c("{$prefix}font_weight", 'select', 'Weight', $s + ['options' => ['' => 'Default', '300' => '300 Light', '400' => '400 Normal', '500' => '500 Medium', '600' => '600 Semi bold', '700' => '700 Bold', '800' => '800 Extra bold', '900' => '900 Black'], 'selectors' => [$selector => 'font-weight:{{VALUE}}']]),
            self::c("{$prefix}text_transform", 'select', 'Transform', $s + ['options' => ['' => 'Default', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize', 'none' => 'Normal'], 'selectors' => [$selector => 'text-transform:{{VALUE}}']]),
            self::c("{$prefix}font_style", 'select', 'Style', $s + ['options' => ['' => 'Default', 'normal' => 'Normal', 'italic' => 'Italic'], 'selectors' => [$selector => 'font-style:{{VALUE}}']]),
            self::c("{$prefix}line_height", 'number', 'Line height (em)', $s + ['responsive' => true, 'min' => 0.5, 'max' => 4, 'step' => 0.1, 'selectors' => [$selector => 'line-height:{{VALUE}}']]),
            self::c("{$prefix}letter_spacing", 'number', 'Letter spacing (px)', $s + ['min' => -5, 'max' => 20, 'step' => 0.1, 'selectors' => [$selector => 'letter-spacing:{{VALUE}}px']]),
        ];
    }

    private static function background(string $tab = 'style'): array
    {
        $bg = ['bg_type' => ['classic']];

        return [
            self::heading('Background', $tab),
            self::c('bg_type', 'choose', 'Type', ['tab' => $tab, 'options' => ['' => 'None', 'classic' => 'Classic', 'gradient' => 'Gradient'], 'icons' => ['' => 'fa-ban', 'classic' => 'fa-paintbrush', 'gradient' => 'fa-barcode']]),
            self::c('bg_color', 'color', 'Color', ['tab' => $tab, 'condition' => ['bg_type' => ['classic', 'gradient']]]),
            self::c('bg_image', 'image', 'Image', ['tab' => $tab, 'condition' => $bg]),
            self::c('bg_position', 'select', 'Position', ['tab' => $tab, 'condition' => $bg, 'options' => ['' => 'Default', 'center center' => 'Center', 'top center' => 'Top', 'bottom center' => 'Bottom', 'center left' => 'Left', 'center right' => 'Right']]),
            self::c('bg_size', 'select', 'Size', ['tab' => $tab, 'condition' => $bg, 'options' => ['' => 'Cover', 'contain' => 'Contain', 'auto' => 'Auto']]),
            self::c('bg_repeat', 'select', 'Repeat', ['tab' => $tab, 'condition' => $bg, 'options' => ['' => 'No repeat', 'repeat' => 'Repeat', 'repeat-x' => 'Repeat X', 'repeat-y' => 'Repeat Y']]),
            self::c('bg_attachment', 'select', 'Attachment', ['tab' => $tab, 'condition' => $bg, 'options' => ['' => 'Scroll', 'fixed' => 'Fixed (parallax)']]),
            self::c('bg_color_b', 'color', 'Second color', ['tab' => $tab, 'condition' => ['bg_type' => ['gradient']]]),
            self::c('bg_gradient_type', 'select', 'Gradient type', ['tab' => $tab, 'condition' => ['bg_type' => ['gradient']], 'options' => ['' => 'Linear', 'radial' => 'Radial']]),
            self::c('bg_angle', 'slider', 'Angle (deg)', ['tab' => $tab, 'condition' => ['bg_type' => ['gradient']], 'min' => 0, 'max' => 360]),
            self::heading('Background overlay', $tab),
            self::c('overlay_color', 'color', 'Overlay color', ['tab' => $tab]),
            self::c('overlay_opacity', 'slider', 'Overlay opacity (%)', ['tab' => $tab, 'min' => 0, 'max' => 100]),
        ];
    }

    private static function border(string $tab = 'style'): array
    {
        return [
            self::heading('Border & shadow', $tab),
            self::c('border_style', 'select', 'Border type', ['tab' => $tab, 'options' => ['' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'], 'selectors' => ['{{SELF}}' => 'border-style:{{VALUE}}']]),
            self::c('border_width', 'number', 'Border width (px)', ['tab' => $tab, 'min' => 0, 'max' => 50, 'condition' => ['border_style' => ['solid', 'dashed', 'dotted', 'double']], 'selectors' => ['{{SELF}}' => 'border-width:{{VALUE}}px']]),
            self::c('border_color', 'color', 'Border color', ['tab' => $tab, 'condition' => ['border_style' => ['solid', 'dashed', 'dotted', 'double']], 'selectors' => ['{{SELF}}' => 'border-color:{{VALUE}}']]),
            self::c('border_radius', 'number', 'Border radius (px)', ['tab' => $tab, 'responsive' => true, 'min' => 0, 'max' => 500, 'selectors' => ['{{SELF}}' => 'border-radius:{{VALUE}}px']]),
            self::c('box_shadow', 'select', 'Box shadow', ['tab' => $tab, 'options' => ['' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large'], 'map' => 'shadow', 'selectors' => ['{{SELF}}' => 'box-shadow:{{VALUE}}']]),
        ];
    }

    private static function advanced(bool $spacingDefaults = false): array
    {
        $t = ['tab' => 'advanced'];

        return [
            self::heading('Layout', 'advanced'),
            self::c('_margin', 'dimensions', 'Margin (px)', $t + ['responsive' => true, 'min' => -500, 'max' => 500, 'selectors' => ['{{SELF}}' => 'margin']]),
            self::c('_padding', 'dimensions', 'Padding (px)', $t + ['responsive' => true, 'min' => 0, 'max' => 500, 'selectors' => ['{{SELF}}' => 'padding']]),
            self::c('_z_index', 'number', 'Z-index', $t + ['min' => -10, 'max' => 999, 'selectors' => ['{{SELF}}' => 'position:relative;z-index:{{VALUE}}']]),
            self::c('_css_id', 'text', 'CSS ID (anchor)', $t + ['pattern' => '^[A-Za-z][A-Za-z0-9_-]{0,60}$', 'help' => 'Letters, numbers, - and _. Link to it with #id.']),
            self::c('_css_classes', 'text', 'CSS classes', $t + ['pattern' => '^[A-Za-z0-9_ -]{0,120}$']),
            self::heading('Motion effects', 'advanced'),
            self::c('_animation', 'select', 'Entrance animation', $t + ['options' => ['' => 'None', 'fade-in' => 'Fade in', 'fade-up' => 'Fade in up', 'fade-down' => 'Fade in down', 'fade-left' => 'Fade in left', 'fade-right' => 'Fade in right', 'zoom-in' => 'Zoom in', 'bounce-in' => 'Bounce in']]),
            self::c('_animation_delay', 'number', 'Animation delay (ms)', $t + ['min' => 0, 'max' => 5000, 'step' => 100, 'condition' => ['_animation' => ['fade-in', 'fade-up', 'fade-down', 'fade-left', 'fade-right', 'zoom-in', 'bounce-in']], 'selectors' => ['{{SELF}}' => 'animation-delay:{{VALUE}}ms']]),
            self::heading('Responsive', 'advanced'),
            self::c('_hide_desktop', 'switch', 'Hide on desktop', $t),
            self::c('_hide_tablet', 'switch', 'Hide on tablet', $t),
            self::c('_hide_mobile', 'switch', 'Hide on mobile', $t),
        ];
    }

    // ------------------------------------------------------------------
    // Structure
    // ------------------------------------------------------------------

    private static function sectionControls(): array
    {
        return [
            self::heading('Layout'),
            self::c('layout', 'choose', 'Section width', ['options' => ['' => 'Boxed', 'full' => 'Full width'], 'help' => 'Full width stretches the background edge to edge.']),
            self::c('content_width', 'select', 'Content width', ['options' => ['' => 'Boxed (default)', 'full' => 'Full width'], 'help' => 'Width of the columns inside the section.']),
            self::c('max_width', 'number', 'Boxed width (px)', ['min' => 300, 'max' => 2400, 'condition' => ['content_width' => ['']], 'selectors' => ['{{SELF}} > .ffb-container' => 'max-width:{{VALUE}}px']]),
            self::c('gap', 'number', 'Columns gap (px)', ['responsive' => true, 'min' => 0, 'max' => 200, 'selectors' => ['{{SELF}} > .ffb-container' => '--ffb-gap:{{VALUE}}px']]),
            self::c('min_height', 'number', 'Minimum height (px)', ['responsive' => true, 'min' => 0, 'max' => 2000, 'selectors' => ['{{SELF}} > .ffb-container' => 'min-height:{{VALUE}}px']]),
            self::c('valign', 'choose', 'Vertical align', ['options' => ['' => 'Stretch', 'flex-start' => 'Top', 'center' => 'Middle', 'flex-end' => 'Bottom'], 'icons' => ['' => 'fa-arrows-up-down', 'flex-start' => 'fa-arrow-up', 'center' => 'fa-grip-lines', 'flex-end' => 'fa-arrow-down'], 'selectors' => ['{{SELF}} > .ffb-container' => 'align-items:{{VALUE}}']]),
            self::c('stack_on', 'select', 'Stack columns on', ['options' => ['' => 'Mobile', 'tablet' => 'Tablet and mobile', 'none' => 'Never']]),
            self::c('reverse_stack', 'switch', 'Reverse order when stacked'),
            self::c('html_tag', 'select', 'HTML tag', ['options' => ['' => 'section', 'div' => 'div', 'header' => 'header', 'footer' => 'footer', 'aside' => 'aside', 'article' => 'article']]),
            ...self::background(),
            self::heading('Text', 'style'),
            self::c('text_color', 'color', 'Text color', ['tab' => 'style', 'selectors' => ['{{SELF}}' => 'color:{{VALUE}}']]),
            self::c('heading_color', 'color', 'Heading color', ['tab' => 'style', 'selectors' => ['{{SELF}} :is(h1,h2,h3,h4,h5,h6)' => 'color:{{VALUE}}']]),
            self::c('link_color', 'color', 'Link color', ['tab' => 'style', 'selectors' => ['{{SELF}} a:not(.ffb-btn)' => 'color:{{VALUE}}']]),
            self::c('text_align', 'choose', 'Text align', ['tab' => 'style', 'responsive' => true, 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'icons' => ['left' => 'fa-align-left', 'center' => 'fa-align-center', 'right' => 'fa-align-right'], 'selectors' => ['{{SELF}}' => 'text-align:{{VALUE}}']]),
            ...self::border(),
            ...self::advanced(),
        ];
    }

    private static function columnControls(): array
    {
        return [
            self::heading('Layout'),
            self::c('width', 'number', 'Column width (%)', ['responsive' => true, 'min' => 5, 'max' => 100, 'step' => 0.1, 'help' => 'Leave empty to share the row equally.']),
            self::c('valign', 'choose', 'Vertical align', ['options' => ['' => 'Top', 'center' => 'Middle', 'flex-end' => 'Bottom', 'space-between' => 'Space between'], 'icons' => ['' => 'fa-arrow-up', 'center' => 'fa-grip-lines', 'flex-end' => 'fa-arrow-down', 'space-between' => 'fa-arrows-up-down'], 'selectors' => ['{{SELF}} > .ffb-column-inner' => 'justify-content:{{VALUE}}']]),
            self::c('widgets_gap', 'number', 'Widgets spacing (px)', ['responsive' => true, 'min' => 0, 'max' => 200, 'selectors' => ['{{SELF}} > .ffb-column-inner' => 'gap:{{VALUE}}px']]),
            self::c('order', 'number', 'Order when stacked', ['responsive' => true, 'min' => -10, 'max' => 10, 'help' => 'Use on tablet/mobile to reorder stacked columns.', 'selectors' => ['{{SELF}}' => 'order:{{VALUE}}']]),
            ...self::background(),
            self::heading('Text', 'style'),
            self::c('text_color', 'color', 'Text color', ['tab' => 'style', 'selectors' => ['{{SELF}}' => 'color:{{VALUE}}']]),
            self::c('text_align', 'choose', 'Text align', ['tab' => 'style', 'responsive' => true, 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'icons' => ['left' => 'fa-align-left', 'center' => 'fa-align-center', 'right' => 'fa-align-right'], 'selectors' => ['{{SELF}}' => 'text-align:{{VALUE}}']]),
            ...self::border(),
            ...self::advanced(),
        ];
    }

    /** Controls every widget gets in its Advanced/Style tabs. */
    private static function widgetCommon(): array
    {
        return [
            self::heading('Box', 'advanced'),
            self::c('_bg_color', 'color', 'Background color', ['tab' => 'advanced', 'selectors' => ['{{SELF}}' => 'background-color:{{VALUE}}']]),
            self::c('_max_width', 'number', 'Max width (%)', ['tab' => 'advanced', 'responsive' => true, 'min' => 5, 'max' => 100, 'selectors' => ['{{SELF}}' => 'max-width:{{VALUE}}%']]),
            self::c('_self_align', 'choose', 'Box position', ['tab' => 'advanced', 'responsive' => true, 'options' => ['flex-start' => 'Left', 'center' => 'Center', 'flex-end' => 'Right'], 'icons' => ['flex-start' => 'fa-align-left', 'center' => 'fa-align-center', 'flex-end' => 'fa-align-right'], 'selectors' => ['{{SELF}}' => 'align-self:{{VALUE}};width:100%']]),
            ...array_map(fn ($c) => ['tab' => 'advanced'] + $c, self::border('advanced')),
            ...self::advanced(),
        ];
    }

    // ------------------------------------------------------------------
    // Widgets
    // ------------------------------------------------------------------

    private static function widgets(): array
    {
        $common = self::widgetCommon();
        $w = static fn (string $title, string $icon, string $category, array $controls, array $extra = []) => [
            'title' => $title, 'icon' => $icon, 'category' => $category,
            'controls' => [...$controls, ...$common],
        ] + $extra;

        $sizes = ['' => 'Default', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large'];

        return [
            'heading' => $w('Heading', 'fa-heading', 'basic', [
                self::c('text', 'textarea', 'Title', ['default' => 'Add your heading text here', 'max' => 500, 'inline' => true]),
                self::c('link', 'url', 'Link'),
                self::c('tag', 'select', 'HTML tag', ['default' => 'h2', 'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'div', 'p' => 'p', 'span' => 'span']]),
                self::c('size', 'select', 'Size preset', ['options' => $sizes]),
                self::c('show_in_toc', 'switch', 'Include in table of contents', ['default' => true]),
                self::align('align', '{{SELF}}'),
                self::c('color', 'color', 'Text color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-heading' => 'color:{{VALUE}}']]),
                ...self::typography('', '{{SELF}} .ffb-heading'),
                self::c('text_shadow', 'switch', 'Text shadow', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-heading' => 'text-shadow:0 2px 6px rgba(0,0,0,.35)']]),
            ]),
            'text' => $w('Text Editor', 'fa-paragraph', 'basic', [
                self::c('content', 'richtext', 'Content', ['default' => '<p>Click here to start writing. Use the toolbar to format text, add links and lists.</p>', 'inline' => true]),
                self::c('drop_cap', 'switch', 'Drop cap'),
                self::c('columns', 'number', 'Text columns', ['responsive' => true, 'min' => 1, 'max' => 4, 'selectors' => ['{{SELF}} .ffb-text' => 'column-count:{{VALUE}};column-gap:32px']]),
                self::align('align', '{{SELF}}'),
                self::c('color', 'color', 'Text color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-text' => 'color:{{VALUE}}']]),
                self::c('link_color', 'color', 'Link color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-text a' => 'color:{{VALUE}}']]),
                ...self::typography('', '{{SELF}} .ffb-text'),
            ]),
            'image' => $w('Image', 'fa-image', 'basic', [
                self::c('src', 'image', 'Choose image'),
                self::c('alt', 'text', 'Alt text', ['max' => 300]),
                self::c('caption', 'text', 'Caption', ['max' => 500]),
                self::c('link_to', 'select', 'Link', ['options' => ['' => 'None', 'file' => 'Media file (lightbox)', 'custom' => 'Custom URL']]),
                self::c('link', 'url', 'URL', ['condition' => ['link_to' => ['custom']]]),
                self::c('new_tab', 'switch', 'Open in new tab', ['condition' => ['link_to' => ['custom']]]),
                self::align('align', '{{SELF}}'),
                self::c('width', 'number', 'Width (%)', ['tab' => 'style', 'responsive' => true, 'min' => 1, 'max' => 100, 'selectors' => ['{{SELF}} img' => 'width:{{VALUE}}%']]),
                self::c('height', 'number', 'Height (px)', ['tab' => 'style', 'responsive' => true, 'min' => 10, 'max' => 2000, 'selectors' => ['{{SELF}} img' => 'height:{{VALUE}}px']]),
                self::c('object_fit', 'select', 'Object fit', ['tab' => 'style', 'options' => ['' => 'Default', 'cover' => 'Cover', 'contain' => 'Contain', 'fill' => 'Fill'], 'selectors' => ['{{SELF}} img' => 'object-fit:{{VALUE}}']]),
                self::c('img_radius', 'number', 'Image radius (px)', ['tab' => 'style', 'min' => 0, 'max' => 500, 'selectors' => ['{{SELF}} img' => 'border-radius:{{VALUE}}px']]),
                self::c('opacity', 'slider', 'Opacity (%)', ['tab' => 'style', 'min' => 0, 'max' => 100, 'map' => 'percent', 'selectors' => ['{{SELF}} img' => 'opacity:{{VALUE}}']]),
                self::c('hover', 'select', 'Hover effect', ['tab' => 'style', 'options' => ['' => 'None', 'zoom' => 'Zoom', 'grayscale' => 'Grayscale to color', 'lift' => 'Lift']]),
                self::c('caption_color', 'color', 'Caption color', ['tab' => 'style', 'selectors' => ['{{SELF}} figcaption' => 'color:{{VALUE}}']]),
            ]),
            'video' => $w('Video', 'fa-circle-play', 'media', [
                self::c('url', 'url', 'Video URL', ['help' => 'YouTube, Vimeo, TikTok, Facebook, Instagram or Spotify link.', 'default' => '']),
                self::c('aspect', 'select', 'Aspect ratio', ['default' => '16/9', 'options' => ['16/9' => '16:9', '4/3' => '4:3', '21/9' => '21:9', '1/1' => '1:1', '9/16' => '9:16 (vertical)'], 'selectors' => ['{{SELF}} iframe' => 'aspect-ratio:{{VALUE}}']]),
                self::c('caption', 'text', 'Caption', ['max' => 500]),
                self::c('radius', 'number', 'Radius (px)', ['tab' => 'style', 'min' => 0, 'max' => 100, 'selectors' => ['{{SELF}} iframe' => 'border-radius:{{VALUE}}px']]),
            ]),
            'button' => $w('Button', 'fa-hand-pointer', 'basic', [
                self::c('text', 'text', 'Text', ['default' => 'Click here', 'max' => 200, 'inline' => true]),
                self::c('link', 'url', 'Link', ['default' => '#']),
                self::c('new_tab', 'switch', 'Open in new tab'),
                self::c('icon', 'select', 'Icon', ['options' => self::ICONS]),
                self::c('icon_position', 'select', 'Icon position', ['options' => ['' => 'After', 'before' => 'Before']]),
                self::c('variant', 'select', 'Style', ['options' => ['' => 'Filled', 'outline' => 'Outline', 'text' => 'Text link']]),
                self::c('size', 'select', 'Size', ['options' => ['' => 'Medium', 'sm' => 'Small', 'lg' => 'Large', 'xl' => 'Extra large']]),
                self::c('full_width', 'switch', 'Full width'),
                self::align('align', '{{SELF}}'),
                self::c('color', 'color', 'Text color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-btn' => 'color:{{VALUE}}']]),
                self::c('bg', 'color', 'Background color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-btn' => 'background-color:{{VALUE}};border-color:{{VALUE}}']]),
                self::c('hover_color', 'color', 'Hover text color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-btn:hover' => 'color:{{VALUE}}']]),
                self::c('hover_bg', 'color', 'Hover background', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-btn:hover' => 'background-color:{{VALUE}};border-color:{{VALUE}}']]),
                self::c('radius', 'number', 'Radius (px)', ['tab' => 'style', 'min' => 0, 'max' => 100, 'selectors' => ['{{SELF}} .ffb-btn' => 'border-radius:{{VALUE}}px']]),
                ...self::typography('', '{{SELF}} .ffb-btn'),
            ]),
            'divider' => $w('Divider', 'fa-minus', 'basic', [
                self::c('style', 'select', 'Style', ['options' => ['' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'], 'selectors' => ['{{SELF}} .ffb-divider-line' => 'border-top-style:{{VALUE}}']]),
                self::c('width', 'number', 'Width (%)', ['responsive' => true, 'min' => 1, 'max' => 100, 'selectors' => ['{{SELF}} .ffb-divider' => 'width:{{VALUE}}%']]),
                self::c('align', 'choose', 'Alignment', ['options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'icons' => ['left' => 'fa-align-left', 'center' => 'fa-align-center', 'right' => 'fa-align-right']]),
                self::c('text', 'text', 'Label (optional)', ['max' => 120]),
                self::c('color', 'color', 'Color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-divider-line' => 'border-color:{{VALUE}}', '{{SELF}} .ffb-divider-label' => 'color:{{VALUE}}']]),
                self::c('weight', 'number', 'Weight (px)', ['tab' => 'style', 'min' => 1, 'max' => 20, 'selectors' => ['{{SELF}} .ffb-divider-line' => 'border-top-width:{{VALUE}}px']]),
                self::c('spacing', 'number', 'Gap (px)', ['tab' => 'style', 'responsive' => true, 'min' => 0, 'max' => 200, 'selectors' => ['{{SELF}}' => 'padding-top:{{VALUE}}px;padding-bottom:{{VALUE}}px']]),
            ]),
            'spacer' => $w('Spacer', 'fa-arrows-up-down', 'basic', [
                self::c('height', 'number', 'Space (px)', ['default' => 50, 'responsive' => true, 'min' => 0, 'max' => 1000, 'selectors' => ['{{SELF}} .ffb-spacer' => 'height:{{VALUE}}px']]),
            ]),
            'icon' => $w('Icon', 'fa-star', 'basic', [
                self::c('icon', 'select', 'Icon', ['default' => 'fa-solid fa-star', 'options' => self::ICONS]),
                self::c('view', 'select', 'View', ['options' => ['' => 'Default', 'stacked' => 'Stacked', 'framed' => 'Framed']]),
                self::c('link', 'url', 'Link'),
                self::align('align', '{{SELF}}'),
                self::c('color', 'color', 'Color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-icon' => 'color:{{VALUE}};border-color:{{VALUE}}']]),
                self::c('bg', 'color', 'Background (stacked)', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-icon--stacked' => 'background-color:{{VALUE}}']]),
                self::c('size', 'number', 'Size (px)', ['tab' => 'style', 'responsive' => true, 'min' => 6, 'max' => 300, 'selectors' => ['{{SELF}} .ffb-icon' => 'font-size:{{VALUE}}px']]),
            ]),
            'icon_box' => $w('Icon Box', 'fa-cube', 'general', [
                self::c('icon', 'select', 'Icon', ['default' => 'fa-solid fa-lightbulb', 'options' => self::ICONS]),
                self::c('title', 'text', 'Title', ['default' => 'This is the heading', 'max' => 300, 'inline' => true]),
                self::c('description', 'textarea', 'Description', ['default' => 'Describe the idea in a sentence or two.', 'max' => 2000]),
                self::c('link', 'url', 'Link'),
                self::c('position', 'choose', 'Icon position', ['options' => ['' => 'Top', 'left' => 'Left', 'right' => 'Right'], 'icons' => ['' => 'fa-arrow-up', 'left' => 'fa-arrow-left', 'right' => 'fa-arrow-right']]),
                self::c('title_tag', 'select', 'Title tag', ['default' => 'h3', 'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'div' => 'div']]),
                self::align('align', '{{SELF}}'),
                self::c('icon_color', 'color', 'Icon color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-iconbox__icon' => 'color:{{VALUE}}']]),
                self::c('icon_size', 'number', 'Icon size (px)', ['tab' => 'style', 'min' => 8, 'max' => 200, 'selectors' => ['{{SELF}} .ffb-iconbox__icon' => 'font-size:{{VALUE}}px']]),
                self::c('title_color', 'color', 'Title color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-iconbox__title' => 'color:{{VALUE}}']]),
                self::c('desc_color', 'color', 'Description color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-iconbox__desc' => 'color:{{VALUE}}']]),
                ...self::typography('title_', '{{SELF}} .ffb-iconbox__title', 'Title typography'),
            ]),
            'image_box' => $w('Image Box', 'fa-id-card', 'general', [
                self::c('src', 'image', 'Image'),
                self::c('title', 'text', 'Title', ['default' => 'This is the heading', 'max' => 300, 'inline' => true]),
                self::c('description', 'textarea', 'Description', ['default' => 'Describe the idea in a sentence or two.', 'max' => 2000]),
                self::c('link', 'url', 'Link'),
                self::align('align', '{{SELF}}'),
                self::c('img_radius', 'number', 'Image radius (px)', ['tab' => 'style', 'min' => 0, 'max' => 500, 'selectors' => ['{{SELF}} img' => 'border-radius:{{VALUE}}px']]),
                self::c('title_color', 'color', 'Title color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-imagebox__title' => 'color:{{VALUE}}']]),
                self::c('desc_color', 'color', 'Description color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-imagebox__desc' => 'color:{{VALUE}}']]),
            ]),
            'quote' => $w('Blockquote', 'fa-quote-left', 'general', [
                self::c('text', 'textarea', 'Quote', ['default' => 'The best investment you can make is in yourself.', 'max' => 3000, 'inline' => true]),
                self::c('cite', 'text', 'Author', ['default' => 'Warren Buffett', 'max' => 200]),
                self::c('variant', 'select', 'Skin', ['options' => ['' => 'Border', 'quotation' => 'Quotation mark', 'boxed' => 'Boxed', 'clean' => 'Clean']]),
                self::align('align', '{{SELF}}'),
                self::c('accent', 'color', 'Accent color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-quote' => 'border-color:{{VALUE}}', '{{SELF}} .ffb-quote::before' => 'color:{{VALUE}}']]),
                self::c('color', 'color', 'Text color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-quote p' => 'color:{{VALUE}}']]),
                ...self::typography('', '{{SELF}} .ffb-quote p'),
            ]),
            'icon_list' => $w('Icon List', 'fa-list-check', 'general', [
                self::c('items', 'repeater', 'Items', ['title_field' => 'text', 'default' => [
                    ['text' => 'List item one', 'icon' => 'fa-solid fa-check'],
                    ['text' => 'List item two', 'icon' => 'fa-solid fa-check'],
                    ['text' => 'List item three', 'icon' => 'fa-solid fa-check'],
                ], 'fields' => [
                    self::c('text', 'text', 'Text', ['max' => 500, 'default' => 'List item']),
                    self::c('icon', 'select', 'Icon', ['options' => self::ICONS, 'default' => 'fa-solid fa-check']),
                    self::c('link', 'url', 'Link'),
                ]]),
                self::c('layout', 'choose', 'Layout', ['options' => ['' => 'Default', 'inline' => 'Inline'], 'icons' => ['' => 'fa-list', 'inline' => 'fa-ellipsis']]),
                self::c('divider', 'switch', 'Divider between items'),
                self::c('icon_color', 'color', 'Icon color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-iconlist i' => 'color:{{VALUE}}']]),
                self::c('text_color', 'color', 'Text color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-iconlist li' => 'color:{{VALUE}}']]),
                self::c('space', 'number', 'Space between (px)', ['tab' => 'style', 'min' => 0, 'max' => 100, 'selectors' => ['{{SELF}} .ffb-iconlist' => 'gap:{{VALUE}}px']]),
                ...self::typography('', '{{SELF}} .ffb-iconlist li'),
            ]),
            'gallery' => $w('Gallery', 'fa-images', 'media', [
                self::c('images', 'repeater', 'Images', ['title_field' => 'alt', 'fields' => [
                    self::c('src', 'image', 'Image'),
                    self::c('alt', 'text', 'Alt text', ['max' => 300]),
                    self::c('caption', 'text', 'Caption', ['max' => 300]),
                ]]),
                self::c('columns', 'number', 'Columns', ['default' => 3, 'responsive' => true, 'min' => 1, 'max' => 8, 'selectors' => ['{{SELF}} .ffb-gallery' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr))']]),
                self::c('ratio', 'select', 'Image ratio', ['options' => ['' => 'Original', '1/1' => '1:1', '4/3' => '4:3', '3/2' => '3:2', '16/9' => '16:9', '3/4' => '3:4'], 'selectors' => ['{{SELF}} .ffb-gallery img' => 'aspect-ratio:{{VALUE}};object-fit:cover']]),
                self::c('lightbox', 'switch', 'Open in lightbox', ['default' => true]),
                self::c('show_captions', 'switch', 'Show captions'),
                self::c('gap', 'number', 'Gap (px)', ['tab' => 'style', 'responsive' => true, 'min' => 0, 'max' => 100, 'selectors' => ['{{SELF}} .ffb-gallery' => 'gap:{{VALUE}}px']]),
                self::c('radius', 'number', 'Radius (px)', ['tab' => 'style', 'min' => 0, 'max' => 100, 'selectors' => ['{{SELF}} .ffb-gallery img' => 'border-radius:{{VALUE}}px']]),
            ]),
            'accordion' => $w('Accordion', 'fa-bars-staggered', 'general', [
                self::c('items', 'repeater', 'Items', ['title_field' => 'title', 'default' => [
                    ['title' => 'Accordion item #1', 'content' => '<p>Add your accordion content here.</p>'],
                    ['title' => 'Accordion item #2', 'content' => '<p>Add your accordion content here.</p>'],
                ], 'fields' => [
                    self::c('title', 'text', 'Title', ['max' => 300, 'default' => 'Accordion item']),
                    self::c('content', 'richtext', 'Content', ['default' => '<p>Add your content here.</p>']),
                ]]),
                self::c('mode', 'select', 'Behaviour', ['options' => ['' => 'Accordion (one open)', 'toggle' => 'Toggle (many open)']]),
                self::c('open_first', 'switch', 'First item open', ['default' => true]),
                self::c('faq_schema', 'switch', 'FAQ schema (SEO)'),
                self::c('title_color', 'color', 'Title color', ['tab' => 'style', 'selectors' => ['{{SELF}} summary' => 'color:{{VALUE}}']]),
                self::c('title_bg', 'color', 'Title background', ['tab' => 'style', 'selectors' => ['{{SELF}} summary' => 'background-color:{{VALUE}}']]),
                self::c('active_color', 'color', 'Active title color', ['tab' => 'style', 'selectors' => ['{{SELF}} details[open] > summary' => 'color:{{VALUE}}']]),
                self::c('content_bg', 'color', 'Content background', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-acc__body' => 'background-color:{{VALUE}}']]),
                self::c('border_c', 'color', 'Border color', ['tab' => 'style', 'selectors' => ['{{SELF}} details' => 'border-color:{{VALUE}}']]),
            ]),
            'tabs' => $w('Tabs', 'fa-folder', 'general', [
                self::c('items', 'repeater', 'Tabs', ['title_field' => 'title', 'default' => [
                    ['title' => 'Tab #1', 'content' => '<p>Tab content one.</p>'],
                    ['title' => 'Tab #2', 'content' => '<p>Tab content two.</p>'],
                ], 'fields' => [
                    self::c('title', 'text', 'Title', ['max' => 200, 'default' => 'Tab']),
                    self::c('content', 'richtext', 'Content', ['default' => '<p>Tab content.</p>']),
                ]]),
                self::c('orientation', 'choose', 'Orientation', ['options' => ['' => 'Horizontal', 'vertical' => 'Vertical'], 'icons' => ['' => 'fa-grip-lines', 'vertical' => 'fa-grip-lines-vertical']]),
                self::c('active_color', 'color', 'Active tab color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-tabs__tab[aria-selected=true]' => 'color:{{VALUE}};border-color:{{VALUE}}']]),
                self::c('tab_color', 'color', 'Tab color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-tabs__tab' => 'color:{{VALUE}}']]),
                self::c('panel_bg', 'color', 'Panel background', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-tabs__panel' => 'background-color:{{VALUE}}']]),
            ]),
            'counter' => $w('Counter', 'fa-arrow-up-9-1', 'general', [
                self::c('start', 'number', 'Starting number', ['default' => 0, 'min' => -1e12, 'max' => 1e12]),
                self::c('end', 'number', 'Ending number', ['default' => 100, 'min' => -1e12, 'max' => 1e12, 'step' => 0.01]),
                self::c('prefix', 'text', 'Prefix', ['max' => 20]),
                self::c('suffix', 'text', 'Suffix', ['max' => 20, 'default' => '%']),
                self::c('decimals', 'number', 'Decimals', ['min' => 0, 'max' => 4]),
                self::c('separator', 'switch', 'Thousand separator', ['default' => true]),
                self::c('duration', 'number', 'Animation duration (ms)', ['default' => 2000, 'min' => 0, 'max' => 10000, 'step' => 100]),
                self::c('title', 'text', 'Title', ['default' => 'Cool number', 'max' => 200]),
                self::align('align', '{{SELF}}'),
                self::c('number_color', 'color', 'Number color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-counter__number' => 'color:{{VALUE}}']]),
                self::c('number_size', 'number', 'Number size (px)', ['tab' => 'style', 'responsive' => true, 'min' => 8, 'max' => 200, 'selectors' => ['{{SELF}} .ffb-counter__number' => 'font-size:{{VALUE}}px']]),
                self::c('title_color', 'color', 'Title color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-counter__title' => 'color:{{VALUE}}']]),
            ]),
            'progress' => $w('Progress Bar', 'fa-bars-progress', 'general', [
                self::c('title', 'text', 'Title', ['default' => 'Progress', 'max' => 200]),
                self::c('percent', 'slider', 'Percentage', ['default' => 50, 'min' => 0, 'max' => 100]),
                self::c('show_percent', 'switch', 'Display percentage', ['default' => true]),
                self::c('bar_color', 'color', 'Bar color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-progress__fill' => 'background-color:{{VALUE}}']]),
                self::c('track_color', 'color', 'Track color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-progress__track' => 'background-color:{{VALUE}}']]),
                self::c('bar_height', 'number', 'Height (px)', ['tab' => 'style', 'min' => 2, 'max' => 80, 'selectors' => ['{{SELF}} .ffb-progress__track' => 'height:{{VALUE}}px']]),
            ]),
            'testimonial' => $w('Testimonial', 'fa-comment-dots', 'general', [
                self::c('content', 'textarea', 'Content', ['default' => 'This publication changed how I think about money and technology.', 'max' => 3000, 'inline' => true]),
                self::c('image', 'image', 'Photo'),
                self::c('name', 'text', 'Name', ['default' => 'Jane Doe', 'max' => 200]),
                self::c('role', 'text', 'Title', ['default' => 'Reader', 'max' => 200]),
                self::c('rating', 'number', 'Star rating (0–5)', ['min' => 0, 'max' => 5, 'step' => 0.5]),
                self::align('align', '{{SELF}}'),
                self::c('content_color', 'color', 'Content color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-testimonial__content' => 'color:{{VALUE}}']]),
                self::c('name_color', 'color', 'Name color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-testimonial__name' => 'color:{{VALUE}}']]),
                self::c('star_color', 'color', 'Star color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-stars' => 'color:{{VALUE}}']]),
            ]),
            'callout' => $w('Alert / Callout', 'fa-circle-exclamation', 'general', [
                self::c('kind', 'select', 'Type', ['default' => 'info', 'options' => ['info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Danger', 'note' => 'Note']]),
                self::c('title', 'text', 'Title', ['default' => 'Key takeaway', 'max' => 300]),
                self::c('content', 'richtext', 'Content', ['default' => '<p>Highlight an important point for your readers.</p>']),
                self::c('show_icon', 'switch', 'Show icon', ['default' => true]),
                self::c('bg', 'color', 'Background', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-callout' => 'background-color:{{VALUE}}']]),
                self::c('accent', 'color', 'Accent', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-callout' => 'border-color:{{VALUE}}', '{{SELF}} .ffb-callout__icon' => 'color:{{VALUE}}']]),
            ]),
            'cta' => $w('Call to Action', 'fa-bullhorn', 'general', [
                self::c('title', 'text', 'Title', ['default' => 'Stay ahead of the market', 'max' => 300]),
                self::c('description', 'textarea', 'Description', ['default' => 'Get weekly insight on finance and future technology.', 'max' => 2000]),
                self::c('button_text', 'text', 'Button text', ['default' => 'Subscribe', 'max' => 100]),
                self::c('link', 'url', 'Button link', ['default' => '#']),
                self::c('image', 'image', 'Background image'),
                self::c('layout', 'choose', 'Layout', ['options' => ['' => 'Stacked', 'row' => 'Side by side'], 'icons' => ['' => 'fa-bars', 'row' => 'fa-table-columns']]),
                self::align('align', '{{SELF}} .ffb-cta'),
                self::c('bg', 'color', 'Background', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-cta' => 'background-color:{{VALUE}}']]),
                self::c('text_color', 'color', 'Text color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-cta' => 'color:{{VALUE}}']]),
                self::c('btn_bg', 'color', 'Button background', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-btn' => 'background-color:{{VALUE}};border-color:{{VALUE}}']]),
                self::c('btn_color', 'color', 'Button text', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-btn' => 'color:{{VALUE}}']]),
            ]),
            'table' => $w('Table', 'fa-table', 'general', [
                self::c('data', 'textarea', 'Rows', ['default' => "Asset | Price | Change\nBitcoin | 64,000 | +2.1%\nGold | 2,350 | -0.4%", 'max' => 20000, 'help' => 'One row per line; separate cells with |']),
                self::c('header', 'switch', 'First row is header', ['default' => true]),
                self::c('striped', 'switch', 'Striped rows', ['default' => true]),
                self::c('caption', 'text', 'Caption', ['max' => 300]),
                self::c('head_bg', 'color', 'Header background', ['tab' => 'style', 'selectors' => ['{{SELF}} th' => 'background-color:{{VALUE}}']]),
                self::c('head_color', 'color', 'Header text', ['tab' => 'style', 'selectors' => ['{{SELF}} th' => 'color:{{VALUE}}']]),
                self::c('border_c', 'color', 'Border color', ['tab' => 'style', 'selectors' => ['{{SELF}} th, {{SELF}} td' => 'border-color:{{VALUE}}']]),
            ]),
            'toc' => $w('Table of Contents', 'fa-list-ol', 'article', [
                self::c('title', 'text', 'Title', ['default' => 'In this article', 'max' => 200]),
                self::c('levels', 'select', 'Headings', ['default' => 'h2,h3', 'options' => ['h2' => 'H2 only', 'h2,h3' => 'H2 – H3', 'h2,h3,h4' => 'H2 – H4']]),
                self::c('numbered', 'switch', 'Numbered list'),
                self::c('collapsible', 'switch', 'Collapsible'),
                self::c('bg', 'color', 'Background', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-toc' => 'background-color:{{VALUE}}']]),
                self::c('link_color', 'color', 'Link color', ['tab' => 'style', 'selectors' => ['{{SELF}} .ffb-toc a' => 'color:{{VALUE}}']]),
            ]),
            'share' => $w('Share Buttons', 'fa-share-nodes', 'article', [
                self::c('networks', 'multiselect', 'Networks', ['default' => ['x', 'facebook', 'linkedin', 'whatsapp', 'email'], 'options' => ['x' => 'X (Twitter)', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'reddit' => 'Reddit', 'email' => 'Email']]),
                self::c('label', 'text', 'Label', ['default' => 'Share this article', 'max' => 100]),
                self::c('show_names', 'switch', 'Show network names'),
                self::c('skin', 'select', 'Skin', ['options' => ['' => 'Brand colors', 'minimal' => 'Minimal', 'outline' => 'Outline']]),
                self::align('align', '{{SELF}}'),
            ]),
            'code' => $w('Code Block', 'fa-code', 'general', [
                self::c('code', 'code', 'Code', ['default' => 'const growth = principal * (1 + rate) ** years;', 'max' => 50000]),
                self::c('language', 'text', 'Language label', ['max' => 40]),
                self::c('dark', 'switch', 'Dark theme', ['default' => true]),
            ]),
            'html' => $w('HTML', 'fa-file-code', 'general', [
                self::c('html', 'code', 'HTML code', ['default' => '<p>Custom HTML</p>', 'max' => 100000, 'help' => 'Scripts, styles and unsafe attributes are removed on save.']),
            ]),
            'map' => $w('Google Maps', 'fa-map-location-dot', 'media', [
                self::c('address', 'text', 'Location', ['default' => 'Nairobi, Kenya', 'max' => 300]),
                self::c('zoom', 'slider', 'Zoom', ['default' => 12, 'min' => 1, 'max' => 20]),
                self::c('height', 'number', 'Height (px)', ['default' => 300, 'responsive' => true, 'min' => 50, 'max' => 1200, 'selectors' => ['{{SELF}} iframe' => 'height:{{VALUE}}px']]),
            ]),
        ];
    }
}
