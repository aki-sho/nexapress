<?php

use app\Core\Csrf;
use app\Models\CategoryPage;

$title = 'カテゴリ公開ページ編集';
$categoryId = (int)$category['id'];
$content = $categoryPage['content'] ?? '';
$postMode = $categoryPage['posts_mode'] ?? CategoryPage::DEFAULTS['posts_mode'];
$showTitle = in_array($categoryPage['show_title'] ?? null, ['0', 0], true) ? '0' : '1';
?>
<section class="admin-page">
    <div class="admin-page-header">
        <h1><?= e($category['name']) ?>の公開ページを編集</h1>
        <div class="admin-header-actions">
            <a class="button secondary" href="<?= e(url('admin/categories') . $sortQuery) ?>">カテゴリ一覧</a>
            <a class="button secondary" href="<?= e(category_url($category)) ?>" target="_blank" rel="noopener">公開ページを開く</a>
        </div>
    </div>
    <?php if ($notice): ?><p role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error-message" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form action="<?= e(url('admin/categories/page/' . $categoryId) . $sortQuery) ?>" method="post" enctype="multipart/form-data"
          class="post-form" data-editor-kind="category" data-editor-id="<?= $categoryId ?>" data-editor-unsaved="<?= $error ? '1' : '0' ?>">
        <input type="hidden" name="_csrf_token" value="<?= e(Csrf::token()) ?>">
        <div class="admin-inline-form">
            <div class="form-group">
                <label for="posts-mode">投稿一覧の表示</label>
                <select id="posts-mode" name="posts_mode">
                    <?php foreach (CategoryPage::POST_MODES as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $value === $postMode ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="show-title">カテゴリ名の見出し</label>
                <select id="show-title" name="show_title">
                    <option value="1" <?= $showTitle === '1' ? 'selected' : '' ?>>表示</option>
                    <option value="0" <?= $showTitle === '0' ? 'selected' : '' ?>>非表示</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="content">本文（任意）</label>
            <textarea id="content" name="content" rows="18"><?= e(is_string($content) ? $content : '') ?></textarea>
        </div>
        <button type="submit">保存</button>
    </form>
</section>
