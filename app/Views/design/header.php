<?php $designHeader = nx_design_settings()['header']; ?>
<header class="site-header">
    <div class="site-header-inner<?= $designHeader['placement'] === 'stack' ? ' nx-design-header-stacked' : '' ?>">
        <?php if ($designHeader['logo'] || $designHeader['name']): ?>
            <div class="site-brand">
                <?php if ($designHeader['logo'] && site_icon() !== ''): ?>
                    <a href="<?= e(url('')) ?>" class="site-icon-link">
                        <img src="<?= e(site_icon()) ?>" alt="<?= e(site_title()) ?>" class="site-icon">
                    </a>
                <?php endif; ?>
                <?php if ($designHeader['name']): ?>
                    <h1 class="site-title"><a href="<?= e(url('')) ?>"><?= e(site_title()) ?></a></h1>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($designHeader['menu']): ?>
            <nav class="site-nav" aria-label="メインメニュー"><?php nx_navigation(); ?></nav>
        <?php endif; ?>
        <?php if ($designHeader['search']): ?>
            <form class="public-search" action="<?= e(url(nx_public_archive_path('search'))) ?>" method="get" role="search">
                <input type="search" name="q" aria-label="記事を検索" placeholder="記事を検索" maxlength="200" value="<?= e($searchQuery ?? '') ?>">
                <button type="submit">検索</button>
            </form>
        <?php endif; ?>
    </div>
</header>
