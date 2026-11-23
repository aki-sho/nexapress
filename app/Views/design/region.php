<?php
$simple = array_keys($rows) === ['top'] && $layout['columns'] === 1;
$regionAttributes = static function (array $settings): string {
    $legacy = $settings['direction'] === 'row'
        ? ($settings['align'] === 'flex-start' ? 'auto' : $settings['align'])
        : ($settings['width'] === 'fit' ? $settings['cross'] : 'auto');
    return ' data-region-direction="' . e($settings['direction']) . '" data-region-align="' . e($legacy) .
        '" data-region-width="' . e($settings['width']) . '" data-region-cross="' . e($settings['cross']) . '"' .
        \app\Core\LayoutPlacement::attributes($settings);
};
?>
<section class="nx-design-region" data-design-area="<?= e($area) ?>" aria-label="<?= e($label) ?>"<?= $simple ? $regionAttributes($layout) : '' ?>>
    <?php if ($simple): ?>
        <?= $rows['top']['contents'][$area] ?>
    <?php else: ?>
        <?php foreach ($rows as $row => ['settings' => $settings, 'contents' => $contents]): ?>
            <div class="nx-design-region-row" data-region-row="<?= e($row) ?>" data-region-columns="<?= (int)$settings['columns'] ?>">
                <?php foreach ($contents as $slot => $content): ?>
                    <div class="nx-design-region-column" data-widget-area="<?= e($slot) ?>"<?= $regionAttributes($settings) ?>><?= $content ?></div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
