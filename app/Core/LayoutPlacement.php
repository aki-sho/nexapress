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
        $classes = preg_split('/\s+/', $element->getAttribute('class')) ?: [];
        $isGroup = array_intersect($classes, ['nx-article-columns', 'nx-article-buttons', 'nx-article-cover']) !== [];
        $isButton = in_array('nx-article-button', $classes, true) && $element->tagName === 'a';
        return match ($name) {
            'data-nx-placement' => $isGroup && self::valid($value),
            'data-nx-layout-width', 'data-nx-layout-gap' => $isGroup,
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
}
