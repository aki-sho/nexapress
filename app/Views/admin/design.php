<?php $title = 'デザイン'; ?>
<section class="nx-design-editor" id="nx-design-editor">
    <div class="nx-design-toolbar">
        <h1>デザイン</h1>
        <div class="nx-design-actions">
            <button type="button" class="button secondary small" data-action="reset" disabled>デザインを初期状態に戻す</button>
            <span id="nx-design-status" role="status" aria-live="polite">公開済み</span>
            <button type="button" class="button" data-action="publish" disabled>公開</button>
        </div>
    </div>
    <p id="nx-design-error" class="error-message" role="alert" hidden></p>
    <div class="nx-design-editor-grid">
        <div class="nx-design-controls">
            <div class="nx-design-nav">
                <label>編集中のテーマ
                    <select data-setting="theme">
                        <?php foreach ($designData['themes'] as $id => $theme): ?>
                            <option value="<?= e($id) ?>" <?= $id === $designData['active'] ? 'selected' : '' ?>>
                                <?= e($theme['name']) ?><?= $id === $designData['active'] ? '（使用中）' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="nx-design-theme-actions">
                    <a href="<?= e(url('admin/themes')) ?>">使用するテーマを変更</a>
                    <button type="button" class="nx-design-small" data-pane="general" aria-pressed="false">全体設定</button>
                </div>
                <div class="nx-design-tabs" role="group" aria-label="編集する項目">
                    <?php foreach ([
                        'header' => 'ヘッダー', 'columns' => '本文カラム', 'footer' => 'フッター',
                        'page_layout' => 'ページ毎のレイアウト',
                    ] as $key => $label): ?>
                        <button type="button" data-pane="<?= e($key) ?>"
                            aria-pressed="<?= $key === 'header' ? 'true' : 'false' ?>"><?= e($label) ?></button>
                    <?php endforeach; ?>
                </div>
                <p id="nx-design-theme-note" class="nx-design-note" hidden></p>
            </div>
            <fieldset id="nx-design-panel" class="nx-design-panel" aria-label="編集項目"></fieldset>
        </div>
        <div class="nx-design-preview">
            <div class="nx-design-preview-bar">
                <strong>サイトプレビュー</strong>
                <div class="nx-design-actions">
                    <button type="button" class="nx-design-small" data-action="toggle-widget-highlight" aria-pressed="true" hidden>配置範囲</button>
                    <div class="nx-design-switch" role="group" aria-label="表示するデザイン">
                        <button type="button" data-mode="draft" aria-pressed="true">編集中</button>
                        <button type="button" data-mode="published" aria-pressed="false">公開版</button>
                    </div>
                    <div class="nx-design-switch" role="group" aria-label="表示する端末">
                        <button type="button" data-device="desktop" aria-pressed="true">PC</button>
                        <button type="button" data-device="mobile" aria-pressed="false">スマホ</button>
                    </div>
                </div>
                <p id="nx-design-widget-note" class="nx-design-note nx-widget-preview-note" role="status" hidden></p>
            </div>
            <div id="nx-design-stage" class="nx-design-stage" role="region" aria-label="サイトプレビュー" aria-busy="true">
                <div id="nx-design-canvas">
                    <iframe id="nx-design-frame" title="公開サイトの見た目" sandbox="allow-same-origin"></iframe>
                    <div id="nx-design-preview-overlay" class="nx-preview-overlay" aria-hidden="true">
                        <div id="nx-design-spacing-overlay" class="nx-spacing-overlay" hidden></div>
                        <div id="nx-design-widget-highlight" class="nx-widget-highlight" hidden></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <noscript><p>デザイン編集にはJavaScriptを有効にしてください。</p></noscript>
</section>
<script type="application/json" id="nx-design-data"><?= json_encode($designData, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= e(nx_asset_url('assets/js/design-color.js')) ?>" defer></script>
<script src="<?= e(nx_asset_url('assets/js/design-page.js')) ?>" defer></script>
<script src="<?= e(nx_asset_url('assets/js/design.js')) ?>" defer></script>
