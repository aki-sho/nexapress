<?php $title = $page['title'] ?? '固定ページ'; ?>

<?php if (empty($page)): ?>
    <div class="empty-message">
        <h1>固定ページが見つかりません</h1>
        <p>指定されたページは存在しないか、公開されていません。</p>
        <p><a href="<?= url('') ?>">トップへ戻る</a></p>
    </div>
<?php else: ?>
    <article class="post-detail">
        <h1><?= e($page['title'] ?? '') ?></h1>

        <?php if (!empty($page['created_at'])): ?>
            <p class="post-date"><?= e($page['created_at']) ?></p>
        <?php endif; ?>

        <div class="post-content">
            <?= nl2br(e($page['content'] ?? '')) ?>
        </div>

        <p>
            <a href="<?= url('') ?>">トップへ戻る</a>
        </p>
    </article>
<?php endif; ?>