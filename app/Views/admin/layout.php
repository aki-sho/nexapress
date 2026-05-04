<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? '管理画面') ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    
</head>
<body>

<div class="admin-layout">
    <aside class="sidebar">
        <h2>My CMS</h2>

        <nav>
            <a href="<?= url('admin') ?>">ダッシュボード</a>
            <a href="<?= url('admin/posts') ?>">投稿一覧</a>
            <a href="<?= url('admin/posts/create') ?>">新規追加</a>
            <a href="<?= url('admin/themes') ?>">テーマ設定</a>
            <a href="<?= url('') ?>" target="_blank">サイトを見る</a>
            <a href="<?= url('admin/logout') ?>">ログアウト</a>
        </nav>
    </aside>

    <main class="main">
        <?= $content ?? '' ?>
    </main>
</div>

</body>
</html>