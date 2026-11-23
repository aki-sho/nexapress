<?php
$editorUrl = url('admin/themes/editor');
$actionsUrl = url('admin/themes/editor/update');
?>
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
                            <?php
                            $label = str_starts_with($name, 'templates/') ? substr($name, strlen('templates/')) : $name;
                            $fileUrl = $editorUrl . '?file=' . rawurlencode($name);
                            $selection = $children[$name] ?? null;
                            ?>
                            <div class="nx-theme-file-row">
                                <a href="<?= e($fileUrl) ?>" title="<?= e($name) ?>"
                                    <?= $file === $name && $layer === 'parent' ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                                <?php if ($selection === null): ?>
                                    <form class="nx-theme-child-create" action="<?= e($actionsUrl) ?>" method="post">
                                        <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="theme" value="<?= e($theme) ?>">
                                        <input type="hidden" name="file" value="<?= e($name) ?>">
                                        <button type="submit" name="action" value="create_child"
                                            aria-label="<?= e($label . ' の子ファイルを作成') ?>">＋ 子ファイルを作成</button>
                                    </form>
                                <?php elseif ($selection === 'parent'): ?>
                                    <small class="nx-theme-file-status">選択中</small>
                                <?php endif; ?>
                            </div>
                            <?php if ($selection !== null): ?>
                                <div class="nx-theme-file-row nx-theme-child-row">
                                    <a href="<?= e($fileUrl . '&layer=child') ?>"
                                        <?= $file === $name && $layer === 'child' ? 'aria-current="page"' : '' ?>><?= e($label) ?>（子）</a>
                                    <?php if ($selection === 'child'): ?><small class="nx-theme-file-status">選択中</small><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <?php if ($document === null): ?>
            <p>一覧からファイルを選択してください。</p>
        <?php else: ?>
            <?php
            $selection = $children[$file] ?? 'parent';
            $useLabel = $layer === 'child' ? '子を使う' : '親を使う';
            ?>
            <form class="nx-theme-editor-form" action="<?= e($actionsUrl) ?>" method="post">
                <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="theme" value="<?= e($theme) ?>">
                <input type="hidden" name="file" value="<?= e($file) ?>">
                <input type="hidden" name="layer" value="<?= e($layer) ?>">
                <input type="hidden" name="revision" value="<?= e($revision ?? $document['revision']) ?>">
                <label for="nx-theme-source"><?= e($file) ?><?= $layer === 'child' ? '（子）' : (isset($children[$file]) ? '（親）' : '') ?></label>
                <p class="form-help" id="nx-theme-source-help">
                    <?php if (str_ends_with($file, '.php')): ?>HTMLを編集できます。PHP部分は参照用です。<?php endif; ?>
                    <?php if ($selection === $layer): ?>保存すると公開サイトに反映されます。
                    <?php else: ?>保存後に「<?= e($useLabel) ?>」を選ぶと公開サイトに反映されます。<?php endif; ?>
                </p>
                <?php if (!$document['compatible']): ?>
                    <p class="error-message" role="status">現在のテーマのPHP部分を反映してから、HTMLの変更を保存してください。</p>
                    <details>
                        <summary>現在のテーマの元のコード</summary>
                        <pre><?= e($document['original']) ?></pre>
                    </details>
                <?php endif; ?>
                <textarea id="nx-theme-source" name="content" rows="22" wrap="off" spellcheck="false"
                    aria-describedby="nx-theme-source-help"><?= "\n" . e($draft ?? $document['content']) ?></textarea>
                <div class="admin-form-actions">
                    <button type="submit" name="action" value="save">保存</button>
                    <?php if ($selection !== $layer): ?>
                        <button type="submit" name="action" value="use" class="button secondary"><?= e($useLabel) ?></button>
                    <?php endif; ?>
                    <?php if ($layer === 'parent' && $document['edited']): ?>
                        <button type="submit" name="action" value="restore" class="button secondary">テーマの元の内容に戻す</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
