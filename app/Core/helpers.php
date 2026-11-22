<?php

function url(string $path = ''): string
{
    $base = base_url();

    $path = trim($path, '/');

    if ($path === '') {
        return $base . '/';
    }

    return $base . '/' . $path;
}

function category_url(array $category): string
{
    return url(nx_public_archive_path('category', $category['slug']));
}

/** Keep existing plain-slug articles/pages reachable when adding archive routes. */
function nx_public_archive_path(string $kind, ?string $slug = null): string
{
    static $paths = [];
    $path = $kind === 'category' ? 'category/' . rawurlencode((string)$slug) : $kind;
    if (isset($paths[$path])) {
        return $paths[$path];
    }
    $candidate = rawurldecode((string)parse_url(url($path), PHP_URL_PATH));
    $post = \app\Models\Post::findBySlug($slug ?? $kind);
    $page = $slug === null ? \app\Models\Page::findBySlug($kind) : null;
    if (($post && rawurldecode((string)parse_url(post_url($post), PHP_URL_PATH)) === $candidate) ||
        ($page && rawurldecode((string)parse_url(page_url($page), PHP_URL_PATH)) === $candidate)) {
        return $paths[$path] = 'post/archive/' . $path;
    }
    return $paths[$path] = $path;
}

function nx_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function nx_navigation(string $menu = 'header', bool $includeHomeFallback = true): void
{
    foreach (\app\Core\Design::menuLinks($menu, $includeHomeFallback) as $link) {
        echo '<a href="' . e($link['url']) . '">' . e($link['label']) . '</a>';
    }
}

function nx_design_settings(): array
{
    return \app\Core\Design::settings();
}

function nx_design_header(string $searchQuery = '', bool $includeHomeFallback = true): void
{
    $design = nx_design_settings();
    $header = $design['header'];
    $rows = $design['headerRows'];
    $draft = \app\Core\Design::isDraftPreview();
    $hasMainRow = $draft || $rows !== null;
    $mainColumns = $rows['top']['columns'] ?? ($header['placement'] === 'stack' ? 1 : 3);
    for ($column = 1; $column <= $mainColumns; $column++) {
        $hasMainRow = $hasMainRow || (bool)$design['widgets']['header' . $column];
    }
    $hasAbove = $design['headerAbove'] && ($draft || $design['widgets']['header_above']);
    $hasBelow = $rows === null && $design['headerBelow'] && ($draft || $design['widgets']['header_bottom1']);
    if ($hasMainRow || $hasAbove || $hasBelow) {
        require BASE_PATH . '/app/Views/design/header.php';
    }
}

function nx_design_background_style(): void
{
    $stylesheet = \app\Core\Design::backgroundStylesheetUrl();
    if ($stylesheet !== null) {
        echo '<link rel="stylesheet" href="' . e($stylesheet) . '">' . "\n";
    }
}

function nx_design_spacing_style(): void
{
    $layout = nx_design_settings();
    $stylesheets = [
        \app\Core\DesignSpacing::stylesheetUrl($layout['spacing']),
        \app\Core\DesignFrame::stylesheetUrl($layout['frames']),
    ];
    foreach ($stylesheets as $stylesheet) {
        if ($stylesheet !== null) {
            echo '<link rel="stylesheet" href="' . e($stylesheet) . '">' . "\n";
        }
    }
}

function nx_design_main(string $content, ?array $pageDesign = null): void
{
    require BASE_PATH . '/app/Views/design/main.php';
}

function nx_design_widgets(string $area, string $searchQuery = '', bool $includeHomeFallback = true): void
{
    $widgets = nx_design_settings()['widgets'][$area] ?? [];
    require BASE_PATH . '/app/Views/design/widgets.php';
}

