<?php

namespace App\Services\PageBuilder;

use App\Services\HtmlSanitizer;
use Illuminate\Support\Str;

/**
 * Validates untrusted builder JSON against the Schema and returns a clean
 * layout containing only known elements, known settings and typed values.
 *
 * Layout shape:
 *   { version: 1, sections: [ { id, settings, columns: [ { id, settings, widgets: [ { id, type, settings } ] } ] } ] }
 */
final class LayoutNormalizer
{
    public const VERSION = 1;

    public const MAX_SECTIONS = 200;

    public const MAX_COLUMNS = 6;

    public const MAX_WIDGETS_PER_COLUMN = 100;

    public const MAX_REPEATER_ITEMS = 60;

    /** @var array<string, true> */
    private array $usedIds = [];

    public static function normalize(mixed $layout): array
    {
        return (new self)->layout($layout);
    }

    /** Normalize a list of sections (e.g. a saved section template). */
    public static function normalizeSections(mixed $sections): array
    {
        return (new self)->layout(['sections' => $sections])['sections'];
    }

    private function layout(mixed $layout): array
    {
        $sections = is_array($layout) && is_array($layout['sections'] ?? null) ? $layout['sections'] : [];
        $out = [];

        foreach (array_slice(array_values($sections), 0, self::MAX_SECTIONS) as $section) {
            if (is_array($section)) {
                $out[] = $this->section($section);
            }
        }

        return ['version' => self::VERSION, 'sections' => $out];
    }

    private function section(array $section): array
    {
        $columns = [];
        foreach (array_slice(array_values(is_array($section['columns'] ?? null) ? $section['columns'] : []), 0, self::MAX_COLUMNS) as $column) {
            if (is_array($column)) {
                $columns[] = $this->column($column);
            }
        }

        if ($columns === []) {
            $columns[] = $this->column([]);
        }

        return [
            'id' => $this->id($section['id'] ?? null),
            'settings' => $this->settings(Schema::controls('section'), $section['settings'] ?? []),
            'columns' => $columns,
        ];
    }

    private function column(array $column): array
    {
        $widgets = [];
        foreach (array_slice(array_values(is_array($column['widgets'] ?? null) ? $column['widgets'] : []), 0, self::MAX_WIDGETS_PER_COLUMN) as $widget) {
            if (is_array($widget) && is_string($widget['type'] ?? null) && ($controls = Schema::controls('widget', $widget['type'])) !== null) {
                $widgets[] = [
                    'id' => $this->id($widget['id'] ?? null),
                    'type' => $widget['type'],
                    'settings' => $this->settings($controls, $widget['settings'] ?? []),
                ];
            }
        }

        return [
            'id' => $this->id($column['id'] ?? null),
            'settings' => $this->settings(Schema::controls('column'), $column['settings'] ?? []),
            'widgets' => $widgets,
        ];
    }

    private function id(mixed $id): string
    {
        if (! is_string($id) || ! preg_match('/^[a-z0-9]{4,16}$/', $id) || isset($this->usedIds[$id])) {
            do {
                $id = strtolower(Str::random(8));
            } while (isset($this->usedIds[$id]) || ! preg_match('/^[a-z0-9]{8}$/', $id));
        }

        $this->usedIds[$id] = true;

        return $id;
    }

