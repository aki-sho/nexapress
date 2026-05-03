<?php $title = 'トップページ'; ?>

<h1 class="page-title">記事一覧</h1>

<?php if (empty($posts)): ?>
    <div class="empty-message">
        <p>公開中の記事はありません。</p>
    </div>
<?php else: ?>
    <div class="post-list">
        <?php foreach ($posts as $post): ?>
            <article class="post-card">
                <h2>
                    <a href="<?= url('post/' . ($post['slug'] ?? '')) ?>">
                        <?= e($post['title'] ?? '') ?>
                    </a>
                </h2>

                <?php if (!empty($post['created_at'])): ?>
                    <p class="post-date"><?= e($post['created_at']) ?></p>
                <?php endif; ?>

                <?php if (!empty($post['content'])): ?>
                    <p class="post-excerpt">
                        <?= e(mb_substr(strip_tags($post['content']), 0, 120)) ?>...
                    </p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>