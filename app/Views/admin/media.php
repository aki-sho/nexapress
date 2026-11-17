<?php
use app\Core\Csrf;
use app\Models\Media;

$title = 'メディア';
$hasImageEditor = false;
$csrf = Csrf::token();
$folderLabels = [0 => '未分類'] + $folders;
$currentFolderName = $viewFolder === null ? 'すべてのメディア' : $folderLabels[$viewFolder];

function media_file_url(array $media): string
{
    return public_url($media['file_path'] ?? '');
}

function media_size_label(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}
?>

<div class="media-library-heading">
    <h1>メディア</h1>
    <button type="button" class="button secondary" data-open-media-dialog="media-create-folder">新規フォルダ</button>
</div>

<?php if ($notice): ?>
    <p class="<?= $notice['error'] ? 'error-message' : 'media-notice' ?>"
        role="<?= $notice['error'] ? 'alert' : 'status' ?>"><?= e($notice['message']) ?></p>
<?php endif; ?>

<dialog id="media-create-folder" class="media-folder-dialog" aria-labelledby="media-create-folder-title">
    <form action="<?= e(url('admin/media/folders/create')) ?>" method="post">
        <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="view_folder" value="<?= e((string)$viewFolder) ?>">
        <h2 id="media-create-folder-title">新規フォルダ</h2>
        <div class="form-group">
            <label for="media-folder-name">フォルダ名</label>
            <input type="text" id="media-folder-name" name="name" maxlength="100" required autofocus>
        </div>
        <div class="media-folder-dialog-actions">
            <button type="button" class="button secondary" data-close-media-dialog>キャンセル</button>
            <button type="submit">作成</button>
        </div>
    </form>
</dialog>

<form action="<?= url('admin/media/upload') ?>" method="post" enctype="multipart/form-data" class="media-upload-form">
    <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="view_folder" value="<?= e((string)$viewFolder) ?>">
    <div class="form-group">
        <label for="media_file">ファイルをアップロード</label>
        <input type="file" id="media_file" name="media_file" required>
        <small>保存先 <?= e($viewFolder > 0 ? $currentFolderName : '未分類') ?> ・ 最大 <?= e(media_size_label($maxUploadSize)) ?></small>
    </div>

    <button type="submit">アップロード</button>
</form>

