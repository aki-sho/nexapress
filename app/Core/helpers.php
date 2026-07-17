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
    $configPath = defined('BASE_PATH') ? BASE_PATH . '/config/general.php' : '';

    if ($configPath && file_exists($configPath)) {
        return require $configPath;
    }

    return [
        'site_title' => 'My CMS',
        'timezone' => 'Asia/Tokyo',
        'site_icon' => '',
    ];
}

function site_title(): string
{
    $config = general_config();

    return $config['site_title'] ?? 'My CMS';
}

function site_icon(): string
{
    $config = general_config();

    return $config['site_icon'] ?? '';
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