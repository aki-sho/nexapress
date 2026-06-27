<?php
$title = 'メディア';

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
            $modalId = 'media-modal-' . ($media['id'] ?? '');
            ?>

            <div class="media-card">
                <button type="button" class="media-thumb-button" onclick="document.getElementById('<?= e($modalId) ?>').showModal()">
                    <?php if ($isImage): ?>
                        <img src="<?= e($url) ?>" alt="<?= e($media['title'] ?? '') ?>" class="media-thumb">
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
                            <img src="<?= e($url) ?>" alt="<?= e($media['title'] ?? '') ?>" class="media-large-image">
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

                            <?php if ($isImage): ?>
                                <div class="admin-card">
                                    <p><strong>画像編集</strong></p>
                                    <p>今後の拡張用エリアです。</p>
                                    <button type="button" disabled>画像編集は未実装</button>
                                </div>
                            <?php endif; ?>

                            <button type="submit">保存</button>
                        </form>

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