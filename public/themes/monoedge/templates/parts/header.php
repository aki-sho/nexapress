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
            <?php nx_navigation(); ?>
        </nav>
        <form class="public-search" action="<?= e(url(nx_public_archive_path('search'))) ?>" method="get" role="search">
            <input type="search" name="q" aria-label="記事を検索" placeholder="記事を検索" maxlength="200" value="<?= e($searchQuery ?? '') ?>">
            <button type="submit">検索</button>
        </form>
    </div>
</header>