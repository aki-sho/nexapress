<?php
$design = nx_design_settings();
$sidebar = $design['columns'] !== 'none' && ($design['widgets']['sidebar'] || \app\Core\Design::isDraftPreview());
$width = $pageDesign['width'] ?? 'inherit';
if ($width === 'inherit') {
    $width = $design['width'];
}
$useSections = !empty($pageDesign['enabled']) && !empty($pageDesign['sections']);
?>
<?php if ($useSections): ?>
    <main class="site-main nx-section-page"><?= $content ?></main>
<?php elseif ($sidebar): ?>
    <div class="site-main nx-design-main nx-design-main--<?= e($design['columns']) ?>" data-content-width="<?= e($width) ?>">
        <main><?= $content ?? '' ?></main>
        <aside class="nx-design-sidebar" aria-label="サイドバー"><?php nx_design_widgets('sidebar'); ?></aside>
    </div>
<?php else: ?>
    <main class="site-main" data-content-width="<?= e($width) ?>"><?= $content ?? '' ?></main>
<?php endif; ?>
