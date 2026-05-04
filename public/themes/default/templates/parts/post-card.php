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