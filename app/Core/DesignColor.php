<?php

namespace app\Core;

final class DesignColor
{
    public static function normalize(mixed $value): ?string
    {
        $channels = self::channels($value);
        if ($channels === null) {
            return null;
        }
        [$red, $green, $blue, $alpha] = $channels;
        $alpha = round($alpha, 4);
        if ($alpha === 1.0) {
            return sprintf('#%02X%02X%02X', $red, $green, $blue);
        }
        $opacity = rtrim(rtrim(number_format($alpha, 4, '.', ''), '0'), '.');
        return "rgba($red, $green, $blue, $opacity)";
    }

    public static function foreground(string $color, string $background = '#F5F6F8'): string
    {
        $channels = self::channels($color);
        $behind = self::channels($background);
        $alpha = $channels[3];
        $luminance = 0;
        foreach ([0.2126, 0.7152, 0.0722] as $index => $weight) {
            $base = $behind[$index] * $behind[3] + [245, 246, 248][$index] * (1 - $behind[3]);
            $value = ($channels[$index] * $alpha + $base * (1 - $alpha)) / 255;
            $luminance += $weight * ($value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4);
        }
        return $luminance > 0.179 ? '#000000' : '#FFFFFF';
    }

    private static function channels(mixed $value): ?array
    {
        if (!is_string($value) || strlen($value) > 64) {
            return null;
        }
        $code = trim($value);
        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/iD', $code, $matches)) {
            $hex = strlen($matches[1]) < 5
                ? implode('', array_map(static fn(string $digit): string => str_repeat($digit, 2), str_split($matches[1])))
                : $matches[1];
            $parts = array_map('hexdec', str_split($hex, 2));
            return [$parts[0], $parts[1], $parts[2], ($parts[3] ?? 255) / 255];
        }
        if (!preg_match('/^(rgba?)\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(\d*\.?\d+))?\s*\)$/iD', $code, $parts) ||
            (strtolower($parts[1]) === 'rgba') !== isset($parts[5])) {
            return null;
        }
        $rgb = array_map('intval', array_slice($parts, 2, 3));
        $alpha = isset($parts[5]) ? (float)$parts[5] : 1.0;
        return max($rgb) <= 255 && $alpha <= 1.0 ? [...$rgb, $alpha] : null;
    }
}
