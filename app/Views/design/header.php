<header class="site-header"<?= $design['headerWidth'] === 'theme' ? '' : ' data-design-width="' . e($design['headerWidth']) . '"' ?>>
    <?php if ($hasAbove): ?>
        <div class="site-header-inner nx-design-header-band" data-widget-area="header_above"<?= $design['headerAboveLayout'] ? \app\Core\LayoutPlacement::attributes($design['headerAboveLayout']) : '' ?>>
            <?php nx_design_widgets('header_above', $searchQuery, $includeHomeFallback); ?>
        </div>
    <?php endif; ?>
    <?php if ($rows !== null): ?>
        <?php require BASE_PATH . '/app/Views/design/header-rows.php'; ?>
    <?php elseif ($hasMainRow): ?>
        <?php
        $nativeHeader = true;
        $rows = \app\Core\Design::headerRowDefaults($header['placement'] === 'stack');
        $rows['split'] = false;
        require BASE_PATH . '/app/Views/design/header-rows.php';
        ?>
    <?php endif; ?>
    <?php if ($hasBelow): ?>
        <div class="site-header-inner nx-design-header-band" data-widget-area="header_bottom1">
            <?php nx_design_widgets('header_bottom1', $searchQuery, $includeHomeFallback); ?>
        </div>
    <?php endif; ?>
</header>
