<?php
$design = nx_design_settings();
$sidebar = $design['columns'] !== 'none' && ($design['widgets']['sidebar'] || \app\Core\Design::isDraftPreview());
?>
<?php if ($sidebar): ?>
    <div class="site-main nx-design-main nx-design-main--<?= e($design['columns']) ?>">
        <main><?= $content ?? '' ?></main>
        <aside class="nx-design-sidebar" aria-label="サイドバー"><?php nx_design_widgets('sidebar'); ?></aside>
    </div>
<?php else: ?>
    <main class="site-main"><?= $content ?? '' ?></main>
<?php endif; ?>
