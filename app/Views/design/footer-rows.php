            <div class="site-footer-inner nx-chrome-row" data-chrome="footer" data-chrome-row="<?= e($row) ?>"
                 data-columns="<?= (int)$section['columns'] ?>" data-align="<?= e($section['align']) ?>">
                <?php for ($column = 1; $column <= $section['columns']; $column++): ?>
                    <?php $area = $section['prefix'] . $column; ?>
                    <div class="nx-chrome-column" data-chrome-column="<?= $column ?>" data-widget-area="<?= e($area) ?>"><?php nx_design_widgets($area); ?></div>
                <?php endfor; ?>
            </div>
