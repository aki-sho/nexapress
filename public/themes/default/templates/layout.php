<?php

use app\Core\Auth;
use app\Core\Theme;

$headerPart = Theme::part('header');
$footerPart = Theme::part('footer');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <?php nx_head($title ?? site_title()); ?>
    <?php nx_search_engine_meta(); ?>
    <link rel="stylesheet" href="<?= e(nx_asset_url('assets/css/base.css')) ?>">
    <?php nx_theme_styles(); ?>
    <link rel="stylesheet" href="<?= e(nx_asset_url('assets/css/public-features.css')) ?>">
    <link rel="stylesheet" href="<?= e(nx_asset_url('assets/css/site-design.css')) ?>">
    <?php if (Auth::check()): ?>
        <link rel="stylesheet" href="<?= e(nx_asset_url('assets/css/admin-preview.css')) ?>">
    <?php endif; ?>
</head>
<body>
    <?php nexapress_admin_header(); ?>
    <?php if ($headerPart !== null) { require $headerPart; } ?>
    <?php nx_design_region('header_after'); ?>
    <?php nx_design_main($content ?? '', $pageDesign ?? null); ?>
    <?php nx_design_region('footer_before'); ?>
    <?php if ($footerPart !== null) { require $footerPart; } ?>
</body>
</html>