    /**
     * Keep only known, valid settings. Missing content fields fall back to
     * their schema default; style fields without a value are simply omitted.
     */
    private function settings(array $controls, mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $out = [];

        foreach ($controls as $control) {
            if ($control['type'] === 'heading') {
                continue;
            }

            $name = $control['name'];

            if (! array_key_exists($name, $raw)) {
                if (array_key_exists('default', $control) && $control['default'] !== null) {
                    $default = $this->value($control, $control['default']);
                    if ($default !== null) {
                        $out[$name] = $default;
                    }
                }

                continue;
            }

            $value = $this->value($control, $raw[$name]);
            if ($value !== null) {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    private function value(array $control, mixed $value): mixed
    {
        if (! empty($control['responsive']) && in_array($control['type'], ['number', 'slider', 'select', 'choose', 'dimensions'], true)) {
            // A responsive value is { desktop, tablet, mobile }; a plain value means desktop.
            $isDeviceMap = is_array($value) && array_intersect(array_keys($value), Schema::DEVICES) !== [];
            $value = $isDeviceMap ? $value : ['desktop' => $value];
            $out = [];
            foreach (Schema::DEVICES as $device) {
                if (array_key_exists($device, $value)) {
                    $clean = $this->scalar($control, $value[$device]);
                    if ($clean !== null) {
                        $out[$device] = $clean;
                    }
                }
            }

            return $out === [] ? null : $out;
        }

        return $this->scalar($control, $value);
    }

    private function scalar(array $control, mixed $value): mixed
    {
        return match ($control['type']) {
            'text' => $this->text($value, $control['max'] ?? 1000, $control['pattern'] ?? null, false),
            'textarea' => $this->text($value, $control['max'] ?? 5000, null, true),
            'code' => is_string($value) ? mb_substr(str_replace("\0", '', $value), 0, $control['max'] ?? 50000) : null,
            'richtext' => is_string($value) ? $this->richtext($value) : null,
            'url' => self::url($value),
            'image' => self::mediaUrl($value),
            'color' => self::color($value),
            'number', 'slider' => $this->number($value, $control),
            'select', 'choose' => $this->option($value, $control['options']),
            'switch' => is_bool($value) ? $value : (in_array($value, [1, '1', 'true', 'on', 'yes'], true) ? true : (in_array($value, [0, '0', 'false', '', 'off', 'no', null], true) ? false : null)),
            'multiselect' => is_array($value) ? array_values(array_unique(array_filter(array_map(fn ($v) => $this->option($v, $control['options']), $value), fn ($v) => $v !== null && $v !== ''))) : null,
            'dimensions' => $this->dimensions($value, $control),
            'repeater' => $this->repeater($value, $control),
            default => null,
        };
    }

    private function text(mixed $value, int $max, ?string $pattern, bool $multiline): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = (string) $value;
        $value = $multiline
            ? preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value)
            : preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
        $value = mb_substr((string) $value, 0, $max);

        if ($pattern !== null && $value !== '' && ! preg_match('/'.$pattern.'/', $value)) {
            return null;
        }

        return $value;
    }

    private function richtext(string $value): string
    {
        if (strlen($value) > 200_000) {
            $value = mb_strcut($value, 0, 200_000);
        }

        return HtmlSanitizer::clean($value);
    }

    private function number(mixed $value, array $control): int|float|null
    {
        if ($value === '' || $value === null || is_bool($value) || ! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        if (! is_finite($number)) {
            return null;
        }

        $number = max((float) ($control['min'] ?? -1e6), min((float) ($control['max'] ?? 1e6), $number));
        $step = (float) ($control['step'] ?? 1);
        $decimals = $step > 0 && $step < 1 ? strlen(rtrim(substr(sprintf('%.4F', $step), 2), '0')) : 0;
        $number = round($number, $decimals);

        return floor($number) == $number && abs($number) < PHP_INT_MAX ? (int) $number : $number;
    }

    private function option(mixed $value, array $options): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = (string) $value;
        foreach (array_keys($options) as $key) {
            if ((string) $key === $value) {
                return $value;
            }
        }

        return null;
    }

    private function dimensions(mixed $value, array $control): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $out = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $n = $this->number($value[$side] ?? null, $control);
            if ($n !== null) {
                $out[$side] = $n;
            }
        }

        return $out === [] ? null : $out;
    }

    private function repeater(mixed $value, array $control): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $items = [];
        foreach (array_slice(array_values($value), 0, self::MAX_REPEATER_ITEMS) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $clean = $this->settings($control['fields'], $item);
            $clean['_id'] = $this->id($item['_id'] ?? null);
            $items[] = $clean;
        }

        return $items;
    }

    // ------------------------------------------------------------------
    // Shared validators (also used by the renderer)
    // ------------------------------------------------------------------

    /** Link URL: http(s), mailto, tel, relative path or fragment. */
    public static function url(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strlen($value) > 2000 || preg_match('/[\x00-\x20"\'<>\\\\`]/', $value)) {
            return null;
        }

        return preg_match('~^(https?://[^/?#]+|mailto:|tel:\+?[0-9]|/(?!/)|#|\?)~i', $value) ? $value : null;
    }

    /** Media URL safe to use in src="" and CSS url(): http(s) or site-relative. */
    public static function mediaUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return strlen($value) <= 2000 && preg_match('~^(https?://[^\s"\'()<>\\\\`]+|/(?!/)[^\s"\'()<>\\\\`]*)$~i', $value) ? $value : null;
    }

    public static function color(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        return preg_match('/^(#[0-9a-f]{3,4}|#[0-9a-f]{6}|#[0-9a-f]{8}|transparent|currentcolor|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d{1,3})\s*)?\)|hsla?\(\s*\d{1,3}\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%\s*(,\s*(0|1|0?\.\d{1,3})\s*)?\))$/', $value)
            ? $value : null;
    }
}
