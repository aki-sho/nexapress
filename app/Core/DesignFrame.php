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

    public const SHADOW_DEFAULT = [
        'x' => 0, 'y' => 4, 'blur' => 12, 'spread' => 0, 'color' => 'rgba(0, 0, 0, 0.12)',
    ];
    public const CARD_SELECTOR = '.site-main .post-card, .site-main .post-detail';

    private const SHADOW_THEME = ['mode' => 'theme', 'value' => null];
    private const SHADOW_FIELDS = ['shadow', 'cardShadow'];
    private const DEFAULT = ['mode' => 'theme', 'line' => null, 'radius' => null, 'shadow' => self::SHADOW_THEME];
    private const SELECTORS = [
        'header' => '.site-header, .site-header[data-design-width]',
        'body' => '.site-main, .site-main[data-content-width], .site-main.nx-section-page',
        'footer' => '.site-footer, .site-footer[data-design-width]',
    ];

    public static function defaults(): array
    {
        $frames = array_fill_keys(array_keys(self::AREAS), self::DEFAULT);
        $frames['body']['cardShadow'] = self::SHADOW_THEME;
        return $frames;
    }

    public static function validate(mixed $input): array
    {
        if (!is_array($input) || array_diff_key($input, self::AREAS)) {
            throw new InvalidArgumentException('枠を設定する場所を確認してください。');
        }
        $result = self::defaults();
        foreach ($input as $area => $frame) {
            $mode = $frame['mode'] ?? null;
            $default = $result[$area];
            if (!is_array($frame) || array_diff_key($frame, $default) || !is_string($mode) || !isset(self::MODES[$mode])) {
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
            if ($mode !== 'theme') {
                $result[$area] = ['mode' => $mode, 'line' => $line, 'radius' => $radius] + $default;
            }
            foreach (self::SHADOW_FIELDS as $key) {
                if (array_key_exists($key, $frame)) {
                    $result[$area][$key] = self::validateShadow($frame[$key]);
                }
            }
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

    private static function validateShadow(mixed $shadow): array
    {
        $mode = $shadow['mode'] ?? null;
        if (!is_array($shadow) || array_diff_key($shadow, self::SHADOW_THEME) || !is_string($mode) || !isset(self::MODES[$mode])) {
            throw new InvalidArgumentException('影の表示設定を確認してください。');
        }
        $value = $shadow['value'] ?? null;
        if ($value !== null) {
            $color = DesignColor::normalize($value['color'] ?? null);
            if (!is_array($value) || array_diff_key($value, self::SHADOW_DEFAULT) ||
                array_diff_key(self::SHADOW_DEFAULT, $value) || $color === null) {
                throw new InvalidArgumentException('影の数値と色を確認してください。');
            }
            foreach (['x', 'y', 'blur', 'spread'] as $dimension) {
                $value[$dimension] = self::pixels($value[$dimension], $dimension === 'blur' ? 0 : -self::MAX_PIXELS);
            }
            $value['color'] = $color;
        } elseif ($mode === 'custom') {
            throw new InvalidArgumentException('影の数値と色を確認してください。');
        }
        return $mode === 'theme' ? self::SHADOW_THEME : ['mode' => $mode, 'value' => $value];
    }

    private static function pixels(mixed $value, int $minimum = 0): int|float
    {
        if (is_string($value) && preg_match('/^-?\d{1,4}(?:\.\d{1,3})?$/D', $value)) {
            $value = (float)$value;
        }
        if ((!is_int($value) && !is_float($value)) || !is_finite($value) ||
            $value < $minimum || $value > self::MAX_PIXELS || round($value, 3) != $value) {
            throw new InvalidArgumentException('枠の数値は' . $minimum . '〜' . self::MAX_PIXELS . 'で、小数第3位まで入力できます。');
        }
        return (int)$value == $value ? (int)$value : $value;
    }

    public static function stylesheetUrl(array $frames): ?string
    {
        $active = [];
        foreach ($frames as $area => $frame) {
            $overrides = [];
            if ($frame['mode'] === 'none') {
                $overrides = ['mode' => 'none'];
            } elseif ($frame['mode'] === 'custom' && ($frame['line'] !== null || $frame['radius'] !== null)) {
                $overrides = ['mode' => 'custom', 'line' => $frame['line'], 'radius' => $frame['radius']];
            }
            foreach (self::SHADOW_FIELDS as $key) {
                $shadow = $frame[$key] ?? self::SHADOW_THEME;
                if ($shadow['mode'] !== 'theme') {
                    $overrides[$key] = $shadow['mode'] === 'none' ? ['mode' => 'none'] : $shadow;
                }
            }
            if ($overrides) {
                $active[$area] = $overrides + ['mode' => 'theme'];
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
            $shadow = self::shadowRule($frame['shadow']);
            if ($shadow !== null) {
                $rules[] = $shadow;
            }
            if ($rules) {
                $css .= self::SELECTORS[$area] . ' { ' . implode(' ', $rules) . " }\n";
            }
            if ($area === 'body') {
                $cardShadow = self::shadowRule($frame['cardShadow']);
                if ($cardShadow !== null) {
                    $css .= self::CARD_SELECTOR . ' { ' . $cardShadow . " }\n";
                }
            }
        }
        return $css;
    }

    private static function shadowRule(array $shadow): ?string
    {
        if ($shadow['mode'] === 'theme') {
            return null;
        }
        if ($shadow['mode'] === 'none') {
            return 'box-shadow: none;';
        }
        ['x' => $x, 'y' => $y, 'blur' => $blur, 'spread' => $spread, 'color' => $color] = $shadow['value'];
        return "box-shadow: {$x}px {$y}px {$blur}px {$spread}px $color;";
    }
}
