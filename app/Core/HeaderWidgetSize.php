<?php

namespace app\Core;

use InvalidArgumentException;

final class HeaderWidgetSize
{
    public const OPTIONS = [
        'site_icon' => ['label' => 'アイコンのサイズ', 'default' => 32, 'min' => 16, 'max' => 96],
        'site_name' => ['label' => '文字サイズ', 'default' => 24, 'min' => 12, 'max' => 48],
    ];

    public static function validate(string $type, mixed $size): int
    {
        $options = self::OPTIONS[$type];
        if (!is_int($size) || $size < $options['min'] || $size > $options['max']) {
            throw new InvalidArgumentException(
                $options['label'] . 'は' . $options['min'] . '〜' . $options['max'] . 'pxの整数で入力してください。'
            );
        }
        return $size;
    }

    public static function stylesheetUrl(array $widgets): ?string
    {
        $sizes = [];
        foreach ($widgets as $column) {
            foreach ($column as $widget) {
                if (isset(self::OPTIONS[$widget['type']], $widget['size'])) {
                    $size = self::validate($widget['type'], $widget['size']);
                    $sizes[$widget['type']][$size] = $size;
                }
            }
        }
        return $sizes === [] ? null
            : url('nexapress/assets/header-widget-size') . '?' . http_build_query($sizes, '', '&', PHP_QUERY_RFC3986);
    }

    public static function stylesheet(array $query): string
    {
        $css = '';
        foreach ($query as $type => $values) {
            $options = self::OPTIONS[$type] ?? null;
            if ($options === null || !is_array($values) || count($values) > $options['max'] - $options['min'] + 1) {
                throw new InvalidArgumentException('ヘッダーのサイズ設定を確認してください。');
            }
            foreach ($values as $value) {
                $size = self::validate($type, is_string($value) && ctype_digit($value) ? (int)$value : $value);
                $attribute = '[data-nx-brand-size="' . $size . '"]';
                $css .= $type === 'site_icon'
                    ? ".site-header .site-icon$attribute { width: {$size}px; height: {$size}px; }\n"
                    : ".site-header .site-title$attribute { font-size: {$size}px; }\n";
            }
        }
        return $css;
    }
}
