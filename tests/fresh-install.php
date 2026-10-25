<?php
$source = dirname(__DIR__);
$existing = $argv[1];
$root = dirname($existing) . '/fresh';
mkdir($root . '/config', 0755, true);
mkdir($root . '/storage', 0755, true);
define('BASE_PATH', $root);
define('BASE_URL', '');
spl_autoload_register(static function (string $class) use ($source): void {
    $file = $source . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) require_once $file;
});
require $source . '/app/Core/helpers.php';
$db = require $existing . '/config/config.php';
$db['table_prefix'] = 'fresh_';
if (!\app\Core\Installer::configureDatabase($db) || !\app\Core\Installer::installSite([
    'admin_username' => 'first-admin', 'admin_password' => 'Test-Password-2026',
    'admin_email' => 'first@example.test', 'site_title' => '新規インストール',
])) {
    throw new RuntimeException('Fresh install failed.');
}
if (!\app\Core\Installer::isInstalled() || \app\Models\Navigation::all() !== [] ||
    \app\Models\Page::all() !== [] || \app\Models\PageRevision::allForPage(1) !== []) {
    throw new RuntimeException('Fresh install schema is incomplete.');
}
echo "PASS: real fresh installation creates all core feature tables\n";
