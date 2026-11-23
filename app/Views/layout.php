<!DOCTYPE html>
<html lang="ja">
<head>
    <?php nx_head($title ?? 'NexaPress'); ?>

    <link rel="stylesheet" href="<?= e(nx_asset_url('assets/css/base.css')) ?>">
    <?php nx_theme_styles(); ?>
<?php if (\app\Core\Auth::check()): ?>
<link rel="stylesheet" href="<?= e(nx_asset_url('assets/css/admin-preview.css')) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(nx_asset_url('assets/css/public-features.css')) ?>">
<link rel="stylesheet" href="<?= e(nx_asset_url('assets/css/layout-placement.css')) ?>">
    <script src="<?= e(nx_asset_url('assets/js/layout-placement.js')) ?>" data-nx-layout-render defer></script>
</head>
<body>

<?php if (empty($hideHeader)): ?>
<header class="admin-header">
    <div class="admin-header-inner">
        <div class="site-brand">
            <h1 class="site-title">
                <a href="<?= url('') ?>">NexaPress</a>
            </h1>
            <p class="site-description">シンプルな自作CMSサイト</p>
        </div>

        <?php if (\app\Core\Auth::check()): ?>
            <nav class="site-nav">
                <a href="<?= url('') ?>">トップ</a>
                <a href="<?= url('admin') ?>">管理画面</a>
                <?php if (function_exists('nx_logout_form')) { nx_logout_form(); } ?>
            </nav>
        <?php endif; ?>
    </div>
</header>
<?php endif; ?>

<main class="install-layout">
    <div class="install-container">
        <?= $content ?? '' ?>
    </div>
</main>

<?php if (empty($hideFooter)): ?>
<footer class="site-footer">
    <div class="site-footer-inner">
        <p>&copy; <?= date('Y') ?> NexaPress. All rights reserved.</p>
    </div>
</footer>
<?php endif; ?>

</body>
</html>
