<?php $title = '投稿一覧'; ?>

<h1>投稿一覧</h1>

<p>
    <a class="button" href="<?= url('admin/posts/create') ?>">新規追加</a>
</p>

<?php if (empty($posts)): ?>
    <div class="admin-card">
        <p>投稿はまだありません。</p>
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
            <?php foreach ($posts as $post): ?>
                <tr>
                    <td><?= e($post['id'] ?? '') ?></td>
                    <td><?= e($post['title'] ?? '') ?></td>
                    <td><?= e($post['status'] ?? '') ?></td>
                    <td><?= e($post['author_name'] ?? $post['name'] ?? '-') ?></td>
                    <td><?= e($post['created_at'] ?? '') ?></td>
                    <td class="table-actions">
                        <a href="<?= url('admin/posts/edit/' . ($post['id'] ?? '')) ?>">編集</a>

                        <form action="<?= url('admin/posts/status/' . ($post['id'] ?? '')) ?>" method="post">
                            <button type="submit" class="button small">
                                <?= ($post['status'] ?? '') === 'published' ? '下書きにする' : '公開する' ?>
                            </button>
                        </form>

                        <form action="<?= url('admin/posts/delete/' . ($post['id'] ?? '')) ?>" method="post">
                            <button type="submit" class="button danger small" onclick="return confirm('削除しますか？')">削除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>