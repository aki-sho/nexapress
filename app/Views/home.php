<?php require BASE_PATH . '/app/Views/layout.php'; ?>

<div class="header">
    <h1>
        <a href="<?php echo BASE_URL; ?>/">NexaPress</a>
    </h1>
</div>

<div class="container">
    <h2>記事一覧</h2>

    <?php if (empty($posts)): ?>
        <div class="card">
            <p>公開中の記事はありません。</p>
        </div>
    <?php endif; ?>

    <?php foreach ($posts as $post): ?>
        <div class="card">
            <h2>
                <a href="<?php echo BASE_URL; ?>/post/<?php echo htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </h2>

            <p>
                <?php echo nl2br(htmlspecialchars(mb_substr($post['content'], 0, 120), ENT_QUOTES, 'UTF-8')); ?>
            </p>

            <p>
                <a href="<?php echo BASE_URL; ?>/post/<?php echo htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                    続きを読む
                </a>
            </p>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>