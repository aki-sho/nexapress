<header class="site-header"<?= $design['headerWidth'] === 'theme' ? '' : ' data-design-width="' . e($design['headerWidth']) . '"' ?>>
    <?php if ($hasAbove): ?>
        <div class="site-header-inner nx-design-header-band" data-widget-area="header_above">
            <?php nx_design_widgets('header_above'); ?>
        </div>
    <?php endif; ?>
    <?php if ($rows !== null): ?>
        <?php require BASE_PATH . '/app/Views/design/header-rows.php'; ?>
    <?php elseif ($hasMainRow): ?>
        <div class="site-header-inner<?= $header['placement'] === 'stack' ? ' nx-design-header-stacked' : '' ?>">
            <?php foreach (['brand', 'menu', 'search'] as $part): ?>
                <?php require BASE_PATH . '/app/Views/design/header-content.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($hasBelow): ?>
        <div class="site-header-inner nx-design-header-band" data-widget-area="header_bottom1">
            <?php nx_design_widgets('header_bottom1'); ?>
        </div>
    <?php endif; ?>
</header>
