<?php $title = $trash ? '固定ページのゴミ箱' : '固定ページ一覧'; $token = \app\Core\Csrf::token(); ?>
<section class="admin-page">
    <div class="admin-page-header"><h1><?= e($title) ?></h1>
        <a class="button" href="<?= e(url('admin/pages/create')) ?>">新規追加</a>
        <a class="button secondary" href="<?= e(url('admin/pages' . ($trash ? '' : '?trash=1'))) ?>"><?= $trash ? '固定ページ一覧' : 'ゴミ箱' ?></a>
    </div>
    <?php if (!empty($notice)): ?><p role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if (!$pages): ?><p>固定ページはありません。</p><?php else: ?>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr>
        <th>タイトル</th><th>スラッグ</th><th>状態</th><th>投稿者</th><th>操作</th>
    </tr></thead><tbody>
    <?php foreach ($pages as $page): ?><tr>
        <td><?= e($page['title']) ?></td><td><?= e($page['slug']) ?></td>
        <td><?= $page['status'] === 'published' ? '公開' : '下書き' ?></td><td><?= e($page['author_name'] ?? '') ?></td>
        <td><div class="core-actions">
        <?php if (!$trash): ?>
            <a href="<?= e(url('admin/pages/edit/' . $page['id'])) ?>">編集</a>
            <a href="<?= e(url('admin/pages/preview/' . $page['id'])) ?>" target="_blank" rel="noopener">プレビュー</a>
            <a href="<?= e(url('admin/pages/revisions/' . $page['id'])) ?>">履歴</a>
        <?php endif; ?>
        <?php foreach ($trash ? ['restore' => '下書きに復元', 'destroy' => '完全に削除'] :
            ['status' => $page['status'] === 'published' ? '下書きにする' : '公開する', 'delete' => 'ゴミ箱へ'] as $method => $label): ?>
            <form action="<?= e(url('admin/pages/' . $method . '/' . $page['id'])) ?>" method="post">
                <input type="hidden" name="_csrf_token" value="<?= e($token) ?>">
                <button type="submit" class="button small secondary" <?= $method === 'destroy' ? 'onclick="return confirm(\'完全に削除します。元に戻せません。\')"' : '' ?>><?= e($label) ?></button>
            </form>
        <?php endforeach; ?>
        </div></td>
    </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
