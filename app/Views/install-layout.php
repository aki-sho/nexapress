<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'CMS インストール') ?></title>
    <link rel="stylesheet" href="<?= public_url('assets/css/style.css') ?>">
</head>
<body>

<main class="install-layout">
    <?= $content ?? '' ?>
</main>

</body>
</html>