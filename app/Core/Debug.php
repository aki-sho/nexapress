<?php

namespace app\Core;

class Debug
{
    public static function enabled(): bool
    {
        $configPath = BASE_PATH . '/config/debug.php';

        if (!file_exists($configPath)) {
            return false;
        }

        $config = require $configPath;

        return !empty($config['enabled']);
    }

    public static function log(string $message, array $context = []): void
    {
        if (!self::enabled()) {
            return;
        }

        $logPath = BASE_PATH . '/storage/logs/debug.log';

        $date = date('Y-m-d H:i:s');

        $line = '[' . $date . '] ' . $message;

        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line .= PHP_EOL;

        file_put_contents($logPath, $line, FILE_APPEND);
    }
}