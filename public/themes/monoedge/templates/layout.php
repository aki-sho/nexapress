<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? site_title()) ?></title>

    <?php if (site_icon() !== ''): ?>
        <link rel="icon" href="<?= e(site_icon()) ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= public_url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= public_url('themes/' . \app\Core\Theme::active() . '/style.css') ?>">
</head>
<body>

<?php nexapress_admin_header(); ?>

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