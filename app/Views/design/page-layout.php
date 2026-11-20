<?php

use app\Core\Design;
use app\Core\PageLayout;
use app\Models\Media;
use app\Models\Navigation;

$title = $layoutDocument['title'];
$body = null;
$images = [];
?>
<link rel="stylesheet" href="<?= e(PageLayout::stylesheetUrl($pageDesign, nx_design_settings()['width'])) ?>">
<?php if (!empty($isPreview)): ?><p class="preview-notice">未公開の内容を表示しています。</p><?php endif; ?>
<?php foreach ($pageDesign['sections'] as $index => $section): ?>
    <?php
    $groups = $section['columns'] === 2 ? $section['groups'] : [array_merge(...$section['groups'])];
    ?>
    <section class="nx-page-section" data-layout-section="<?= $index ?>" data-background="<?= e($section['background']) ?>"
        data-columns="<?= $section['columns'] ?>">
        <div class="nx-page-section-inner">
            <?php foreach ($groups as $elements): ?>
                <div class="nx-page-section-column">
                    <?php foreach ($elements as $element): ?>
                        <?php switch ($element['type']):
                            case 'page_title': ?>
                                <h1<?php \app\Core\EditorPreview::attribute('title'); ?>><?= e($title) ?></h1>
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
