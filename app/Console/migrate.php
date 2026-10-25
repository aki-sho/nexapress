<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('BASE_PATH', dirname(__DIR__, 2));
define('BASE_URL', '');
if (!is_file(BASE_PATH . '/config/config.php')) {
    fwrite(STDERR, "DB設定がありません。新規サイトはブラウザのインストーラーを使用してください。\n");
    exit(1);
}
spl_autoload_register(static function (string $class): void {
    $file = BASE_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) require_once $file;
});
require BASE_PATH . '/app/Core/helpers.php';
try {
    $executed = \app\Core\Migrator::run(BASE_PATH . '/database/migrations');
    echo $executed ? 'DB更新完了：' . implode(', ', $executed) . PHP_EOL : "DBは更新済みです。\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
