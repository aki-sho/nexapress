<?php $title = $post['title'] ?? '記事詳細'; ?>

<?php if (empty($post)): ?>
    <div class="empty-message">
        <h1>記事が見つかりません</h1>
        <p>指定された記事は存在しないか、公開されていません。</p>
        <p><a href="<?= url('') ?>">トップへ戻る</a></p>
    </div>
<?php else: ?>
    <article class="post-detail">
        <h1><?= e($post['title'] ?? '') ?></h1>

        <?php if (!empty($post['created_at'])): ?>
            <p class="post-date"><?= e($post['created_at']) ?></p>
        <?php endif; ?>

        <div class="post-content">
            <?= nl2br(e($post['content'] ?? '')) ?>
        </div>

        <p>
            <a href="<?= url('') ?>">トップへ戻る</a>
        </p>
    </article>
<?php endif; ?>