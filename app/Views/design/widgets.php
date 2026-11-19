<?php if (!$widgets && \app\Core\Design::isDraftPreview()): ?>
    <p class="nx-design-empty">ここにウィジェットを配置できます。</p>
<?php endif; ?>
<?php foreach ($widgets as $widget): ?>
    <?php if ($widget['type'] === 'category' && count($widget['reference_ids'] ?? []) > 1): ?>
        <?php
        $selectedCategories ??= array_column(\app\Models\Category::all(), null, 'id');
        $categoryLinks = [];
        foreach ($widget['reference_ids'] as $id) {
            if (isset($selectedCategories[$id])) {
                $categoryLinks[] = $selectedCategories[$id];
            }
        }
        ?>
        <?php if ($categoryLinks): ?>
            <section class="nx-design-widget nx-design-widget-categories">
                <?php if ($widget['title'] !== ''): ?><h2><?= e($widget['title']) ?></h2><?php endif; ?>
                <ul><?php foreach ($categoryLinks as $category): ?>
                    <li><a href="<?= e(category_url($category)) ?>"><?= e($category['name']) ?></a></li>
                <?php endforeach; ?></ul>
            </section>
        <?php endif; ?>
        <?php continue; ?>
    <?php endif; ?>
    <?php if (isset(\app\Models\Navigation::TYPES[$widget['type']])): ?>
        <?php $link = \app\Models\Navigation::resolveLink(['label' => $widget['title']] + $widget); ?>
        <?php if ($link !== null): ?>
            <div class="nx-design-widget"><a href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a></div>
        <?php endif; ?>
        <?php continue; ?>
    <?php endif; ?>
    <section class="nx-design-widget">
        <?php if ($widget['title'] !== ''): ?>
            <h2><?= e($widget['title']) ?></h2>
        <?php endif; ?>
        <?php switch ($widget['type']):
            case 'menu': ?>
                <nav class="nx-design-widget-menu" aria-label="<?= e($widget['title'] ?: 'メニュー') ?>">
                    <?php nx_navigation($widget['menu']); ?>
                </nav>
                <?php break;
            case 'recent': ?>
                <ul><?php foreach (\app\Models\Post::publishedPage(1, 5) as $post): ?>
                    <li><a href="<?= e(post_url($post)) ?>"><?= e($post['title']) ?></a></li>
                <?php endforeach; ?></ul>
                <?php break;
            case 'categories': ?>
                <ul><?php foreach (\app\Models\Category::all() as $category): ?>
                    <li><a href="<?= e(category_url($category)) ?>"><?= e($category['name']) ?></a></li>
                <?php endforeach; ?></ul>
                <?php break;
            case 'search': ?>
                <form class="public-search" action="<?= e(url(nx_public_archive_path('search'))) ?>" method="get" role="search">
                    <input type="search" name="q" aria-label="記事を検索" placeholder="記事を検索" maxlength="200">
                    <button type="submit">検索</button>
                </form>
                <?php break;
            case 'copyright': ?>
                <p>&copy; <?= date('Y') ?> <?= e($widget['text']) ?></p>
                <?php break;
            case 'text': ?>
                <p class="nx-design-widget-text"><?= e($widget['text']) ?></p>
                <?php break;
        endswitch; ?>
    </section>
<?php endforeach; ?>
