<footer class="site-footer">
    <?php foreach (['top', 'bottom'] as $row): ?>
        <?php if ($row === 'bottom' && !$rows['split']) { continue; } ?>
        <?php $count = $row === 'top' ? $columns : $rows['bottom']['columns']; ?>
        <div class="site-footer-inner nx-chrome-row" data-chrome="footer" data-chrome-row="<?= e($row) ?>"
             data-columns="<?= (int)$count ?>" data-align="<?= e($rows[$row]['align']) ?>">
            <?php for ($column = 1; $column <= $count; $column++): ?>
                <?php $area = ($row === 'top' ? 'footer' : 'footer_bottom') . $column; ?>
                <div class="nx-chrome-column" data-chrome-column="<?= $column ?>" data-widget-area="<?= e($area) ?>"><?php nx_design_widgets($area); ?></div>
            <?php endfor; ?>
        </div>
    <?php endforeach; ?>
</footer>
