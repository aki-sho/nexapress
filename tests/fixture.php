<?php
// Run only against a disposable MySQL/MariaDB instance; the database name is generated here.
$source = dirname(__DIR__);
$fixture = $argv[1] ?? '';
if (!is_dir($fixture) || !str_starts_with(realpath($fixture), realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Fixture must be an existing directory inside the system temporary directory.');
}
$password = getenv('NEXAPRESS_TEST_PASSWORD');
if ($password === false) {
    throw new RuntimeException('Set NEXAPRESS_TEST_PASSWORD for the disposable database.');
}
$host = getenv('NEXAPRESS_TEST_HOST') ?: '127.0.0.1;port=13316';
$user = getenv('NEXAPRESS_TEST_USER') ?: 'root';
$connection = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $user, $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$statePath = $fixture . '/test-state.json';
if (($argv[2] ?? '') === 'cleanup') {
    $state = json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
    if (!preg_match('/^nexapress_test_[a-f0-9]{16}$/', $state['database'])) {
        throw new RuntimeException('Refusing to remove a database outside the test namespace.');
    }
    $connection->exec('DROP DATABASE `' . $state['database'] . '`');
    echo "Test database removed.\n";
    exit;
}
$database = 'nexapress_test_' . bin2hex(random_bytes(8));
$connection->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
file_put_contents($statePath, json_encode(['database' => $database]));
$config = ['db_host' => $host, 'db_name' => $database, 'db_user' => $user, 'db_pass' => $password, 'table_prefix' => 'nx_'];
file_put_contents($fixture . '/config/config.php', '<?php return ' . var_export($config, true) . ';');
define('BASE_PATH', $fixture);
define('BASE_URL', '');
spl_autoload_register(static function (string $class) use ($fixture): void {
    $path = $fixture . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) require_once $path;
});
require $fixture . '/app/Core/helpers.php';
$pdo = \app\Core\Database::connect();
$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . "\n";
}
// Recreate the unmodified 2.3.0 schema, then migrate real existing content.
$create = new ReflectionMethod(\app\Core\Installer::class, 'createTables');
$create->setAccessible(true);
$create->invoke(null, $pdo, 'nx_');
foreach (['administrator' => 'admin', 'editor' => 'editor', 'author' => 'author', 'viewer' => 'viewer'] as $role => $username) {
    \app\Models\User::create(['username' => $username, 'name' => $username, 'email' => $username . '@example.test',
        'password_hash' => password_hash('Test-Password-2026', PASSWORD_DEFAULT), 'role' => $role]);
}
$pdo->exec("INSERT INTO nx_pages (title, slug, content, status, user_id) VALUES ('既存ページ', 'legacy', '<p>既存の内容</p>', 'published', 1)");
$pdo->exec("INSERT INTO nx_posts (title, slug, content, status, user_id) VALUES ('既存投稿', 'legacy-post', '<p>既存投稿の本文</p>', 'published', 1)");
$from22 = ($argv[2] ?? '') === '2.2';
if ($from22) {
    $pdo->exec('DROP TABLE nx_post_revisions');
    $keys = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nx_posts'
        AND COLUMN_NAME = 'featured_media_id' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($keys as $key) {
        $pdo->exec('ALTER TABLE nx_posts DROP FOREIGN KEY `' . str_replace('`', '``', $key) . '`');
    }
    $pdo->exec('ALTER TABLE nx_posts DROP COLUMN excerpt, DROP COLUMN featured_media_id, DROP COLUMN deleted_at');
}
foreach ($from22 ? ['1.7.0', '2.0.0', '2.2.0'] : ['1.7.0', '2.0.0', '2.2.0', '2.3.0'] as $version) {
    $pdo->prepare('INSERT INTO nx_nexapress_migrations (migration) VALUES (?)')->execute([$version]);
}
check(\app\Core\Migrator::run($fixture . '/database/migrations') === ($from22 ? ['2.3.0', '2.4.0'] : ['2.4.0']), $from22 ? 'upgrade from 2.2.0' : 'upgrade from 2.3.0');
check(\app\Models\Page::find(1)['content'] === '<p>既存の内容</p>', 'migration preserves existing content');
check(\app\Models\Post::findBySlug('legacy-post')['content'] === '<p>既存投稿の本文</p>', 'migration preserves existing posts');
check(\app\Core\Migrator::run($fixture . '/database/migrations') === [], 'migration is idempotent');
\app\Core\SiteSchema::ensure($pdo);
check(count(\app\Models\Page::published()) === 1, 'fresh-install schema helper is idempotent');
\app\Models\Category::create(['name' => 'ニュース', 'slug' => 'news']);
for ($i = 0; $i < 12; $i++) {
    \app\Models\Post::create(['title' => '公開記事 ' . $i, 'slug' => 'article-' . $i, 'content' => '<p>検索対象</p>',
        'excerpt' => '', 'status' => 'published', 'category_id' => 1, 'user_id' => 1]);
}
$hiddenId = \app\Models\Post::create(['title' => '非公開シークレット', 'slug' => 'secret', 'content' => '<p>検索対象</p>', 'excerpt' => '', 'status' => 'draft', 'user_id' => 1]);
$trashId = \app\Models\Post::create(['title' => 'ゴミ箱シークレット', 'slug' => 'trashed', 'content' => '<p>検索対象</p>', 'excerpt' => '', 'status' => 'published', 'user_id' => 1]);
\app\Models\Post::delete($trashId);
check(\app\Models\Post::countPublicArchive(1, '検索対象') === 12, 'public search excludes drafts and trash');
check(\app\Models\Post::countPublicArchive(null, '%') === 0, 'LIKE wildcards are literal');
\app\Models\Post::create(['title' => '旧検索スラッグ', 'slug' => 'search', 'content' => '<p>以前からある検索という名前の記事</p>', 'excerpt' => '', 'status' => 'published', 'user_id' => 1]);
check(nx_public_archive_path('search') === 'post/archive/search', 'existing plain-slug content keeps its URL');
check(count(\app\Models\Post::publicArchive(1, '', 10, 10)) === 2, 'category pagination');
$validation = \app\Core\PageValidator::validate(['title' => '安全なページ', 'slug' => 'safe', 'content' => '<p>本文</p><script>alert(1)</script>', 'status' => 'draft']);
check($validation['errors'] === [] && !str_contains($validation['data']['content'], '<script'), 'page content is sanitized');
$id = \app\Models\Page::create($validation['data'] + ['user_id' => 1]);
\app\Models\PageRevision::create(\app\Models\Page::find($id), 1);
\app\Models\Page::update($id, array_merge($validation['data'], ['title' => '変更後', 'status' => 'published']));
check(count(\app\Models\PageRevision::allForPage($id)) === 1, 'page snapshots');
for ($i = 0; $i < 52; $i++) {
    \app\Models\PageRevision::create(\app\Models\Page::find($id), 1);
}
check(count(\app\Models\PageRevision::allForPage($id)) === 50, 'page history retains only the latest 50 revisions');
\app\Models\Page::delete($id);
check(\app\Models\Page::findBySlug('safe') === null, 'trashed page is not public');
\app\Models\Page::restore($id);
check(\app\Models\Page::find($id)['status'] === 'draft', 'trash restores as a draft');
\app\Models\Page::destroy($id);
check(\app\Models\Page::find($id) !== null, 'permanent deletion refuses an active page');
\app\Models\Page::delete($id);
\app\Models\Page::destroy($id);
check(\app\Models\PageRevision::allForPage($id) === [], 'permanent deletion cascades page history');
check(!\app\Models\Navigation::safeUrl('javascript:alert(1)') && !\app\Models\Navigation::safeUrl('//evil.test') &&
    !\app\Models\Navigation::safeUrl('/\\evil.test'), 'navigation rejects unsafe URLs');
check(\app\Models\Navigation::safeUrl('/posts') && \app\Models\Navigation::safeUrl('https://example.test/'), 'navigation accepts supported URLs');
check(\app\Core\ExtensionCompatibility::issues(['requires' => ['php' => '99.0']], '2.4.0', '8.2.0') !== [], 'incompatible PHP is rejected');
check(\app\Core\ExtensionCompatibility::issues(['requires' => ['nexapress' => '2.3.0', 'php' => '>=8.0']], '2.4.0', '8.2.0') === [], 'minimum-version compatibility');
check(\app\Core\ExtensionCompatibility::issues(['requires' => ['php' => '^8.0']], '2.4.0', '8.2.0') !== [], 'unknown constraints fail closed');
file_put_contents($fixture . '/storage/installed.lock', date('c'));
file_put_contents($statePath, json_encode(['database' => $database, 'checks' => $checks]));
echo 'Database checks passed: ' . $checks . "\n";
