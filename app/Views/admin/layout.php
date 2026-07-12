<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= e(($title ?? '管理画面') . ' - ' . site_title()) ?></title>
    <link rel="stylesheet" href="<?= public_url('assets/css/style.css') ?>">
    <?php if (function_exists('do_action')): ?>
        <?php do_action('admin_head'); ?>
    <?php endif; ?>
</head>
<body>

<div class="admin-layout">
    <aside class="sidebar">
        <h2><?= e(site_title()) ?></h2>

        <nav>
            <a href="<?= url('admin') ?>">ダッシュボード</a>
            <a href="<?= url('admin/updates') ?>">更新</a>
            <a href="<?= url('admin/posts') ?>">投稿一覧</a>
            <a href="<?= url('admin/posts/create') ?>">新規追加</a>
            <a href="<?= url('admin/categories') ?>">カテゴリ</a>
            <a href="<?= url('admin/media') ?>">メディア</a>

            <?php $enabledExtensions = \app\Core\Extension::enabled(); ?>

            <div class="sidebar-menu-item sidebar-menu-item-has-submenu">
                <a href="<?= url('admin/extensions') ?>" class="sidebar-menu-link">
                    拡張機能
                </a>

                <div class="sidebar-submenu">
                    <a href="<?= url('admin/extensions') ?>">
                        拡張機能一覧
                    </a>

                    <?php foreach ($enabledExtensions as $extension): ?>
                        <?php if ($extension['has_dashboard']): ?>
                            <a href="<?= url(
                                'admin/extensions/' .
                                rawurlencode($extension['key']) .
                                '/dashboard'
                            ) ?>">
                                <?= e($extension['admin_menu_label']) ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <a href="<?= url('admin/themes') ?>">テーマ設定</a>
            <a href="<?= url('admin/pages') ?>">固定ページ</a>
            <div class="sidebar-group">
                <div class="sidebar-group-title">設定</div>
                <a href="<?= url('admin/settings/general') ?>">一般設定</a>
                <a href="<?= url('admin/settings/url') ?>">URL設定</a>
                <a href="<?= url('admin/settings/debug') ?>">デバッグ設定</a>
            </div>
            <a href="<?= url('') ?>" target="_blank">サイトを見る</a>
            <a href="<?= url('admin/logout') ?>">ログアウト</a>
        </nav>
    </aside>

    <main class="main">
        <?= $content ?? '' ?>
    </main>
</div>
<?php if (function_exists('do_action')): ?>
    <?php do_action('admin_footer'); ?>
<?php endif; ?>
</body>
</html>