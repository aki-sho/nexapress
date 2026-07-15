<?php $title = $post['title'] ?? '記事詳細'; ?>

<article class="post-detail">
    <?php if (empty($post)): ?>
        <h1>記事が見つかりません</h1>
        <p>指定された記事は存在しないか、公開されていません。</p>
        <p><a href="<?= url('') ?>">トップへ戻る</a></p>
    <?php else: ?>
        <h1><?= e($post['title'] ?? '') ?></h1>

        <?php if (!empty($post['created_at'])): ?>
            <p class="post-date"><?= e($post['created_at']) ?></p>
        <?php endif; ?>

        <div class="post-content">
            <?= $post['content'] ?? '' ?>
        </div>

        <p>
            <a href="<?= url('') ?>">トップへ戻る</a>
        </p>
    <?php endif; ?>
</article>