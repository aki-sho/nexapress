<?php
$design = nx_design_settings();
$sidebar = $design['columns'] !== 'none' && ($design['widgets']['sidebar'] || \app\Core\Design::isDraftPreview());
$width = $pageDesign['width'] ?? 'inherit';
if ($width === 'inherit') {
    $width = $design['width'];
}
$useSections = !empty($pageDesign['enabled']) && !empty($pageDesign['sections']);
$placement = $design['bodyColumnsLayout'];
$placementAttributes = $placement ? \app\Core\LayoutPlacement::attributes($placement) : '';
?>
<?php if ($sidebar): ?>
    <div class="site-main nx-design-main nx-design-main--<?= e($design['columns']) ?>" data-content-width="<?= e($width) ?>"<?= $placementAttributes ?>>
        <main<?= $useSections ? ' class="nx-section-page"' : '' ?>><?= $content ?? '' ?></main>
        <aside class="nx-design-sidebar" data-widget-area="sidebar" aria-label="サイドバー"><?php nx_design_widgets('sidebar'); ?></aside>
    </div>
<?php elseif ($useSections): ?>
    <main class="site-main nx-section-page"<?= $placementAttributes ?>><?= $content ?></main>
<?php else: ?>
    <main class="site-main" data-content-width="<?= e($width) ?>"<?= $placementAttributes ?>><?= $content ?? '' ?></main>
<?php endif; ?>
