<?php
$title = $trash ? '固定ページのゴミ箱' : '固定ページ一覧';
$token = \app\Core\Csrf::token();
$listFilters = $trash ? ['trash' => '1'] : [];
$listParameters = $listFilters + $sort->parameters();
$listQuery = $listParameters ? '?' . http_build_query($listParameters) : '';
?>
<section class="admin-page">
    <div class="admin-page-header"><h1><?= e($title) ?></h1>
        <div class="admin-header-actions">
        <a class="button" href="<?= e(url('admin/pages/create')) ?>">新規追加</a>
        <a class="button secondary" href="<?= e(url('admin/pages' . ($trash ? '' : '?trash=1'))) ?>"><?= $trash ? '固定ページ一覧' : 'ゴミ箱' ?></a>
        </div>
    </div>
    <?php if (!empty($notice)): ?><p role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if (!$pages): ?><p><?= $trash ? 'ゴミ箱は空です。' : '固定ページはありません。' ?></p><?php else: ?>
    <?php
    $bulkActions = \app\Models\ContentBulk::actions('pages', $trash);
    $bulkActionUrl = url('admin/pages/bulk') . $listQuery;
    require __DIR__ . '/content-bulk-form.php';
    ?>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr>
        <th scope="col" class="content-bulk-selection">
            <input type="checkbox" form="content-bulk" data-content-bulk-all aria-label="この一覧をすべて選択" hidden>
        </th>
        <?php
        $columns = ['title' => 'タイトル', 'slug' => 'スラッグ', 'status' => '状態', 'author' => '投稿者'];
        if (!$trash) {
            $columns['public_url'] = '公開ページ';
        }
        foreach ($columns as $key => $label) {
            $sort->heading($key, $label, 'admin/pages', $listFilters);
        }
        ?>
        <th scope="col">操作</th>
    </tr></thead><tbody>
    <?php foreach ($pages as $page): ?><tr>
        <td class="content-bulk-selection">
            <input type="checkbox" form="content-bulk" name="selected_ids[]" value="<?= (int)$page['id'] ?>"
                aria-label="<?= e($page['title'] . 'を選択') ?>">
        </td>
        <td><?= e($page['title']) ?></td><td><?= e($page['slug']) ?></td>
        <td><?= $page['status'] === 'published' ? '公開' : '下書き' ?></td><td><?= e($page['author_name'] ?? '') ?></td>
        <?php if (!$trash): ?>
            <td>
                <?php if ($page['status'] === 'published'): ?>
                    <?php $publicUrl = page_url($page); ?>
                    <a class="admin-public-link" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e($publicUrl) ?></a>
                <?php else: ?>
                    公開後に表示
                <?php endif; ?>
            </td>
        <?php endif; ?>
        <td><div class="core-actions">
        <?php if (!$trash): ?>
            <a href="<?= e(url('admin/pages/edit/' . $page['id'])) ?>">編集</a>
            <a href="<?= e(url('admin/pages/preview/' . $page['id'])) ?>" target="_blank" rel="noopener">プレビュー</a>
            <a href="<?= e(url('admin/pages/revisions/' . $page['id'])) ?>">履歴</a>
        <?php endif; ?>
        <?php foreach ($trash ? ['restore' => '下書きに復元', 'destroy' => '完全に削除'] :
            ['status' => $page['status'] === 'published' ? '下書きにする' : '公開する', 'delete' => 'ゴミ箱へ'] as $method => $label): ?>
            <form action="<?= e(url('admin/pages/' . $method . '/' . $page['id']) . $listQuery) ?>" method="post">
                <input type="hidden" name="_csrf_token" value="<?= e($token) ?>">
                <button type="submit" class="button small secondary" <?= $method === 'destroy' ? 'onclick="return confirm(\'完全に削除します。元に戻せません。\')"' : '' ?>><?= e($label) ?></button>
            </form>
        <?php endforeach; ?>
        </div></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
    <!-- Keep this after the row inputs so a truncated submission cannot apply only some selections. -->
    <input type="hidden" name="selection_complete" form="content-bulk" value="1">
    <?php endif; ?>
</section>
