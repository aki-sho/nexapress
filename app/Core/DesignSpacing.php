<?php

namespace app\Core;

use InvalidArgumentException;

final class DesignSpacing
{
    public const AREAS = ['header' => 'header', 'body' => 'columns', 'footer' => 'footer'];
    public const MAX_PIXELS = 9999;

    private const PROPERTIES = [
        'marginTop' => 'margin-top',
        'marginBottom' => 'margin-bottom',
        'paddingTop' => 'padding-top',
        'paddingBottom' => 'padding-bottom',
    ];

    private const SELECTORS = [
        'header' => '.site-header',
        'body' => '.site-main, .site-main.nx-section-page',
        'footer' => '.site-footer',
    ];

    public static function defaults(): array
    {
        return array_fill_keys(array_keys(self::AREAS), array_fill_keys(array_keys(self::PROPERTIES), null));
    }

    public static function validate(mixed $input): array
    {
        if (!is_array($input) || array_diff_key($input, self::AREAS)) {
            throw new InvalidArgumentException('上下の余白を設定する場所を確認してください。');
        }
        $result = self::defaults();
        foreach ($input as $area => $values) {
            if (!is_array($values) || array_diff_key($values, self::PROPERTIES)) {
                throw new InvalidArgumentException('上下の余白の設定を確認してください。');
            }
            foreach ($values as $key => $value) {
                if ($value === null) {
                    continue;
                }
                if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) ||
                    (int)$value < 0 || (int)$value > self::MAX_PIXELS) {
                    throw new InvalidArgumentException('上下の余白は0〜' . self::MAX_PIXELS . 'の整数で入力してください。');
                }
                $result[$area][$key] = (int)$value;
            }
        }
        return $result;
    }

    public static function stylesheetUrl(array $spacing): ?string
    {
        $query = http_build_query($spacing, '', '&', PHP_QUERY_RFC3986);
        return $query === '' ? null : url('nexapress/assets/site-spacing') . '?' . $query;
    }

    public static function stylesheet(array $query): string
    {
        $css = '';
        foreach (self::validate($query) as $area => $spacing) {
            $rules = [];
            foreach (self::PROPERTIES as $key => $property) {
                if ($spacing[$key] !== null) {
                    $rules[] = "$property: {$spacing[$key]}px;";
                }
            }
            if (!$rules) {
                continue;
            }
            $css .= self::SELECTORS[$area] . ' { ' . implode(' ', $rules) . " }\n";
            if ($area !== 'body') {
                // Apply the requested padding at the wrapper edge, keeping the space between rows.
                foreach (['Top' => 'first', 'Bottom' => 'last'] as $side => $position) {
                    if ($spacing['padding' . $side] !== null) {
                        $property = self::PROPERTIES['padding' . $side];
                        $css .= ".site-$area > .site-$area-inner:$position-child { $property: 0; }\n";
                    }
                }
            }
        }
        return $css;
    }
}
