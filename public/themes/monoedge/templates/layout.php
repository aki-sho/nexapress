<!DOCTYPE html>
<html lang="ja">
<head>
    <?php nx_head(
        $title ?? site_title()
    ); ?>

    <?php nx_search_engine_meta(); ?>

    <link
        rel="stylesheet"
        href="<?= public_url(
            'assets/css/style.css'
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= public_url(
            'themes/'
            . \app\Core\Theme::active()
            . '/style.css'
        ) ?>"
    >
</head>
<body>

<?php nexapress_admin_header(); ?>

<?php

$header = \app\Core\Theme::part(
    'header'
);

if ($header) {
    require $header;
}

?>

<main class="site-main">
    <?= $content ?? '' ?>
</main>

<?php

$footer = \app\Core\Theme::part(
    'footer'
);

if ($footer) {
    require $footer;
}

?>

</body>
</html>