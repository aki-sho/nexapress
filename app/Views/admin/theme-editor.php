<?php $editorUrl = url('admin/themes/editor'); ?>
<section class="nx-theme-editor">
    <div class="admin-page-header">
        <div>
            <h1>テーマエディタ</h1>
            <p>使用中のテーマ <strong><?= e($themeName) ?></strong></p>
        </div>
        <a class="button secondary" href="<?= e(url('admin/design')) ?>">デザインに戻る</a>
    </div>

    <?php if ($notice !== ''): ?><p class="core-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="error-message" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="nx-theme-editor-layout">
        <nav class="nx-theme-editor-files" aria-label="テーマファイル">
            <?php foreach ($files as $group => $names): ?>
                <?php if (!$names) { continue; } ?>
                <h2><?= e($group) ?></h2>
                <ul>
                    <?php foreach ($names as $name): ?>
                        <li>
                            <?php $label = str_starts_with($name, 'templates/') ? substr($name, strlen('templates/')) : $name; ?>
                            <a href="<?= e($editorUrl . '?file=' . rawurlencode($name)) ?>" title="<?= e($name) ?>"
                                <?= $file === $name ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <?php if ($document === null): ?>
            <p>一覧からファイルを選択してください。</p>
        <?php else: ?>
            <form class="nx-theme-editor-form" action="<?= e(url('admin/themes/editor/update')) ?>" method="post">
                <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="theme" value="<?= e($theme) ?>">
                <input type="hidden" name="file" value="<?= e($file) ?>">
                <input type="hidden" name="revision" value="<?= e($revision ?? $document['revision']) ?>">
                <label for="nx-theme-source"><?= e($file) ?></label>
                <p class="form-help" id="nx-theme-source-help">
                    <?php if (str_ends_with($file, '.php')): ?>HTMLを編集できます。PHP部分はそのまま使います。<?php endif; ?>
                    保存すると公開サイトに反映されます。
                </p>
                <?php if (!$document['compatible']): ?>
                    <p class="error-message" role="status">テーマの更新でPHP部分が変わったため、公開サイトには元のテンプレートを表示しています。</p>
                    <details>
                        <summary>現在のテーマの元のコード</summary>
                        <pre><?= e($document['original']) ?></pre>
                    </details>
                <?php endif; ?>
                <textarea id="nx-theme-source" name="content" rows="22" wrap="off" spellcheck="false"
                    aria-describedby="nx-theme-source-help"><?= "\n" . e($draft ?? $document['content']) ?></textarea>
                <div class="admin-form-actions">
                    <button type="submit" name="action" value="save">保存</button>
                    <?php if ($document['edited']): ?>
                        <button type="submit" name="action" value="restore" class="button secondary">テーマの元の内容に戻す</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
