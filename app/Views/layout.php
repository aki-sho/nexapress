<!DOCTYPE html>
<html lang="ja">
<head>
    <?php nx_head($title ?? 'NexaPress'); ?>

    <link rel="stylesheet" href="<?= public_url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= public_url('themes/' . \app\Core\Theme::active() . '/style.css') ?>">
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
                <a href="<?= url('admin/logout') ?>">ログアウト</a>
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