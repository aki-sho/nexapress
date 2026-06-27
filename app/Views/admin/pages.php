<?php $title = '固定ページ一覧'; ?>

<h1>固定ページ一覧</h1>

<p>
    <a class="button" href="<?= url('admin/pages/create') ?>">新規追加</a>
</p>

<?php if (empty($pages)): ?>
    <div class="admin-card">
        <p>固定ページはまだありません。</p>
    </div>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>タイトル</th>
                <th>状態</th>
                <th>投稿者</th>
                <th>作成日</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $page): ?>
                <tr>
                    <td><?= e($page['id'] ?? '') ?></td>
                    <td>
                        <a href="<?= page_url($page) ?>" target="_blank">
                            <?= e($page['title'] ?? '') ?>
                        </a>
                    </td>
                    <td><?= e($page['status'] ?? '') ?></td>
                    <td><?= e($page['author_name'] ?? '-') ?></td>
                    <td><?= e($page['created_at'] ?? '') ?></td>
                    <td class="table-actions">
                        <a href="<?= url('admin/pages/edit/' . ($page['id'] ?? '')) ?>">編集</a>

                        <form action="<?= url('admin/pages/status/' . ($page['id'] ?? '')) ?>" method="post">
                            <button type="submit" class="button small">
                                <?= ($page['status'] ?? '') === 'published' ? '下書きにする' : '公開する' ?>
                            </button>
                        </form>

                        <form action="<?= url('admin/pages/delete/' . ($page['id'] ?? '')) ?>" method="post">
                            <button type="submit" class="button danger small" onclick="return confirm('削除しますか？')">削除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>