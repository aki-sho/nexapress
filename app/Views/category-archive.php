<?php

use app\Core\EditorPreview;
use app\Core\Theme;

$title = $archiveTitle;
$showTitle = (bool)$categoryPage['show_title'];
$postMode = $categoryPage['posts_mode'];
$body = nx_post_content($categoryPage, false);
?>
<section class="post-archive nx-category-archive">
    <?php if ($showTitle || !empty($pagination['total_items'])): ?>
        <header class="post-archive-header">
            <?php if ($showTitle): ?>
                <h1 class="page-title"><?= e($archiveTitle) ?></h1>
            <?php endif; ?>
            <?php if (!empty($pagination['total_items'])): ?>
                <p class="post-archive-count">全 <?= (int)$pagination['total_items'] ?> 件の記事</p>
            <?php endif; ?>
        </header>
    <?php endif; ?>

    <?php if ($body !== '' || EditorPreview::active()): ?>
        <div class="post-content nx-category-body"<?php EditorPreview::attribute('body'); ?>><?= $body ?></div>
    <?php endif; ?>

    <?php if ($postMode !== 'hidden'): ?>
        <div class="nx-category-posts">
            <?php if (!$posts): ?>
                <div class="empty-message"><p>公開中の記事はありません。</p></div>
            <?php else: ?>
                <div class="post-list<?= $postMode === 'cards' ? ' nx-category-cards' : '' ?>">
                    <?php
                    $postCard = Theme::part('post-card') ?? BASE_PATH . '/public/themes/default/templates/parts/post-card.php';
                    foreach ($posts as $post) {
                        require $postCard;
                    }
                    ?>
                </div>
                <?php require Theme::part('pagination') ?? BASE_PATH . '/public/themes/default/templates/parts/pagination.php'; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
