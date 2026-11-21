<?php
$simple = array_keys($rows) === ['top'] && $layout['columns'] === 1;
?>
<section class="nx-design-region" data-design-area="<?= e($area) ?>" aria-label="<?= e($label) ?>"<?php if ($simple): ?> data-region-direction="<?= e($layout['direction']) ?>" data-region-align="<?= e($layout['align']) ?>"<?php endif; ?>>
    <?php if ($simple): ?>
        <?= $rows['top']['contents'][$area] ?>
    <?php else: ?>
        <?php foreach ($rows as $row => ['settings' => $settings, 'contents' => $contents]): ?>
            <div class="nx-design-region-row" data-region-row="<?= e($row) ?>" data-region-columns="<?= (int)$settings['columns'] ?>">
                <?php foreach ($contents as $slot => $content): ?>
                    <div class="nx-design-region-column" data-widget-area="<?= e($slot) ?>"
                         data-region-direction="<?= e($settings['direction']) ?>" data-region-align="<?= e($settings['align']) ?>"><?= $content ?></div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
