<?php
$title = !empty($category['id']) ? 'カテゴリ編集' : 'カテゴリ管理';

$categoryName = $category['name'] ?? '';
$slug = $category['slug'] ?? '';

$action = !empty($category['id'])
    ? url('admin/categories/update/' . $category['id'])
    : url('admin/categories/store');
?>

<section class="admin-page">
    <div class="admin-page-header">
        <h2><?= e($title) ?></h2>
    </div>

    <form action="<?= e($action) ?>" method="post" class="post-form admin-inline-form">
        <div class="form-group">
            <label for="name">カテゴリ名</label>
            <input type="text" id="name" name="name" value="<?= e($categoryName) ?>" required>
        </div>

        <div class="form-group">
            <label for="slug">スラッグ</label>
            <input type="text" id="slug" name="slug" value="<?= e($slug) ?>" required>
        </div>

        <div class="admin-form-actions">
        <button type="submit">保存</button>

        <?php if (!empty($category['id'])): ?>
            <a class="button secondary" href="<?= url('admin/categories') ?>">新規追加に戻る</a>
        <?php endif; ?>
        </div>
    </form>

    <hr class="admin-section-divider">

    <h3 class="admin-section-heading">カテゴリ一覧</h3>

    <div class="admin-table-wrap"><table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>カテゴリ名</th>
                <th>スラッグ</th>
                <th scope="col">公開ページ</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="5">カテゴリはまだありません。</td>
                </tr>
            <?php else: ?>
                <?php foreach ($categories as $item): ?>
                    <?php $publicUrl = category_url($item); ?>
                    <tr>
                        <td><?= e($item['id']) ?></td>
                        <td><?= e($item['name']) ?></td>
                        <td><?= e($item['slug']) ?></td>
                        <td><a class="admin-public-link" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e($publicUrl) ?></a></td>
                        <td>
                            <a class="button secondary" href="<?= url('admin/categories/edit/' . $item['id']) ?>">編集</a>

                            <form action="<?= url('admin/categories/delete/' . $item['id']) ?>" method="post" style="display:inline;" onsubmit="return confirm('このカテゴリを削除しますか？');">
                                <button type="submit">削除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table></div>
</section>
