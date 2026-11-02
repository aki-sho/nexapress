<?php
namespace app\Core;

final class Debug
{
    private const MAX_BYTES = 2097152;
    public static function enabled(): bool
    {
        $path = BASE_PATH . '/config/debug.php';
        if (!is_file($path)) { return false; }
        $config = require $path;
        return is_array($config) && ($config['enabled'] ?? false) === true;
    }
    public static function sanitize(array $context, int $depth = 0): array
    {
        if ($depth > 5) { return ['truncated' => true]; }
        $result = [];
        foreach (array_slice($context, 0, 50, true) as $key => $value) {
            $name = (string)$key;
            if (preg_match('/password|passwd|secret|token|authorization|cookie|api.?key|credential|^(params|arguments)$/i', $name)) {
                $result[$key] = '[redacted]'; continue;
            }
            if (is_array($value)) { $result[$key] = self::sanitize($value, $depth + 1); continue; }
            if (is_string($value)) {
                if (preg_match('/uri|url|referer|referrer/i', $name)) {
                    $path = parse_url($value, PHP_URL_PATH); $value = is_string($path) ? $path : '';
                }
                $value = preg_replace('/[\x00-\x1f\x7f]/', '', $value) ?? '';
                $result[$key] = substr($value, 0, 2048);
            } elseif (is_scalar($value) || $value === null) { $result[$key] = $value; }
            else { $result[$key] = '[unsupported]'; }
        }
        return $result;
    }
    public static function log(string $message, array $context = []): void
    {
        if (!self::enabled()) { return; }
        $directory = BASE_PATH . '/storage/logs';
        if (is_link($directory) || !is_dir($directory) && !@mkdir($directory, 0700, true)) { return; }
        $path = $directory . '/debug.log';
        if (is_link($path) || is_link($path . '.lock')) { return; }
        $lock = @fopen($path . '.lock', 'c');
        if (!$lock) { return; }
        try {
            if (!flock($lock, LOCK_EX)) { return; }
            clearstatcache(true, $path);
            if (is_file($path) && (filesize($path) >= self::MAX_BYTES || date('Ymd', filemtime($path)) !== date('Ymd'))) {
                if (!@rename($path, $directory . '/debug-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.log')) { return; }
            }
            $archives = glob($directory . '/debug-*.log') ?: [];
            usort($archives, static fn($a, $b) => filemtime($b) <=> filemtime($a));
            foreach ($archives as $index => $archive) {
                if (!is_link($archive) && ($index >= 2 || filemtime($archive) < time() - 14 * 86400)) { @unlink($archive); }
            }
            $message = substr(preg_replace('/[\x00-\x1f\x7f]/', '', $message) ?? '', 0, 500);
            $json = json_encode(self::sanitize($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . ' ' . substr((string)$json, 0, 8192) . PHP_EOL;
            if (is_file($path) && filesize($path) + strlen($line) > self::MAX_BYTES) { return; }
            if (@file_put_contents($path, $line, FILE_APPEND) !== false) { @chmod($path, 0600); }
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