<div class="media-library">
    <aside class="media-folders">
        <h2>フォルダ</h2>
        <nav aria-label="メディアのフォルダ">
            <a class="media-folder-link" href="<?= e(url('admin/media')) ?>"
                <?php if ($viewFolder === null): ?>aria-current="page"<?php endif; ?>>すべて</a>
            <?php foreach ($folderLabels as $folderId => $folderName): ?>
                <a class="media-folder-link" href="<?= e(url('admin/media?folder=' . $folderId)) ?>"
                    <?php if ($viewFolder === $folderId): ?>aria-current="page"
                    <?php else: ?>data-media-drop-folder="<?= (int)$folderId ?>"<?php endif; ?>><?= e($folderName) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <section class="media-list" aria-labelledby="media-list-title">
        <div class="media-list-heading">
            <h2 id="media-list-title"><?= e($currentFolderName) ?></h2>
            <?php if ($viewFolder > 0): ?>
                <form action="<?= e(url('admin/media/folders/delete/' . $viewFolder)) ?>" method="post"
                    data-media-confirm="<?= e('フォルダ「' . $currentFolderName . '」を削除しますか？ 中のメディアは「未分類」に戻ります。') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
                    <button type="submit" class="button secondary small" data-media-confirm-button disabled>フォルダを削除</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($mediaItems === []): ?>
            <p class="media-empty">ここに表示するメディアはありません。</p>
        <?php else: ?>
            <form action="<?= e(url('admin/media/move')) ?>" method="post" class="media-bulk-actions" data-media-actions>
                <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="view_folder" value="<?= e((string)$viewFolder) ?>">
                <input type="hidden" name="media_ids" value="[]">
                <label class="media-select-all"><input type="checkbox" data-media-select-all>すべて選択</label>
                <span data-media-count aria-live="polite">0件選択</span>
                <select name="folder_id" aria-label="移動先のフォルダ">
                    <option value="">移動先を選択</option>
                    <?php foreach ($folderLabels as $folderId => $folderName): ?>
                        <?php if ($folderId !== $viewFolder): ?>
                            <option value="<?= (int)$folderId ?>"><?= e($folderName) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button secondary small" data-media-move disabled>移動</button>
                <button type="submit" class="button danger small" data-media-bulk-delete disabled
                    formaction="<?= e(url('admin/media/bulk-delete')) ?>">選択した項目を削除</button>
            </form>

            <div class="media-grid">
                <?php foreach ($mediaItems as $media): ?>
                    <?php
                    $id = (int)$media['id'];
                    $mediaTitle = $media['title'];
                    $url = media_file_url($media);
                    $isImage = ($media['file_type'] ?? '') === 'image';
                    $canEdit = $isImage && in_array($media['mime_type'] ?? '', Media::EDITABLE_IMAGE_TYPES, true);
                    $hasImageEditor = $hasImageEditor || $canEdit;
                    $modalId = 'media-modal-' . $id;
                    $deleteId = 'media-delete-' . $id;
                    ?>

                    <div class="media-card" data-media-card data-media-title="<?= e($mediaTitle) ?>" draggable="true">
                        <div class="media-card-tools">
                            <input type="checkbox" data-media-select value="<?= $id ?>" aria-label="<?= e($mediaTitle . 'を選択') ?>">
                            <form id="<?= e($deleteId) ?>" action="<?= e(url('admin/media/delete/' . $id)) ?>" method="post"
                                data-media-confirm="<?= e('「' . $mediaTitle . '」を削除しますか？ 記事などで使用中の場合は表示できなくなります。') ?>">
                                <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="view_folder" value="<?= e((string)$viewFolder) ?>">
                                <button type="submit" class="media-card-delete" data-media-confirm-button disabled
                                    aria-label="<?= e($mediaTitle . 'を削除') ?>">削除</button>
                            </form>
                        </div>
                        <button type="button" class="media-thumb-button" data-open-media-dialog="<?= e($modalId) ?>"
                            aria-label="<?= e($mediaTitle . 'の詳細') ?>">
                            <?php if ($isImage): ?>
                                <img src="<?= e($url) ?>" alt="<?= e($mediaTitle) ?>" class="media-thumb" draggable="false"
                                    loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="media-file-icon"><?= e(strtoupper($media['file_type'] ?? 'file')) ?></span>
                            <?php endif; ?>
                        </button>

                        <div class="media-card-body">
                            <strong><?= e($mediaTitle) ?></strong>
                            <small><?= e($media['mime_type'] ?? '') ?></small>
                            <small><?= e(media_size_label((int)($media['file_size'] ?? 0))) ?></small>
                            <?php if ($viewFolder === null): ?>
                                <small class="media-card-folder"><?= e($folderLabels[$media['folder_id'] ?? 0] ?? '未分類') ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <dialog id="<?= e($modalId) ?>" class="media-modal" aria-label="<?= e($mediaTitle . 'の詳細') ?>">
                        <div class="media-modal-inner">
                            <div class="media-modal-preview">
                                <?php if ($isImage): ?>
                                    <img src="<?= e($url) ?>" alt="<?= e($mediaTitle) ?>" class="media-large-image" loading="lazy" decoding="async">
                                <?php elseif (($media['file_type'] ?? '') === 'audio'): ?>
                                    <audio controls src="<?= e($url) ?>"></audio>
                                <?php elseif (($media['file_type'] ?? '') === 'video'): ?>
                                    <video controls src="<?= e($url) ?>"></video>
                                <?php else: ?>
                                    <p><a href="<?= e($url) ?>" target="_blank" rel="noopener">ファイルを開く</a></p>
                                <?php endif; ?>
                            </div>

                            <div class="media-modal-side">
                                <form action="<?= e(url('admin/media/update/' . $id)) ?>" method="post">
                                    <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="view_folder" value="<?= e((string)$viewFolder) ?>">
                                    <div class="form-group">
                                        <label for="media-title-<?= $id ?>">タイトル</label>
                                        <input type="text" id="media-title-<?= $id ?>" name="title" value="<?= e($mediaTitle) ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="media-description-<?= $id ?>">説明</label>
                                        <textarea id="media-description-<?= $id ?>" name="description" rows="5"><?= e($media['description'] ?? '') ?></textarea>
                                    </div>

                                    <button type="submit">保存</button>
                                </form>

                                <?php if ($canEdit): ?>
                                    <button type="button" class="button secondary" data-open-image-editor
                                        data-image-url="<?= e($url) ?>"
                                        data-image-title="<?= e($mediaTitle) ?>"
                                        data-image-bytes="<?= (int)($media['file_size'] ?? 0) ?>"
                                        data-image-mime="<?= e($media['mime_type']) ?>"
                                        data-save-url="<?= e(url('admin/media/edit/' . $id)) ?>">画像編集</button>
                                <?php endif; ?>

                                <div class="media-modal-actions">
                                    <button type="submit" form="<?= e($deleteId) ?>" class="button danger small"
                                        data-media-confirm-button disabled>削除</button>
                                    <button type="button" class="button secondary small" data-close-media-dialog>閉じる</button>
                                </div>
                            </div>
                        </div>
                    </dialog>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script src="<?= e(nx_asset_url('assets/js/media-library.js')) ?>" defer></script>

