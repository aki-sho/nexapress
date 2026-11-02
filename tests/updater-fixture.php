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
    $pdo->exec("INSERT INTO nx_posts (title,slug,content,status,user_id) VALUES ('Retained post','retained-post','<p>Retained post content</p>','published',1)");
    $pdo->exec("INSERT INTO nx_extension_settings (extension_key,is_enabled) VALUES ('retained-extension',1)");
}
if ($mode === 'restore') {
    \app\Core\UpdateBackup::restore($argv[3]);
}
$pdo = \app\Core\Database::connect();
$version = require $fixture . '/config/version.php';
echo json_encode([
    'version' => $version['version'],
    'posts' => $pdo->query('SELECT id,title,slug,content,status,user_id FROM nx_posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    'pages' => $pdo->query('SELECT id,title,slug,content,status,user_id FROM nx_pages ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    'users' => $pdo->query('SELECT id,username,email,password_hash,role FROM nx_users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    'extensions' => $pdo->query('SELECT extension_key,is_enabled FROM nx_extension_settings ORDER BY extension_key')->fetchAll(PDO::FETCH_ASSOC),
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
