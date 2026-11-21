    <?php foreach (['top', 'bottom'] as $row): ?>
        <?php if ($row === 'bottom' && !$rows['split']) { continue; } ?>
        <div class="site-header-inner nx-chrome-row<?= !empty($nativeHeader) ? ' nx-design-header-native' . ($header['placement'] === 'stack' ? ' nx-design-header-stacked' : '') : '' ?>" data-chrome="header" data-chrome-row="<?= e($row) ?>"
             data-columns="<?= (int)$rows[$row]['columns'] ?>" data-align="<?= e($rows[$row]['align']) ?>">
            <?php for ($column = 1; $column <= $rows[$row]['columns']; $column++): ?>
                <?php $area = ($row === 'top' ? 'header' : 'header_bottom') . $column; ?>
                <div class="nx-chrome-column" data-chrome-column="<?= $column ?>" data-widget-area="<?= e($area) ?>">
                    <?php nx_design_widgets($area, $searchQuery, $includeHomeFallback); ?>
                </div>
            <?php endfor; ?>
        </div>
    <?php endforeach; ?>
