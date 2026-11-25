<?php
$title = !empty($category['id']) ? 'カテゴリ編集' : 'カテゴリ管理';

$categoryName = $category['name'] ?? '';
$slug = $category['slug'] ?? '';
$categoryId = $category['id'] ?? null;
$listRoute = $categoryId ? 'admin/categories/edit/' . $categoryId : 'admin/categories';
$sortParameters = $sort->parameters();
$sortQuery = $sortParameters ? '?' . http_build_query($sortParameters) : '';
$action = url($categoryId ? 'admin/categories/update/' . $categoryId : 'admin/categories/store') . $sortQuery;
$token = \app\Core\Csrf::token();
?>

<section class="admin-page">
    <div class="admin-page-header">
        <h2><?= e($title) ?></h2>
    </div>

    <form action="<?= e($action) ?>" method="post" class="post-form admin-inline-form">
        <input type="hidden" name="_csrf_token" value="<?= e($token) ?>">
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

        <?php if ($categoryId): ?>
            <a class="button secondary" href="<?= e(url('admin/categories/page/' . $categoryId) . $sortQuery) ?>">公開ページを編集</a>
            <a class="button secondary" href="<?= e(url('admin/categories') . $sortQuery) ?>">新規追加に戻る</a>
        <?php endif; ?>
        </div>
    </form>

    <hr class="admin-section-divider">

    <h3 class="admin-section-heading">カテゴリ一覧</h3>

    <div class="admin-table-wrap"><table class="admin-table">
        <thead>
            <tr>
                <?php
                $columns = ['id' => 'ID', 'name' => 'カテゴリ名', 'slug' => 'スラッグ', 'public_url' => '公開ページ'];
                foreach ($columns as $key => $label) {
                    $sort->heading($key, $label, $listRoute);
                }
                ?>
                <th scope="col">操作</th>
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
                        <td><div class="core-actions">
                            <a class="button secondary" href="<?= e(url('admin/categories/edit/' . $item['id']) . $sortQuery) ?>">編集</a>
                            <a class="button secondary" href="<?= e(url('admin/categories/page/' . $item['id']) . $sortQuery) ?>">公開ページを編集</a>
                            <form action="<?= e(url('admin/categories/delete/' . $item['id']) . $sortQuery) ?>" method="post" onsubmit="return confirm('このカテゴリと公開ページの編集内容を削除しますか？');">
                                <input type="hidden" name="_csrf_token" value="<?= e($token) ?>">
                                <button type="submit">削除</button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table></div>
</section>
