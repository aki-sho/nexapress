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
    \app\Models\CategoryPage::find(1) !== null ||
    \app\Models\Page::all() !== [] || \app\Models\PageRevision::allForPage(1) !== [] ||
    \app\Core\Design::snapshot('default')['layout']['columns'] !== 'none' ||
    \app\Core\PageLayout::settings(1) !== ['enabled' => false, 'width' => 'inherit', 'sections' => []] ||
    \app\Models\Media::folders() !== [] || \app\Models\Media::all(0) !== [] ||
    \app\Core\PageLayout::settings(1, 'post') !== ['enabled' => false, 'width' => 'inherit', 'sections' => []]) {
    throw new RuntimeException('Fresh install schema is incomplete.');
}
echo "PASS: real fresh installation creates all core feature tables\n";
