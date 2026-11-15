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
<link rel="stylesheet" href="<?= e(public_url('assets/css/public-features.css')) ?>">
<link rel="stylesheet" href="<?= e(public_url('assets/css/site-design.css')) ?>">
<?php if (\app\Core\Auth::check()): ?>
<link rel="stylesheet" href="<?= e(public_url('assets/css/admin-preview.css')) ?>">
<?php endif; ?>
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

<?php require BASE_PATH . '/app/Views/design/main.php'; ?>

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
