<?php

namespace app\Core;

use InvalidArgumentException;

final class DesignFrame
{
    public const AREAS = ['header' => 'header', 'body' => 'columns', 'footer' => 'footer'];
    public const MODES = ['theme' => 'テーマの既定', 'none' => 'なし', 'custom' => '指定'];
    public const STYLES = ['solid' => '実線', 'dashed' => '破線', 'dotted' => '点線'];
    public const MAX_PIXELS = DesignSpacing::MAX_PIXELS;
    public const LINE_DEFAULT = [
        'style' => 'solid', 'color' => '#CBD5E1',
        'widths' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
    ];

    private const DEFAULT = ['mode' => 'theme', 'line' => null, 'radius' => null];
    private const SELECTORS = [
        'header' => '.site-header, .site-header[data-design-width]',
        'body' => '.site-main, .site-main[data-content-width], .site-main.nx-section-page',
        'footer' => '.site-footer, .site-footer[data-design-width]',
    ];

    public static function defaults(): array
    {
        return array_fill_keys(array_keys(self::AREAS), self::DEFAULT);
    }

    public static function validate(mixed $input): array
    {
        if (!is_array($input) || array_diff_key($input, self::AREAS)) {
            throw new InvalidArgumentException('枠を設定する場所を確認してください。');
        }
        $result = self::defaults();
        foreach ($input as $area => $frame) {
            $mode = $frame['mode'] ?? null;
            if (!is_array($frame) || array_diff_key($frame, self::DEFAULT) || !is_string($mode) || !isset(self::MODES[$mode])) {
                throw new InvalidArgumentException('枠の表示設定を確認してください。');
            }
            $line = $frame['line'] ?? null;
            $radius = $frame['radius'] ?? null;
            if ($line !== null) {
                $line = self::validateLine($line);
            }
            if ($radius !== null) {
                $radius = self::pixels($radius);
            }
            $result[$area] = $mode === 'theme' ? self::DEFAULT : ['mode' => $mode, 'line' => $line, 'radius' => $radius];
        }
        return $result;
    }

    private static function validateLine(mixed $line): array
    {
        $style = $line['style'] ?? null;
        $color = DesignColor::normalize($line['color'] ?? null);
        $widths = $line['widths'] ?? null;
        $sides = self::LINE_DEFAULT['widths'];
        if (!is_array($line) || array_diff_key($line, self::LINE_DEFAULT) || !is_string($style) || !isset(self::STYLES[$style]) ||
            $color === null || !is_array($widths) || array_diff_key($widths, $sides) || array_diff_key($sides, $widths)) {
            throw new InvalidArgumentException('枠線の種類・色・太さを確認してください。');
        }
        foreach ($sides as $side => $_) {
            $sides[$side] = self::pixels($widths[$side]);
        }
        return ['style' => $style, 'color' => $color, 'widths' => $sides];
    }

    private static function pixels(mixed $value): int|float
    {
        if (is_string($value) && preg_match('/^\d{1,4}(?:\.\d{1,3})?$/D', $value)) {
            $value = (float)$value;
        }
        if ((!is_int($value) && !is_float($value)) || !is_finite($value) ||
            $value < 0 || $value > self::MAX_PIXELS || round($value, 3) != $value) {
            throw new InvalidArgumentException('枠の数値は0〜' . self::MAX_PIXELS . 'で、小数第3位まで入力できます。');
        }
        return (int)$value == $value ? (int)$value : $value;
    }

    public static function stylesheetUrl(array $frames): ?string
    {
        $active = [];
        foreach ($frames as $area => $frame) {
            if ($frame['mode'] === 'none') {
                $active[$area] = ['mode' => 'none'];
            } elseif ($frame['mode'] === 'custom' && ($frame['line'] !== null || $frame['radius'] !== null)) {
                $active[$area] = $frame;
            }
        }
        return $active ? url('nexapress/assets/site-frame') . '?' . http_build_query($active, '', '&', PHP_QUERY_RFC3986) : null;
    }

    public static function stylesheet(array $query): string
    {
        $css = '';
        foreach (self::validate($query) as $area => $frame) {
            $rules = [];
            if ($frame['mode'] === 'none') {
                $rules[] = 'border-style: none;';
            } elseif ($frame['mode'] === 'custom') {
                if ($frame['line'] !== null) {
                    ['style' => $style, 'color' => $color, 'widths' => $widths] = $frame['line'];
                    $sizes = implode(' ', array_map(static fn(int|float $value): string => $value . 'px', $widths));
                    $rules[] = "border-style: $style; border-color: $color; border-width: $sizes;";
                }
                if ($frame['radius'] !== null) {
                    $rules[] = "border-radius: {$frame['radius']}px;";
                }
            }
            if ($rules) {
                $css .= self::SELECTORS[$area] . ' { ' . implode(' ', $rules) . " }\n";
            }
        }
        return $css;
    }
}
