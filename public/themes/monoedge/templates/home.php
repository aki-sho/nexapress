<?php

$title = site_title();

$pagination = is_array(
    $pagination ?? null
)
    ? $pagination
    : [];
?>

<section class="post-archive">
    <header class="post-archive-header">
        <h1 class="page-title">
            記事一覧
        </h1>

        <?php if (
            !empty(
                $pagination['total_items']
            )
        ): ?>
            <p class="post-archive-count">
                全
                <?= (int)$pagination[
                    'total_items'
                ] ?>
                件の記事
            </p>
        <?php endif; ?>
    </header>

    <?php if (empty($posts)): ?>
        <div class="empty-message">
            <p>
                公開中の記事はありません。
            </p>
        </div>
    <?php else: ?>
        <div class="post-list">
            <?php foreach (
                $posts
                as $post
            ): ?>
                <?php
                $postCard =
                    \app\Core\Theme::part(
                        'post-card'
                    );

                if ($postCard) {
                    require $postCard;
                }
                ?>
            <?php endforeach; ?>
        </div>

        <?php
        $paginationPart =
            \app\Core\Theme::part(
                'pagination'
            );

        if ($paginationPart) {
            require $paginationPart;
        }
        ?>
    <?php endif; ?>
</section>