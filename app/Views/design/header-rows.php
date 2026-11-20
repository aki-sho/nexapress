    <?php foreach (['top', 'bottom'] as $row): ?>
        <?php if ($row === 'bottom' && !$rows['split']) { continue; } ?>
        <div class="site-header-inner nx-chrome-row" data-chrome="header" data-chrome-row="<?= e($row) ?>"
             data-columns="<?= (int)$rows[$row]['columns'] ?>" data-align="<?= e($rows[$row]['align']) ?>">
            <?php for ($column = 1; $column <= $rows[$row]['columns']; $column++): ?>
                <?php $area = ($row === 'top' ? 'header' : 'header_bottom') . $column; ?>
                <div class="nx-chrome-column" data-chrome-column="<?= $column ?>" data-widget-area="<?= e($area) ?>">
                    <?php $hasContent = false; ?>
                    <?php foreach ($rows['positions'] as $part => $position): ?>
                        <?php if ($position['row'] === $row && $position['column'] === $column): ?>
                            <?php
                            $hasContent = $hasContent || match ($part) {
                                'brand' => $logo !== '' || $header['name'],
                                'menu' => (bool)$links,
                                default => $header['search'],
                            };
                            require BASE_PATH . '/app/Views/design/header-content.php';
                            ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!$hasContent || $design['widgets'][$area]): nx_design_widgets($area); endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    <?php endforeach; ?>
