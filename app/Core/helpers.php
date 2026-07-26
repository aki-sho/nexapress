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

    <?php do_action('nx_head'); ?>
    <?php
}

function nexapress_admin_header(): void
{
    // ログイン中の管理者だけに、本体側の管理者用ヘッダーを表示する
    if (empty($_SESSION['user'])) {
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
                <a href="<?= url('admin/logout') ?>">ログアウト</a>
            </nav>
        </div>
    </header>
    <?php
}

/*
 * 投稿本文を安全化して取得
 *
 * 2.3.0より前に保存された投稿も
 * 公開時に再度安全化する。
 */
function nx_post_content(
    array $post
): string {
    return \app\Core\HtmlSanitizer::sanitize(
        (string)(
            $post['content']
            ?? ''
        )
    );
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
                nx_post_content($post)
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