<?php

use app\Core\Design;
use app\Core\PageLayout;
use app\Core\Theme;

if (!in_array('page_layout', Theme::designAreas(Theme::active()), true)) {
    return;
}

$articleId = (int)($displayDocument['id'] ?? 0);
$displayFields = $articleId > 0 ? (PageLayout::snapshot($articleId, false, $displayKind)['overrides'] ?? []) : [];
$displayLayout = Design::pageLayout($articleId, $displayKind);
$displayInput = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && is_array($_POST['page_display'] ?? null)
    ? $_POST['page_display'] : [];
$legacySections = (bool)$displayLayout['sections'];
$titleSetting = isset($displayFields['showTitle']) ? ($displayFields['showTitle'] ? 'show' : 'hide') : 'inherit';
$sectionSetting = isset($displayFields['enabled']) ? ($displayFields['enabled'] ? 'sections' : 'blocks') : 'inherit';
$settings = [
    'title' => ['自動タイトル', $titleSetting, ['inherit' => '引き継ぐ', 'show' => '表示', 'hide' => '非表示']],
];
if ($displayKind === 'page') {
    $dateSetting = ($displayLayout['showCreatedAt'] ?? true) ? 'show' : 'hide';
    $settings['created_at'] = ['作成日時', $dateSetting, ['show' => '表示', 'hide' => '非表示']];
    $homeLink = ($displayLayout['showHomeLink'] ?? true) ? 'show' : 'hide';
    $settings['home_link'] = ['トップへ戻る', $homeLink, ['show' => '表示', 'hide' => '非表示']];
}
$settings['width'] = ['本文幅', $displayFields['width'] ?? 'inherit', ['inherit' => '引き継ぐ'] + PageLayout::WIDTHS];
if ($legacySections) {
    $settings['sections'] = ['本文の構成', $sectionSetting,
        ['inherit' => '引き継ぐ', 'blocks' => '本文ブロック', 'sections' => '既存のセクション']];
}
?>
<details class="article-display">
    <summary>ページの表示</summary>
    <div class="article-display-fields">
        <?php foreach ($settings as $field => [$label, $selected, $choices]): ?>
            <?php if (is_string($displayInput[$field] ?? null) && isset($choices[$displayInput[$field]])): ?>
                <?php $selected = $displayInput[$field]; ?>
            <?php endif; ?>
            <label for="article-display-<?= e($field) ?>"><?= e($label) ?>
                <select id="article-display-<?= e($field) ?>" name="page_display[<?= e($field) ?>]">
                    <?php foreach ($choices as $value => $option): ?>
                        <option value="<?= e($value) ?>" <?= $selected === $value ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endforeach; ?>
    </div>
</details>
