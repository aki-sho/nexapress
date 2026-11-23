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
foreach (['posts', 'pages', 'post_revisions'] as $name) {
    $pdo->exec("ALTER TABLE nx_{$name} MODIFY COLUMN content TEXT NOT NULL");
}
foreach (['administrator' => 'admin', 'editor' => 'editor', 'author' => 'author', 'viewer' => 'viewer'] as $role => $username) {
    \app\Models\User::create(['username' => $username, 'name' => $username, 'email' => $username . '@example.test',
        'password_hash' => password_hash('Test-Password-2026', PASSWORD_DEFAULT), 'role' => $role]);
}
$pdo->exec("INSERT INTO nx_pages (title, slug, content, status, user_id) VALUES ('既存ページ', 'legacy', '<p>既存の内容</p>', 'published', 1)");
$pdo->exec("INSERT INTO nx_posts (title, slug, content, status, user_id) VALUES ('既存投稿', 'legacy-post', '<p>既存投稿の本文</p>', 'published', 1)");
mkdir($fixture . '/public/uploads/media', 0755, true);
$legacyMediaSize = file_put_contents($fixture . '/public/uploads/media/legacy-media.txt', 'previously uploaded media');
$pdo->exec("INSERT INTO nx_media (title, original_name, file_name, file_path, mime_type, file_size, file_type, user_id)
    VALUES ('既存メディア', 'legacy-media.txt', 'legacy-media.txt', 'uploads/media/legacy-media.txt',
    'text/plain', {$legacyMediaSize}, 'document', 1)");
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
$expected = ['2.4.0', '2.5.0', '2.7.0', '2.9.0', '2.10.0', '2.33.0'];
check(\app\Core\Migrator::run($fixture . '/database/migrations') === ($from22 ? ['2.3.0', ...$expected] : $expected),
    $from22 ? 'upgrade from 2.2.0' : 'upgrade from 2.3.0');
check(\app\Models\Page::find(1)['content'] === '<p>既存の内容</p>', 'migration preserves existing content');
check(\app\Models\Post::findBySlug('legacy-post')['content'] === '<p>既存投稿の本文</p>', 'migration preserves existing posts');
check(\app\Core\Migrator::run($fixture . '/database/migrations') === [], 'migration is idempotent');
\app\Core\SiteSchema::ensure($pdo);
\app\Core\SiteSchema::ensureMediaFolders($pdo);
$legacyLayout = ['enabled' => false, 'width' => 'inherit', 'sections' => []];
$pdo->prepare('INSERT INTO nx_page_layouts (page_id, settings) VALUES (1, ?)')
    ->execute([json_encode($legacyLayout, JSON_THROW_ON_ERROR)]);
check(\app\Core\PageLayout::snapshot(1)['overrides'] === ['format' => 2],
    'unused layouts saved before inheritance adopt common settings');
$legacyLayout['width'] = 'wide';
check(\app\Core\PageLayout::overrides($legacyLayout) === ['format' => 2, 'width' => 'wide'],
    'unused legacy layouts preserve an explicitly selected body width');
$explicitDisabled = ['format' => 2, 'enabled' => false, 'sectionCount' => 0];
check(\app\Core\PageLayout::overrides($explicitDisabled) === $explicitDisabled,
    'new explicit disabled layouts continue to override common settings');
$legacyLayout['sections'] = [\app\Core\PageLayout::sectionDefaults()];
check(\app\Core\PageLayout::overrides($legacyLayout)['enabled'] === false,
    'configured legacy sections retain an intentional disabled state');
check(count(\app\Models\Page::published()) === 1, 'fresh-install schema helper is idempotent');
$legacyMedia = \app\Models\Media::all(0);
check(count($legacyMedia) === 1 && $legacyMedia[0]['file_path'] === 'uploads/media/legacy-media.txt',
    'existing media is uncategorized and keeps its URL after migration');
$previousDesign = \app\Core\Design::settings();
unset($previousDesign['width']);
unset($previousDesign['widgets']['header_after'], $previousDesign['widgets']['footer_before']);
$previousDesign['widgets']['footer1'] = [];
$pdo->prepare('UPDATE nx_site_design SET settings = ? WHERE id = 1')
    ->execute([json_encode(['themes' => ['default' => $previousDesign]], JSON_THROW_ON_ERROR)]);
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
$limit = \app\Core\ArticleContent::MAX_BYTES;
$oversized = \app\Core\PageValidator::validate(['title' => '長い本文', 'slug' => 'oversized-page', 'content' => str_repeat('x', $limit + 1)]);
check(count($oversized['errors']) === 1 && str_contains($oversized['errors'][0], '6,553,500'), 'oversized fixed-page content is rejected');
$schema = $pdo->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('nx_posts', 'nx_pages', 'nx_post_revisions', 'nx_page_revisions') AND COLUMN_NAME = 'content'")
    ->fetchAll(PDO::FETCH_COLUMN);
check(count($schema) === 4 && array_unique($schema) === ['mediumtext'], 'content columns support extended storage after migration');
check($validation['errors'] === [] && !str_contains($validation['data']['content'], '<script'), 'page content is sanitized');
$alignedBody = '<p class="intro" style="text-align: left; color: #112233; text-align: center">中央揃え</p>';
$storedBody = \app\Core\HtmlSanitizer::sanitize($alignedBody);
$displayBody = nx_post_content(['content' => $alignedBody], false);
check(str_contains($storedBody, 'text-align: center') && !str_contains($storedBody, 'nx-content-align-')
    && str_contains($displayBody, 'class="intro nx-content-align-center"')
    && str_contains($displayBody, 'data-nx-display-style="color: #112233"')
    && !str_contains($displayBody, ' style=') && !str_contains($displayBody, 'text-align:'),
    'public rendering serves saved alignment and text color without inline style attributes');
$displayOnlyAttributes = '<p data-nx-display-style="color: #ffffff" style="color: #112233; font-size: 42px; font-weight: 900">色</p>';
check(!str_contains(\app\Core\HtmlSanitizer::sanitize($displayOnlyAttributes), 'data-nx-display-style')
    && str_contains(nx_post_content(['content' => $displayOnlyAttributes], false),
        'data-nx-display-style="color: #112233; font-size: 42px; font-weight: 900"'),
    'public formatting is derived from saved and validated declarations');
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