function nx_design_region(string $area): void
{
    $label = \app\Core\Design::REGIONS[$area] ?? null;
    if ($label === null) {
        return;
    }

    $design = nx_design_settings();
    $layout = $design['regions'][$area];
    $draft = \app\Core\Design::isDraftPreview();
    $rows = [];
    foreach (\app\Core\Design::regionWidgetAreas($area) as $row => $slots) {
        $settings = $row === 'top' ? $layout : $layout[$row];
        if ($row !== 'top' && !$settings['enabled']) {
            continue;
        }
        $contents = [];
        foreach (array_slice($slots, 0, $settings['columns']) as $slot) {
            $contents[$slot] = '';
            if (!$draft && !$design['widgets'][$slot]) {
                continue;
            }
            ob_start();
            try {
                nx_design_widgets($slot);
                $contents[$slot] = trim(ob_get_contents());
            } finally {
                ob_end_clean();
            }
        }
        if (array_filter($contents)) {
            $rows[$row] = ['settings' => $settings, 'contents' => $contents];
        }
    }
    if ($rows) {
        require BASE_PATH . '/app/Views/design/region.php';
    }
}

function nx_design_footer(): void
{
    $design = nx_design_settings();
    $rows = $design['footerRows'];
    $above = $design['footerAbove'];
    $sections = [];
    if ($above['enabled']) {
        $visible = \app\Core\Design::isDraftPreview();
        for ($column = 1; $column <= $above['columns'] && !$visible; $column++) {
            $visible = (bool)$design['widgets']['footer_above' . $column];
        }
        if ($visible) {
            $sections['above'] = ['prefix' => 'footer_above', 'columns' => $above['columns'], 'align' => $above['align']];
        }
    }
    $sections['top'] = ['prefix' => 'footer', 'columns' => $design['footer'], 'align' => $rows['top']['align'] ?? null];
    if ($rows['split'] ?? false) {
        $sections['bottom'] = ['prefix' => 'footer_bottom'] + $rows['bottom'];
    }
    $width = $design['footerWidth'];
    require BASE_PATH . '/app/Views/design/footer.php';
}

