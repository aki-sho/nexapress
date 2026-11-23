<?php

namespace app\Core;

final class RequestSize
{
    public static function phpLimit(string $setting): ?int
    {
        $value = trim((string)ini_get($setting));
        if (!preg_match('/\A([0-9]+)\s*([KMG]?)\z/i', $value, $parts)) {
            return null;
        }

        $multiplier = match (strtoupper($parts[2])) {
            'K' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            default => 1,
        };
        $bytes = (float)$parts[1] * $multiplier;
        return $bytes > 0 ? ($bytes >= PHP_INT_MAX ? PHP_INT_MAX : (int)$bytes) : null;
    }

    public static function requirePostWithinLimit(): void
    {
        $limit = self::phpLimit('post_max_size');
        $length = (string)($_SERVER['CONTENT_LENGTH'] ?? '');
        if ($limit === null || !ctype_digit($length) || (float)$length <= $limit) {
            return;
        }

        http_response_code(413);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=UTF-8');
        echo '送信データがPHPの受信上限（' . number_format($limit)
            . 'バイト）を超えています。編集画面に戻って入力内容を確認してください。';
        exit;
    }
}
