<?php $title = site_title(); ?>

<h1 class="page-title">記事一覧</h1>

<?php if (empty($posts)): ?>
    <div class="empty-message">
        <p>公開中の記事はありません。</p>
    </div>
<?php else: ?>
    <div class="post-list">
        <?php foreach ($posts as $post): ?>
            <?php
            $postCard = \app\Core\Theme::part('post-card');

            if ($postCard) {
                require $postCard;
            }
            ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>