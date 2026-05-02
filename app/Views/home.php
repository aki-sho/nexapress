<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>NexaPress</title>
    <link rel="stylesheet" href="/NexaPress/public/themes/default/style.css">
</head>
<body>

<header class="site-header">
    <div class="site-header-inner">
        <h1 class="site-title">
            <a href="/NexaPress/public/">NexaPress</a>
        </h1>
        <p class="site-description">シンプルな自作CMSサイトです。</p>

        <nav class="site-nav">
            <a href="/NexaPress/public/">ホーム</a>
            <a href="/NexaPress/public/admin/login">管理画面</a>
        </nav>
    </div>
</header>

<main class="site-main">
    <h2 class="page-title">最新記事</h2>

    <?php if (empty($posts)): ?>
        <div class="empty-message">
            公開中の記事はありません。
        </div>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <h2>
                        <a href="/NexaPress/public/post/<?= htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </h2>

                    <p class="post-meta">
                        投稿日：
                        <?= htmlspecialchars($post['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <p class="post-excerpt">
                        <?php
                        $content = strip_tags($post['content'] ?? '');
                        echo htmlspecialchars(mb_substr($content, 0, 120), ENT_QUOTES, 'UTF-8');
                        ?>
                        <?php if (mb_strlen($content) > 120): ?>
                            ...
                        <?php endif; ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <div class="site-footer-inner">
        &copy; <?= date('Y') ?> NexaPress
    </div>
</footer>

</body>
</html>