<?php
use app\Core\Csrf;
use app\Models\Media;

$title = 'メディア';
$hasImageEditor = false;

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

<h1>メディア</h1>

<form action="<?= url('admin/media/upload') ?>" method="post" enctype="multipart/form-data" class="media-upload-form">
    <div class="form-group">
        <label for="media_file">ファイルをアップロード</label>
        <input type="file" id="media_file" name="media_file" required>
        <small>最大アップロードサイズ: 100 MB</small>
    </div>

    <button type="submit">アップロード</button>
</form>

<?php if (empty($mediaItems)): ?>
    <div class="admin-card">
        <p>メディアはまだありません。</p>
    </div>
<?php else: ?>
    <div class="media-grid">
        <?php foreach ($mediaItems as $media): ?>
            <?php
            $url = media_file_url($media);
            $isImage = ($media['file_type'] ?? '') === 'image';
            $canEdit = $isImage && in_array($media['mime_type'] ?? '', Media::EDITABLE_IMAGE_TYPES, true);
            $hasImageEditor = $hasImageEditor || $canEdit;
            $modalId = 'media-modal-' . ($media['id'] ?? '');
            ?>

            <div class="media-card">
                <button type="button" class="media-thumb-button" onclick="document.getElementById('<?= e($modalId) ?>').showModal()">
                    <?php if ($isImage): ?>
                        <img src="<?= e($url) ?>" alt="<?= e($media['title'] ?? '') ?>" class="media-thumb" loading="lazy" decoding="async">
                    <?php else: ?>
                        <div class="media-file-icon">
                            <?= e(strtoupper($media['file_type'] ?? 'file')) ?>
                        </div>
                    <?php endif; ?>
                </button>

                <div class="media-card-body">
                    <strong><?= e($media['title'] ?? '') ?></strong>
                    <small><?= e($media['mime_type'] ?? '') ?></small>
                    <small><?= e(media_size_label((int)($media['file_size'] ?? 0))) ?></small>
                </div>
            </div>

            <dialog id="<?= e($modalId) ?>" class="media-modal">
                <div class="media-modal-inner">
                    <div class="media-modal-preview">
                        <?php if ($isImage): ?>
                            <img src="<?= e($url) ?>" alt="<?= e($media['title'] ?? '') ?>" class="media-large-image" loading="lazy" decoding="async">
                        <?php elseif (($media['file_type'] ?? '') === 'audio'): ?>
                            <audio controls src="<?= e($url) ?>"></audio>
                        <?php elseif (($media['file_type'] ?? '') === 'video'): ?>
                            <video controls src="<?= e($url) ?>"></video>
                        <?php else: ?>
                            <p><a href="<?= e($url) ?>" target="_blank">ファイルを開く</a></p>
                        <?php endif; ?>
                    </div>

                    <div class="media-modal-side">
                        <form action="<?= url('admin/media/update/' . ($media['id'] ?? '')) ?>" method="post">
                            <div class="form-group">
                                <label>タイトル</label>
                                <input type="text" name="title" value="<?= e($media['title'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label>説明</label>
                                <textarea name="description" rows="5"><?= e($media['description'] ?? '') ?></textarea>
                            </div>

                            <button type="submit">保存</button>
                        </form>

                        <?php if ($canEdit): ?>
                            <button type="button" class="button secondary" data-open-image-editor
                                data-image-url="<?= e($url) ?>"
                                data-image-title="<?= e($media['title'] ?? '') ?>"
                                data-image-bytes="<?= (int)($media['file_size'] ?? 0) ?>"
                                data-image-mime="<?= e($media['mime_type']) ?>"
                                data-save-url="<?= e(url('admin/media/edit/' . (int)$media['id'])) ?>">画像編集</button>
                        <?php endif; ?>

                        <form action="<?= url('admin/media/delete/' . ($media['id'] ?? '')) ?>" method="post">
                            <button type="submit" class="button danger small" onclick="return confirm('削除しますか？')">削除</button>
                        </form>

                        <button type="button" class="button secondary" onclick="document.getElementById('<?= e($modalId) ?>').close()">閉じる</button>
                    </div>
                </div>
            </dialog>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($hasImageEditor): ?>
    <dialog class="media-editor" data-media-editor aria-labelledby="media-editor-title"
        data-csrf="<?= e(Csrf::token()) ?>" data-max-dimension="<?= (int)$maxEditDimension ?>">
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
