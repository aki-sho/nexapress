<header class="site-header">
    <div class="site-header-inner">
        <div class="site-brand">
            <?php if (site_icon() !== ''): ?>
                <a href="<?= url('') ?>" class="site-icon-link">
                    <img src="<?= e(site_icon()) ?>" alt="<?= e(site_title()) ?>" class="site-icon">
                </a>
            <?php endif; ?>

            <h1 class="site-title">
                <a href="<?= url('') ?>"><?= e(site_title()) ?></a>
            </h1>
        </div>

        <nav class="site-nav">
            <a href="<?= url('') ?>">トップ</a>
        </nav>
    </div>
</header>