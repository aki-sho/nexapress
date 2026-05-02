<?php require BASE_PATH . '/app/Views/layout.php'; ?>

<div class="header">
    <h1>
        <a href="<?php echo BASE_URL; ?>/">NexaPress</a>
    </h1>
</div>

<div class="container">
    <div class="card">
        <h1><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h1>

        <p>
            公開日：
            <?php echo htmlspecialchars($post['published_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </p>

        <div>
            <?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?>
        </div>
    </div>

    <p>
        <a href="<?php echo BASE_URL; ?>/">記事一覧へ戻る</a>
    </p>
</div>

</body>
</html>