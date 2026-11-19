<?php if ($part === 'brand' && ($logo !== '' || $header['name'])): ?>
    <div class="site-brand">
        <?php if ($logo !== ''): ?>
            <a href="<?= e(url('')) ?>" class="site-icon-link"><img src="<?= e($logo) ?>" alt="<?= e(site_title()) ?>" class="site-icon"></a>
        <?php endif; ?>
        <?php if ($header['name']): ?>
            <h1 class="site-title"><a href="<?= e(url('')) ?>"><?= e(site_title()) ?></a></h1>
        <?php endif; ?>
    </div>
<?php elseif ($part === 'menu' && $links): ?>
    <nav class="site-nav" aria-label="メインメニュー">
        <?php foreach ($links as $link): ?>
            <a href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a>
        <?php endforeach; ?>
    </nav>
<?php elseif ($part === 'search' && $header['search']): ?>
    <form class="public-search" action="<?= e(url(nx_public_archive_path('search'))) ?>" method="get" role="search">
        <input type="search" name="q" aria-label="記事を検索" placeholder="記事を検索" maxlength="200" value="<?= e($searchQuery) ?>">
        <button type="submit">検索</button>
    </form>
<?php endif; ?>
