<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title'] ?? '記事詳細', ENT_QUOTES, 'UTF-8') ?> | My CMS</title>
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
    <?php if (empty($post)): ?>
        <div class="empty-message">
            記事が見つかりませんでした。
        </div>
    <?php else: ?>
        <article class="post-detail">
            <h1><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h1>

            <p class="post-meta">
                投稿日：
                <?= htmlspecialchars($post['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div class="post-content">
                <?= nl2br(htmlspecialchars($post['content'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
            </div>
        </article>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <div class="site-footer-inner">
        &copy; <?= date('Y') ?> NexaPress
    </div>
</footer>

</body>
</html>