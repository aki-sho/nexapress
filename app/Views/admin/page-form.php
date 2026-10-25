<?php $title = empty($page['id']) ? '新規固定ページ' : '固定ページ編集'; ?>
<section class="admin-page">
    <div class="admin-page-header"><h1><?= e($title) ?></h1>
        <a class="button secondary" href="<?= e(url('admin/pages')) ?>">固定ページ一覧</a>
        <?php if (!empty($page['id'])): ?>
            <a class="button secondary" href="<?= e(url('admin/pages/preview/' . $page['id'])) ?>" target="_blank" rel="noopener">プレビュー</a>
            <a class="button secondary" href="<?= e(url('admin/pages/revisions/' . $page['id'])) ?>">編集履歴</a>
        <?php endif; ?>
    </div>
    <?php if (!empty($notice)): ?><p role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="error-message" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form action="<?= e($action) ?>" method="post" class="post-form" data-editor-kind="page" data-editor-id="<?= (int)($page['id'] ?? 0) ?>" data-editor-unsaved="<?= !empty($error) ? '1' : '0' ?>">
        <input type="hidden" name="_csrf_token" value="<?= e(\app\Core\Csrf::token()) ?>">
        <div class="form-group"><label for="title">タイトル</label>
            <input id="title" name="title" value="<?= e($page['title'] ?? '') ?>" maxlength="255" required></div>
        <div class="form-group"><label for="slug">スラッグ</label>
            <input id="slug" name="slug" value="<?= e($page['slug'] ?? '') ?>" maxlength="255"></div>
        <div class="form-group"><label for="content">本文</label>
            <textarea id="content" name="content" rows="18" required><?= e($page['content'] ?? '') ?></textarea></div>
        <div class="form-group"><label for="status">公開状態</label><select id="status" name="status">
            <option value="draft" <?= ($page['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>下書き</option>
            <option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>公開</option>
        </select></div>
        <button type="submit">保存</button>
    </form>
</section>