<?php if ($hasImageEditor): ?>
    <dialog class="media-editor" data-media-editor aria-labelledby="media-editor-title"
        data-csrf="<?= e($csrf) ?>" data-max-dimension="<?= (int)$maxEditDimension ?>">
        <div class="media-editor-header">
            <div>
                <h2 id="media-editor-title">画像編集</h2>
                <p data-editor-title></p>
            </div>
            <button type="button" class="button secondary" data-editor-close>閉じる</button>
        </div>
        <div class="media-editor-layout">
            <div class="media-editor-preview">
                <div class="media-editor-stage" data-editor-stage data-mode="resize">
                    <canvas data-editor-canvas aria-label="編集中の画像"></canvas>
                    <div class="media-editor-frame" data-editor-frame hidden>
                        <?php foreach (['nw' => '左上', 'n' => '上辺', 'ne' => '右上', 'w' => '左辺',
                            'e' => '右辺', 'sw' => '左下', 's' => '下辺', 'se' => '右下'] as $handle => $label): ?>
                            <button type="button" class="media-editor-handle" data-resize-handle="<?= $handle ?>"
                                aria-label="<?= $label ?>のハンドル"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="media-editor-hint" data-editor-hint></p>
            </div>
            <fieldset class="media-editor-controls" data-editor-controls disabled>
                <legend class="media-editor-visually-hidden">画像の編集操作</legend>
                <div class="media-editor-actions" aria-label="編集モード" role="group">
                    <button type="button" class="button secondary" data-editor-mode="resize" aria-pressed="true">リサイズ</button>
                    <button type="button" class="button secondary" data-editor-mode="crop" aria-pressed="false">切り抜き</button>
                </div>
                <div data-editor-options="resize">
                    <label for="media-editor-width">横幅（px）</label>
                    <input type="number" min="1" max="<?= (int)$maxEditDimension ?>" step="1" id="media-editor-width" data-editor-width>
                    <div class="media-editor-actions" aria-label="横幅のプリセット" role="group">
                        <?php foreach ([800, 1200, 1600] as $width): ?>
                            <button type="button" class="button secondary" data-editor-preset="<?= $width ?>"><?= $width ?> px</button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div data-editor-options="crop" hidden>
                    <label for="media-editor-ratio">切り抜きの比率</label>
                    <select id="media-editor-ratio" data-editor-ratio>
                        <option value="free">自由</option>
                        <option value="1">正方形</option>
                        <option value="16:9">16:9</option>
                    </select>
                </div>
                <div class="media-editor-actions" aria-label="回転と取り消し" role="group">
                    <button type="button" class="button secondary" data-editor-turn="-1">左へ90°</button>
                    <button type="button" class="button secondary" data-editor-turn="1">右へ90°</button>
                    <button type="button" class="button secondary" data-editor-undo disabled>元に戻す</button>
                </div>
                <div>
                    <label for="media-editor-quality">保存画質</label>
                    <select id="media-editor-quality" data-editor-quality>
                        <option value="standard">標準</option>
                        <option value="light">軽量</option>
                    </select>
                </div>
                <dl class="media-editor-sizes">
                    <dt>元画像</dt><dd data-editor-original></dd>
                    <dt>編集後</dt><dd data-editor-output></dd>
                </dl>
                <button type="button" class="button" data-editor-save>別画像として保存</button>
            </fieldset>
        </div>
        <p class="media-editor-status" role="status" data-editor-status></p>
    </dialog>
    <script src="<?= e(nx_asset_url('assets/js/media-editor.js')) ?>" defer></script>
<?php endif; ?>
