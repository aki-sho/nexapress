<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'NexaPress') ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('themes/' . \app\Core\Theme::active() . '/style.css') ?>">
</head>
<body>

<?php
$header = \app\Core\Theme::part('header');

if ($header) {
    require $header;
}
?>

<main class="site-main">
    <?= $content ?? '' ?>
</main>

<?php
$footer = \app\Core\Theme::part('footer');

if ($footer) {
    require $footer;
}
?>

</body>
</html>