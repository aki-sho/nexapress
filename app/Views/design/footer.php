<footer class="site-footer">
    <div class="site-footer-inner nx-design-footer nx-design-footer--<?= (int)$columns ?>">
        <?php for ($column = 1; $column <= $columns; $column++): ?>
            <div class="nx-design-footer-column"><?php nx_design_widgets('footer' . $column); ?></div>
        <?php endfor; ?>
    </div>
</footer>
