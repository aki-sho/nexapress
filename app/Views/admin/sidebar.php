<?php

use app\Core\Extension;
use app\Core\Permission;

$iconPaths = [
    'dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
    'posts' => 'M5 3h14v18H5z M8 8h8 M8 12h8 M8 16h5',
    'add' => 'M12 5v14 M5 12h14',
    'pages' => 'M8 3h8l4 4v14H8z M16 3v5h4 M4 7v14 M11 12h6 M11 16h6',
    'categories' => 'M3 4h8l10 10-7 7L3 10z M7 8h.1',
    'media' => 'M3 4h18v16H3z M3 17l5-5 4 4 3-3 6 6 M15 8h.1',
    'design' => 'M4 5h16v11H4z M8 20h8 M12 16v4 M4 9h16',
    'navigation' => 'M4 6h16 M4 12h16 M4 18h16',
    'extensions' => 'M4 8h5V6a3 3 0 0 1 6 0v2h5v4h-2a3 3 0 0 0 0 6h2v3H4v-5h2a2 2 0 0 0 0-4H4z',
    'users' => 'M14 8a4 4 0 1 1-8 0 4 4 0 0 1 8 0z M2 21v-2a5 5 0 0 1 5-5h6a5 5 0 0 1 5 5v2 M17 5a3 3 0 0 1 0 6 M20 15a4 4 0 0 1 2 4v2',
    'updates' => 'M21 4v6h-6 M21 10a9 9 0 0 0-16-4 M3 20v-6h6 M3 14a9 9 0 0 0 16 4',
    'settings' => 'M4 7h16 M4 17h16 M9 4v6 M15 14v6',
    'url' => 'M10 13l4-4 M8 16l-2 2a3 3 0 0 1-4-4l4-4a3 3 0 0 1 4 0 M16 8l2-2a3 3 0 0 1 4 4l-4 4a3 3 0 0 1-4 0',
    'debug' => 'M8 8h8v8a4 4 0 0 1-8 0z M10 5 8 3 M14 5l2-2 M4 9l4 2 M20 9l-4 2 M4 14h4 M16 14h4 M4 20l4-2 M20 20l-4-2 M12 11v5',
    'profile' => 'M16 8a4 4 0 1 1-8 0 4 4 0 0 1 8 0z M4 21v-2a5 5 0 0 1 5-5h6a5 5 0 0 1 5 5v2',
    'external' => 'M13 4h7v7 M20 4l-9 9 M10 5H4v15h15v-6',
    'logout' => 'M10 20H4V4h6 M9 12h12 M17 8l4 4-4 4',
];

$icon = static fn(string $name): string => '<svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
    . ' stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
    . '<path d="' . $iconPaths[$name] . '"/></svg>';
$requestPath = rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$link = static function (string $path, string $label, string $glyph, string $class = '', bool $external = false) use ($icon, $requestPath): void {
    $href = url($path);
    $current = !$external && rtrim((string)parse_url($href, PHP_URL_PATH), '/') === $requestPath;
    ?>
    <a class="sidebar-nav-link<?= $class === '' ? '' : ' ' . e($class) ?>" href="<?= e($href) ?>"
        <?= $current ? 'aria-current="page"' : '' ?> <?= $external ? 'target="_blank" rel="noopener"' : '' ?>>
        <?= $icon($glyph) ?><span><?= e($label) ?></span>
    </a>
    <?php
};

$contentLinks = array_filter([
    ['posts.view', 'admin/posts', '投稿一覧', 'posts'],
    ['posts.create', 'admin/posts/create', '新規追加', 'add'],
    ['pages.manage', 'admin/pages', '固定ページ', 'pages'],
    ['categories.manage', 'admin/categories', 'カテゴリ', 'categories'],
    ['media.manage', 'admin/media', 'メディア', 'media'],
], static fn(array $item): bool => Permission::can($item[0]));

$canThemes = Permission::can('themes.manage');
$canSettings = Permission::can('settings.manage');
$canExtensions = Permission::can('extensions.manage');
$canUsers = Permission::can('users.manage');
$canUpdates = Permission::can('updates.manage');
?>
<aside class="sidebar">
    <h2><?= e(site_title()) ?></h2>
    <nav aria-label="管理メニュー">
        <?php if (Permission::can('dashboard.view')): ?>
            <div class="sidebar-section"><?php $link('admin', 'ダッシュボード', 'dashboard'); ?></div>
        <?php endif; ?>
        <?php if ($contentLinks): ?>
            <div class="sidebar-section">
                <?php foreach ($contentLinks as [, $path, $label, $glyph]) { $link($path, $label, $glyph); } ?>
            </div>
        <?php endif; ?>

        <?php if ($canThemes || $canSettings): ?>
            <div class="sidebar-section">
                <?php if ($canThemes) {
                    $link('admin/design', 'デザイン', 'design');
                } else {
                    $link('admin/navigation', 'メニュー管理', 'navigation');
                } ?>
            </div>
        <?php endif; ?>

        <?php if ($canExtensions || $canUsers || $canUpdates): ?>
            <div class="sidebar-section">
                <?php if ($canExtensions): ?>
                    <div class="sidebar-menu-item sidebar-menu-item-has-submenu">
                        <?php $link('admin/extensions', '拡張機能', 'extensions', 'sidebar-menu-link'); ?>
                        <div class="sidebar-submenu">
                            <a href="<?= e(url('admin/extensions')) ?>">拡張機能一覧</a>
                            <?php foreach (Extension::enabled() as $extension): ?>
                                <?php if ($extension['has_dashboard']): ?>
                                    <a href="<?= e(url('admin/extensions/' . rawurlencode($extension['key']) . '/dashboard')) ?>">
                                        <?= e($extension['admin_menu_label']) ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($canUsers) { $link('admin/users', 'ユーザー', 'users'); } ?>
                <?php if ($canUpdates) { $link('admin/updates', '更新', 'updates'); } ?>
            </div>
        <?php endif; ?>

        <?php if ($canSettings): ?>
            <div class="sidebar-section sidebar-group">
                <div class="sidebar-group-title">設定</div>
                <?php
                $link('admin/settings/general', '一般設定', 'settings');
                $link('admin/settings/url', 'URL設定', 'url');
                $link('admin/settings/debug', 'デバッグ設定', 'debug');
                ?>
            </div>
        <?php endif; ?>

        <div class="sidebar-section">
            <?php
            $link('admin/profile', '自分のプロフィール', 'profile');
            $link('', 'サイトを見る', 'external', '', true);
            if (function_exists('nx_logout_form')) { nx_logout_form($icon('logout')); }
            ?>
        </div>
    </nav>
</aside>