function nx_archive_page_url(int $page, string $path = '', string $query = ''): string
{
    $params = [];
    if ($query !== '') {
        $params['q'] = $query;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    return url($path) . ($params ? '?' . http_build_query($params) : '');
}

function nx_editor_saved(): void
{
    $key = $_POST['_editor_draft_key'] ?? null;
    $prefix = 'nx-editor:' . (int)(\app\Core\Auth::user()['id'] ?? 0) . ':';
    if (is_string($key) && strlen($key) < 300 && str_starts_with($key, $prefix)) {
        $_SESSION['editor_saved_keys'][] = $key;
    }
}

function public_url(string $path = ''): string
{
    $base = base_url();

    if (!str_ends_with($base, '/public')) {
        $base .= '/public';
    }

    $path = trim($path, '/');

    if ($path === '') {
        return $base . '/';
    }

    return $base . '/' . $path;
}

function nx_asset_url(string $path): string
{
    static $versions = [];
    $path = ltrim($path, '/');
    if (!isset($versions[$path])) {
        $file = BASE_PATH . '/public/' . $path;
        $hash = is_file($file) ? hash_file('sha256', $file) : false;
        $versions[$path] = $hash === false ? '' : '?v=' . substr($hash, 0, 16);
    }
    return public_url($path) . $versions[$path];
}

function nx_theme_styles(): void
{
    foreach (\app\Core\Theme::stylesheetPaths() as $path) {
        echo '<link rel="stylesheet" href="' . e(nx_asset_url($path)) . '">' . "\n";
    }
}

function base_url(): string
{
    $base = defined('BASE_URL') ? BASE_URL : '';

    $configPath = defined('BASE_PATH') ? BASE_PATH . '/config/url.php' : '';

    if ($configPath && file_exists($configPath)) {
        $config = require $configPath;
        $mode = $config['site_url_mode'] ?? 'public';

        if ($mode === 'root') {
            return preg_replace('#/public$#', '', $base);
        }
    }

    return rtrim($base, '/');
}

function post_url(array $post): string
{
    $configPath = defined('BASE_PATH') ? BASE_PATH . '/config/url.php' : '';
    $type = 'post_slug';

    if ($configPath && file_exists($configPath)) {
        $config = require $configPath;
        $type = $config['post_url_type'] ?? 'post_slug';
    }

    $slug = $post['slug'] ?? '';

    if ($type === 'slug') {
        return url($slug);
    }

    if ($type === 'category_slug') {
        $category = $post['category_slug'] ?? 'post';
        return url($category . '/' . $slug);
    }

    return url('post/' . $slug);
}

function page_url(array $page): string
{
    $configPath = defined('BASE_PATH') ? BASE_PATH . '/config/url.php' : '';
    $type = 'page_slug';

    if ($configPath && file_exists($configPath)) {
        $config = require $configPath;
        $type = $config['page_url_type'] ?? 'page_slug';
    }

    $slug = $page['slug'] ?? '';

    if ($type === 'slug') {
        return url($slug);
    }

    return url('page/' . $slug);
}

function general_config(): array
{
    $configPath = defined('BASE_PATH')
        ? BASE_PATH . '/config/general.php'
        : '';

    $defaults = [
        'site_title' => 'My CMS',
        'timezone' => 'Asia/Tokyo',
        'site_icon' => '',
        'discourage_search_engines' => false,
    ];

    if (
        $configPath === '' ||
        !file_exists($configPath)
    ) {
        return $defaults;
    }

    $config = require $configPath;

    if (!is_array($config)) {
        return $defaults;
    }

    return array_merge(
        $defaults,
        $config
    );
}

function site_title(): string
{
    $config = general_config();

    return $config['site_title']
        ?? 'My CMS';
}

function site_icon(): string
{
    $config = general_config();

    return $config['site_icon']
        ?? '';
}

/*
 * 検索エンジン表示設定を取得
 */
function discourage_search_engines(): bool
{
    $config = general_config();

    return (bool) (
        $config['discourage_search_engines']
        ?? false
    );
}

/*
 * noindex設定を出力
 */
function nx_search_engine_meta(): void
{
    if (!discourage_search_engines()) {
        return;
    }

    ?>
    <meta
        name="robots"
        content="noindex, nofollow"
    >
    <?php
}

function redirect_to(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
 * 拡張機能の処理をフックへ登録
 */
function add_action(
    string $hookName,
    callable $callback,
    int $priority = 10
): void {
    \app\Core\Hook::addAction(
        $hookName,
        $callback,
        $priority
    );
}

/*
 * フックへ登録された処理を実行
 */
function do_action(
    string $hookName,
    mixed ...$arguments
): void {
    \app\Core\Hook::doAction(
        $hookName,
        ...$arguments
    );
}

/*
 * 公開ページのhead共通情報を出力
 */
function nx_head(?string $title = null): void
{
    $title = $title ?? site_title();
    $icon = site_icon();

    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>

    <?php if ($icon !== ''): ?>
        <link rel="icon" href="<?= e($icon) ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= e(nx_asset_url('assets/css/article-cover.css')) ?>">
    <?php if (!\app\Core\EditorPreview::active() && !\app\Core\Design::isPreview()): ?>
        <script src="<?= e(nx_asset_url('assets/js/article-cover.js')) ?>" data-nx-cover-render defer></script>
    <?php endif; ?>
    <?php if (\app\Core\EditorPreview::active()): ?>
        <link rel="stylesheet" href="<?= e(nx_asset_url('assets/css/editor-preview.css')) ?>">
    <?php endif; ?>
    <?php do_action('nx_head'); ?>
    <?php
}

function nexapress_admin_header(): void
{
    // ログイン中の管理者だけに、本体側の管理者用ヘッダーを表示する
    if (\app\Core\Design::isPreview() || \app\Core\EditorPreview::active() || !\app\Core\Auth::check()) {
        return;
    }

    ?>
    <header class="admin-preview-header">
        <div class="admin-preview-header-inner">
            <div class="admin-preview-brand">
                <a href="<?= url('') ?>">NexaPress</a>
                <span>管理者プレビュー中</span>
            </div>

            <nav class="admin-preview-nav">
                <a href="<?= url('') ?>">トップ</a>
                <a href="<?= url('admin') ?>">管理画面</a>
                <?php nx_logout_form(); ?>
            </nav>
        </div>
    </header>
    <?php
}

function nx_logout_form(): void
{
    ?>
    <form class="logout-form" action="<?= e(url('admin/logout')) ?>" method="post">
        <input type="hidden" name="_csrf_token" value="<?= e(\app\Core\Csrf::token()) ?>">
        <button type="submit">ログアウト</button>
    </form>
    <?php
}

/*
 * 投稿本文を安全化して取得
 *
 * 2.3.0より前に保存された投稿も
 * 公開時に再度安全化する。
 */
function nx_post_content(
    array $post,
    bool $expand = true
): string {
    $content = \app\Core\HtmlSanitizer::sanitize(
        (string)(
            $post['content']
            ?? ''
        ),
        true
    );
    return $expand ? \app\Core\ContentExtension::render($content, $post) : $content;
}

/*
 * 投稿一覧用の抜粋を取得
 */
function nx_post_excerpt(
    array $post,
    int $length = 120
): string {
    $length = max(
        1,
        min($length, 1000)
    );

    $excerpt = trim(
        (string)(
            $post['excerpt']
            ?? ''
        )
    );

    /*
     * 保存済み抜粋がない場合は
     * 本文から自動生成
     */
    if ($excerpt === '') {
        $excerpt = html_entity_decode(
            strip_tags(
                nx_post_content($post, false)
            ),
            ENT_QUOTES |
            ENT_HTML5,
            'UTF-8'
        );
    }

    $excerpt = str_replace(
        "\u{00A0}",
        ' ',
        $excerpt
    );

    $excerpt = preg_replace(
        '/\s+/u',
        ' ',
        $excerpt
    ) ?? '';

    $excerpt = trim($excerpt);

    if ($excerpt === '') {
        return '';
    }

    if (
        function_exists('mb_strlen') &&
        function_exists('mb_substr')
    ) {
        if (
            mb_strlen(
                $excerpt,
                'UTF-8'
            ) <= $length
        ) {
            return $excerpt;
        }

        return mb_substr(
            $excerpt,
            0,
            $length,
            'UTF-8'
        ) . '…';
    }

    if (strlen($excerpt) <= $length) {
        return $excerpt;
    }

    return substr(
        $excerpt,
        0,
        $length
    ) . '…';
}

/*
 * 投稿のアイキャッチ画像URLを取得
 */
function nx_post_featured_image_url(
    array $post
): string {
    $path = trim(
        (string)(
            $post[
                'featured_media_path'
            ] ?? ''
        )
    );

    if ($path === '') {
        return '';
    }

    return public_url($path);
}

/*
 * アイキャッチ画像の代替テキスト
 */
function nx_post_featured_image_alt(
    array $post
): string {
    $alt = trim(
        (string)(
            $post[
                'featured_media_description'
            ] ?? ''
        )
    );

    if ($alt !== '') {
        return $alt;
    }

    $alt = trim(
        (string)(
            $post[
                'featured_media_title'
            ] ?? ''
        )
    );

    if ($alt !== '') {
        return $alt;
    }

    return trim(
        (string)(
            $post['title']
            ?? ''
        )
    );
}

/*
 * 投稿の表示日を取得
 */
function nx_post_date(
    array $post,
    string $format = 'Y/m/d'
): string {
    $date = trim(
        (string)(
            $post['published_at']
            ??
            $post['created_at']
            ?? ''
        )
    );

    if ($date === '') {
        return '';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date(
        $format,
        $timestamp
    );
}

/*
 * トップページのページ送りURL
 */
function nx_home_page_url(
    int $page
): string {
    $page = max(1, $page);

    $params = $_GET;

    unset($params['page']);

    if ($page > 1) {
        $params['page'] = $page;
    }

    $query = http_build_query(
        $params
    );

    $homeUrl = url('');

    return $query !== ''
        ? $homeUrl . '?' . $query
        : $homeUrl;
}
