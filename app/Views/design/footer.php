<footer class="site-footer"<?= $width === 'theme' ? '' : ' data-design-width="' . e($width) . '"' ?>>
    <?php foreach ($sections as $row => $section): ?>
        <?php if ($section['align'] !== null): ?>
            <?php require BASE_PATH . '/app/Views/design/footer-rows.php'; ?>
        <?php else: ?>
            <div class="site-footer-inner nx-design-footer nx-design-footer--<?= (int)$section['columns'] ?>"
                 data-chrome="footer" data-chrome-row="<?= e($row) ?>" data-columns="<?= (int)$section['columns'] ?>">
                <?php for ($column = 1; $column <= $section['columns']; $column++): ?>
                    <?php $area = $section['prefix'] . $column; ?>
                    <div class="nx-design-footer-column" data-widget-area="<?= e($area) ?>"><?php nx_design_widgets($area); ?></div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</footer>
