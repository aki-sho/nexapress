<?php

use app\Core\Design;
use app\Models\Media;
use app\Models\Navigation;

$title = $layoutDocument['title'];
$body = null;
$images = [];
$addTitle = ($pageDesign['showTitle'] ?? null) === true;
foreach ($pageDesign['sections'] as $section) {
    if (in_array('page_title', array_column(array_merge(...$section['groups']), 'type'), true)) {
        $addTitle = false;
        break;
    }
}
?>
<article class="nx-page-article">
<?php if (!empty($isPreview)): ?><p class="preview-notice">未公開の内容を表示しています。</p><?php endif; ?>
<?php foreach ($pageDesign['sections'] as $index => $section): ?>
    <?php
    $groups = $section['columns'] === 2 ? $section['groups'] : [array_merge(...$section['groups'])];
    if ($index === 0 && $addTitle) {
        array_unshift($groups[0], ['type' => 'page_title']);
    }
    $placement = ['align' => $section['align']];
    if ($section['columnsWidth'] !== 100) {
        $placement['width'] = $section['columnsWidth'];
    }
    ?>
    <?php $fullSection = $section['columns'] === 1 && $section['columnsWidth'] === 100 &&
        ($section['widthMode'] === 'theme' || $section['percent'] === 100) && $section['background'] !== 'content'; ?>
    <section class="nx-page-section" data-layout-section="<?= $index ?>" data-background="<?= e($section['background']) ?>"<?= $fullSection ? ' data-full-width-section' : '' ?>
        data-columns="<?= $section['columns'] ?>"<?= \app\Core\LayoutPlacement::attributes($placement) ?>>
        <div class="nx-page-section-inner">
            <?php foreach ($groups as $elements): ?>
                <div class="nx-page-section-column">
                    <?php foreach ($elements as $element): ?>
                        <?php switch ($element['type']):
                            case 'page_title': ?>
                                <?php if ($pageDesign['showTitle'] ?? true): ?>
                                    <h1<?php \app\Core\EditorPreview::attribute('title'); ?>><?= e($title) ?></h1>
                                <?php endif; ?>
                                <?php break;
                            case 'page_content': ?>
                                <div class="post-content"<?php \app\Core\EditorPreview::attribute('body'); ?>><?= $body ??= nx_post_content($layoutDocument) ?></div>
                                <?php break;
                            case 'heading': ?>
                                <?php if ($element['text'] !== ''): ?><h2><?= e($element['text']) ?></h2><?php endif; ?>
                                <?php break;
                            case 'text': ?>
                                <p class="nx-page-text"><?= e($element['text']) ?></p>
                                <?php break;
                            case 'image':
                                $id = $element['media_id'];
                                $image = $id === null ? [] : ($images[$id] ??= Media::find($id) ?? []);
                                ?>
                                <?php if (($image['file_type'] ?? '') === 'image'): ?>
                                    <img class="nx-page-image" src="<?= e(public_url($image['file_path'])) ?>"
                                        alt="<?= e($element['alt']) ?>" decoding="async">
                                <?php elseif (Design::isDraftPreview()): ?>
                                    <p class="nx-design-empty">使用する画像を選択できます。</p>
                                <?php endif; ?>
                                <?php break;
                            case 'button':
                                $link = Navigation::resolveLink(['type' => 'custom', 'label' => $element['text'], 'url' => $element['url']]);
                                ?>
                                <?php if ($link !== null): ?>
                                    <a class="nx-page-button" href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a>
                                <?php elseif ($element['text'] !== '' && Design::isDraftPreview()): ?>
                                    <span class="nx-page-button"><?= e($element['text']) ?></span>
                                <?php endif; ?>
                                <?php break;
                        endswitch; ?>
                    <?php endforeach; ?>
                    <?php if (!$elements && Design::isDraftPreview()): ?>
                        <p class="nx-design-empty">ここに要素を追加できます。</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
</article>
