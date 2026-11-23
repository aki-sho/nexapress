<?php

namespace app\Core;

final class LayoutPlacement
{
    public const OPTIONS = [
        'flex-start' => '先頭寄せ',
        'center' => '中央寄せ',
        'flex-end' => '末尾寄せ',
        'space-between' => '両端揃え',
        'space-around' => '両側に配分',
        'space-evenly' => 'すべて等間隔',
    ];

    private const TEXT_TAGS = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    public static function valid(mixed $value): bool
    {
        return is_string($value) && isset(self::OPTIONS[$value]);
    }

    public static function width(mixed $value): int
    {
        if (!is_int($value) || $value < 1 || $value > 100) {
            throw new \InvalidArgumentException('幅は1〜100％で指定してください。');
        }

        return $value;
    }

    public static function attributes(array $settings): string
    {
        $alignment = $settings['align'];
        return ' data-nx-placement="' . e($alignment) . '"' .
            (is_int($settings['width'] ?? null) ? ' data-nx-layout-width="' . $settings['width'] . '"' : '');
    }

    public static function articleAttribute(\DOMElement $element, string $name, string $value): bool
    {
        $classes = self::classes($element);
        $isGroup = array_intersect($classes, ['nx-article-columns', 'nx-article-buttons', 'nx-article-cover']) !== [];
        $isText = in_array($element->tagName, self::TEXT_TAGS, true);
        $isButton = in_array('nx-article-button', $classes, true) && $element->tagName === 'a';
        return match ($name) {
            'data-nx-placement' => ($isGroup || $isText) && self::valid($value),
            'data-nx-layout-width' => $isGroup || $isText,
            'data-nx-layout-gap' => $isGroup,
            'data-nx-column-area' => $element->tagName === 'div'
                && in_array($value, ['above', 'below'], true)
                && $element->parentNode instanceof \DOMElement
                && in_array('nx-article-columns', self::classes($element->parentNode), true),
            'data-nx-column-width' => in_array('nx-article-column', $classes, true),
            'data-nx-columns-align' => in_array('nx-article-columns', $classes, true)
                && in_array($value, ['start', 'center', 'end', 'stretch'], true),
            'data-nx-buttons-edge' => in_array('nx-article-buttons', $classes, true) && $value === 'bottom',
            'data-nx-button-kind' => $isButton && in_array($value, ['fill', 'outline', 'text'], true),
            'data-nx-button-size' => $isButton && in_array($value, ['small', 'medium', 'large'], true),
            'data-nx-button-icon' => $isButton && in_array($value, ['arrow', 'external', 'download'], true),
            'data-nx-button-radius', 'data-nx-button-background', 'data-nx-button-color', 'data-nx-button-border' =>
                $isButton,
            default => true,
        };
    }

    // Public rendering derives track sizes from the sanitized column widths.
    public static function renderColumnTracks(\DOMElement $group): void
    {
        if (!in_array('nx-article-columns', self::classes($group), true)) {
            return;
        }

        $columns = [];
        $hasSharedArea = false;
        foreach ($group->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->tagName !== 'div') {
                continue;
            }
            $hasSharedArea = $hasSharedArea || $child->hasAttribute('data-nx-column-area');
            if (in_array('nx-article-column', self::classes($child), true) && !$child->hasAttribute('data-nx-column-area')) {
                $columns[] = $child;
            }
        }
        $count = count($columns);
        if (!$hasSharedArea || $count < 2 || $count > 4) {
            return;
        }

        $weights = array_map(static fn(\DOMElement $column): float => $column->hasAttribute('data-nx-column-width')
            ? (int)$column->getAttribute('data-nx-column-width') / 100 : 1 / $count, $columns);
        $total = max(1, array_sum($weights));
        foreach ($weights as $index => $weight) {
            $group->setAttribute('data-nx-column-track-' . ($index + 1), number_format($weight / $total, 8, '.', ''));
        }
    }

    private static function classes(\DOMElement $element): array
    {
        return preg_split('/\s+/', $element->getAttribute('class')) ?: [];
    }
}
