<?php

use app\Core\Auth;
use app\Core\Theme;

$theme = Theme::active();
$headerPart = Theme::part('header');
$footerPart = Theme::part('footer');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <?php nx_head($title ?? site_title()); ?>
    <?php nx_search_engine_meta(); ?>
    <link rel="stylesheet" href="<?= e(public_url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(public_url('themes/' . $theme . '/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(public_url('assets/css/public-features.css')) ?>">
    <link rel="stylesheet" href="<?= e(public_url('assets/css/site-design.css')) ?>">
    <?php if (Auth::check()): ?>
        <link rel="stylesheet" href="<?= e(public_url('assets/css/admin-preview.css')) ?>">
    <?php endif; ?>
</head>
<body>
    <?php nexapress_admin_header(); ?>
    <?php if ($headerPart !== null) { require $headerPart; } ?>
    <?php nx_design_main($content ?? ''); ?>
    <?php if ($footerPart !== null) { require $footerPart; } ?>
</body>
</html>
