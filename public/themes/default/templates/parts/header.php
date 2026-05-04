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