<?php
$title = empty($post['id']) ? '新規投稿' : '投稿編集';

$postTitle = $post['title'] ?? '';
$slug = $post['slug'] ?? '';
$categoryId = $post['category_id'] ?? '';
$content = $post['content'] ?? '';
$status = $post['status'] ?? 'draft';
?>

<section class="admin-page">
    <div class="admin-page-header">
        <h2><?= e($title) ?></h2>
        <a class="button secondary" href="<?= url('admin/posts') ?>">投稿一覧へ戻る</a>
    </div>

    <?php if (!empty($error)): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>

    <form action="<?= e($action) ?>" method="post" class="post-form">
        <div class="form-group">
            <label for="title">タイトル</label>
            <input type="text" id="title" name="title" value="<?= e($postTitle) ?>" required>
        </div>

        <div class="form-group">
            <label for="slug">スラッグ</label>
            <input type="text" id="slug" name="slug" value="<?= e($slug) ?>" required>
        </div>


        <div class="form-group">
            <label for="category_id">カテゴリ</label>
            <select id="category_id" name="category_id">
                <option value="">未分類</option>

                <?php foreach ($categories ?? [] as $category): ?>
                    <option value="<?= e($category['id']) ?>" <?= (string)$categoryId === (string)$category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="content">本文</label>
            <textarea id="content" name="content" rows="12" required><?= e($content) ?></textarea>
        </div>

        <div class="form-group">
            <label for="status">公開状態</label>
            <select id="status" name="status">
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>下書き</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>公開</option>
            </select>
        </div>

        <button type="submit">保存</button>
    </form>
</section>