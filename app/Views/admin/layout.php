<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= e(($title ?? '管理画面') . ' - ' . site_title()) ?></title>
    <?php
    // The update result can render this layout before the new helpers are loaded.
    $adminAssetUrl = function_exists('nx_asset_url') ? 'nx_asset_url' : 'public_url';
    ?>
    <link rel="stylesheet" href="<?= e($adminAssetUrl('assets/css/core-features.css')) ?>">
    <link rel="stylesheet" href="<?= e($adminAssetUrl('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e($adminAssetUrl('assets/css/admin.css')) ?>">
    <?php if (($title ?? '') === 'デザイン'): ?>
    <link rel="stylesheet" href="<?= e($adminAssetUrl('assets/css/design-admin.css')) ?>">
    <?php endif; ?>
    <?php $adminStylePath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH); ?>
    <?php if (preg_match('#/admin/themes/editor(?:/|$)#', $adminStylePath)): ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= e($adminAssetUrl('assets/css/theme-editor.css')) ?>">
    <?php endif; ?>
    <?php if (preg_match('#/admin/media(?:/|$)#', $adminStylePath)): ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= e($adminAssetUrl('assets/css/admin-media.css')) ?>">
    <?php endif; ?>
    <!-- 投稿管理画面専用CSS -->
    <?php if (preg_match('#/admin/posts(?:/|$)#', $adminStylePath)): ?>
    <link rel="stylesheet" href="<?= e($adminAssetUrl('assets/css/admin-posts.css')) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e($adminAssetUrl('assets/css/admin-compact.css')) ?>">
    <?php if (function_exists('do_action')): ?>
        <?php do_action('admin_head'); ?>
    <?php endif; ?>
</head>
<body>

<div class="admin-layout<?= !empty($embedded) ? ' is-embedded' : '' ?>">
    <?php if (empty($embedded)): ?>
    <?php require BASE_PATH . '/app/Views/admin/sidebar.php'; ?>
    <?php endif; ?>

    <main class="main">
        <?= $content ?? '' ?>
    </main>
</div>
<?php if (function_exists('do_action')): ?>
    <?php do_action('admin_footer'); ?>
<?php endif; ?>
<?php
$savedEditorKeys = [];
if (empty($embedded)) {
    $savedEditorKeys = $_SESSION['editor_saved_keys'] ?? [];
    unset($_SESSION['editor_saved_keys']);
}
?>
<script type="application/json" id="editor-drafts-config"><?= json_encode([
    'userId' => (int)(\app\Core\Auth::user()['id'] ?? 0), 'base' => base_url(), 'savedKeys' => $savedEditorKeys,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(public_url('assets/js/editor-drafts.js')) ?>" defer></script>
</body>
</html>
