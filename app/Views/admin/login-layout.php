<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'ログイン') ?></title>
    <link rel="stylesheet" href="<?= public_url('assets/css/style.css') ?>">
</head>
<body class="login-body">

<?= $content ?? '' ?>

</body>
</html>