<?php
// Only disposable, randomly named test databases and temporary site copies.
$fixture = realpath($argv[1] ?? '');
$temporary = realpath(sys_get_temp_dir());
if (!$fixture || !$temporary || !str_starts_with($fixture, $temporary . DIRECTORY_SEPARATOR)
    || !str_starts_with(basename(dirname($fixture)), 'nexapress-updater-')) {
    throw new RuntimeException('Refusing a fixture outside the updater test directory.');
}
$password = getenv('NEXAPRESS_TEST_PASSWORD');
if ($password === false) throw new RuntimeException('A disposable database password is required.');
$host = getenv('NEXAPRESS_TEST_HOST') ?: '127.0.0.1;port=13316';
$user = getenv('NEXAPRESS_TEST_USER') ?: 'root';
$server = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $user, $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$mode = $argv[2] ?? 'state';
$statePath = $fixture . '/updater-test-state.json';
if ($mode === 'setup') {
    $database = 'nexapress_updater_' . bin2hex(random_bytes(8));
    $server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    file_put_contents($statePath, json_encode(['database' => $database]));
    file_put_contents($fixture . '/config/config.php', '<?php return ' . var_export([
        'db_host' => $host, 'db_name' => $database, 'db_user' => $user, 'db_pass' => $password, 'table_prefix' => 'nx_',
    ], true) . ';');
}
$testState = json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^nexapress_updater_[a-f0-9]{16}$/', $testState['database'])) {
    throw new RuntimeException('Refusing a database outside the updater test namespace.');
}
if ($mode === 'cleanup') {
    $server->exec('DROP DATABASE `' . $testState['database'] . '`');
    echo "Disposable updater database removed.\n";
    exit;
}
define('BASE_PATH', $fixture);
define('BASE_URL', '');
spl_autoload_register(static function (string $class) use ($fixture): void {
    $file = $fixture . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) require_once $file;
});
require $fixture . '/app/Core/helpers.php';
if ($mode === 'setup') {
    if (!\app\Core\Installer::installSite([
        'admin_username' => 'admin', 'admin_password' => 'Test-Password-2026',
        'admin_email' => 'admin@example.test', 'site_title' => 'Retained updater test site',
    ])) throw new RuntimeException('The original release installer failed.');
    $pdo = \app\Core\Database::connect();
    foreach (glob($fixture . '/database/migrations/*.php') as $file) {
        $pdo->prepare('INSERT IGNORE INTO nx_nexapress_migrations (migration) VALUES (?)')->execute([basename($file, '.php')]);
    }
    $pdo->exec("INSERT INTO nx_pages (title,slug,content,status,user_id) VALUES ('Retained page','retained-page','<p>Retained page content</p>','published',1)");
    $pdo->exec("INSERT INTO nx_pages (title,slug,content,status,user_id) VALUES ('Retained shared page','retained-shared-page','<p>Retained shared page content</p>','published',1)");
    $pdo->exec("INSERT INTO nx_posts (title,slug,content,status,user_id) VALUES ('Retained post','retained-post','<p>Retained post content</p>','published',1)");
    $pdo->exec("INSERT INTO nx_media (title, original_name, file_name, file_path, mime_type, file_size, file_type, user_id)
        VALUES ('Retained media', 'retained.txt', 'retained.txt', 'uploads/retained.txt', 'text/plain', 15, 'document', 1)");
    $pdo->exec("INSERT INTO nx_extension_settings (extension_key,is_enabled) VALUES ('retained-extension',1)");
    if ($pdo->query("SHOW TABLES LIKE 'nx_page_layouts'")->fetchColumn()) {
        \app\Core\PageLayout::save(1, [
            'enabled' => true, 'width' => 'full', 'sections' => [[
                'background' => 'full', 'color' => '#654155', 'widthMode' => 'percent', 'percent' => 80,
                'columns' => 2, 'share' => 50, 'gap' => 10,
                'groups' => [[['type' => 'page_title'], ['type' => 'page_content']], []],
            ]],
        ]);
        $pdo->prepare('INSERT INTO nx_page_layouts (page_id, settings) VALUES (2, ?)')
            ->execute([json_encode(['enabled' => false, 'width' => 'inherit', 'sections' => []], JSON_THROW_ON_ERROR)]);
    }
    $installedVersion = require $fixture . '/config/version.php';
    if (version_compare($installedVersion['version'], '2.20.0', '>=') &&
        version_compare($installedVersion['version'], '2.21.0', '<')) {
        foreach (['default', 'monoedge'] as $theme) {
            $design = \app\Core\Design::snapshot($theme);
            $rows = \app\Core\Design::headerRowDefaults();
            $rows['split'] = true;
            $rows['top']['columns'] = 2;
            $rows['bottom']['columns'] = 2;
            $rows['positions'] = ['brand' => ['row' => 'top', 'column' => 2],
                'menu' => ['row' => 'bottom', 'column' => 1], 'search' => ['row' => 'bottom', 'column' => 2]];
            $design['layout']['headerRows'] = $rows;
            $design['layout']['header']['logo'] = false;
            $design['layout']['headerAbove'] = true;
            $text = ['type' => 'text', 'title' => 'Retained widget', 'text' => 'Retained header content', 'menu' => 'footer'];
            $design['layout']['widgets']['header_above'] = [$text];
            $design['layout']['widgets']['header_bottom2'] = array_fill(0, 12, $text);
            if (!\app\Core\Design::publish(\app\Core\Design::validate($design), $design['revision'])) {
                throw new RuntimeException('Could not seed the legacy header design.');
            }
        }
    }
    if (version_compare($installedVersion['version'], '2.21.0', '>=') &&
        version_compare($installedVersion['version'], '2.24.0', '<')) {
        $design = \app\Core\Design::snapshot('default');
        $design['layout']['footer'] = 3;
        $design['layout']['footerRows'] = ['split' => true, 'top' => ['align' => 'flex-end'],
            'bottom' => ['columns' => 2, 'align' => 'center']];
        foreach (['footer2' => 'body', 'footer_bottom2' => 'lower row', 'footer_before' => 'outside'] as $area => $label) {
            $design['layout']['widgets'][$area] = [
                ['type' => 'text', 'title' => '', 'text' => 'Retained footer ' . $label, 'menu' => 'footer'],
            ];
        }
        if (version_compare($installedVersion['version'], '2.23.0', '>=')) {
            $design['layout']['regions']['header_after'] = ['direction' => 'row', 'align' => 'center'];
            $design['layout']['regions']['footer_before'] = ['direction' => 'row', 'align' => 'flex-end'];
            $design['layout']['widgets']['header_after'] = [
                ['type' => 'text', 'title' => '', 'text' => 'Retained header outside', 'menu' => 'header'],
            ];
        }
        if (!\app\Core\Design::publish(\app\Core\Design::validate($design), $design['revision'])) {
            throw new RuntimeException('Could not seed the legacy footer design.');
        }
    }
}
if ($mode === 'restore') {
    \app\Core\UpdateBackup::restore($argv[3]);
}
$pdo = \app\Core\Database::connect();
$version = require $fixture . '/config/version.php';
$layouts = [];
foreach (['page', 'post'] as $kind) {
    $name = 'nx_' . $kind . '_layouts';
    $layouts[$kind] = $pdo->query("SHOW TABLES LIKE '{$name}'")->fetchColumn()
        ? $pdo->query("SELECT * FROM {$name} ORDER BY {$kind}_id")->fetchAll(PDO::FETCH_ASSOC) : [];
}
echo json_encode([
    'version' => $version['version'],
    'posts' => $pdo->query('SELECT id,title,slug,content,status,user_id FROM nx_posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    'pages' => $pdo->query('SELECT id,title,slug,content,status,user_id FROM nx_pages ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    'users' => $pdo->query('SELECT id,username,email,password_hash,role FROM nx_users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    'extensions' => $pdo->query('SELECT extension_key,is_enabled FROM nx_extension_settings ORDER BY extension_key')->fetchAll(PDO::FETCH_ASSOC),
    'media' => $pdo->query('SELECT id,title,file_path,mime_type,user_id FROM nx_media ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    'layouts' => $layouts,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
