<?php require BASE_PATH . '/app/Views/admin/layout.php'; ?>

<h1>投稿一覧</h1>

<p>
    <a href="/NexaPress/public/admin/posts/create" class="btn">新規追加</a>
</p>

<table class="table">
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
                <td><?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($post['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($post['author_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <a href="/NexaPress/public/admin/posts/edit/<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">編集</a>

                    <form method="post" action="/NexaPress/public/admin/posts/status/<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>" style="display:inline;">
                        <button type="submit">
                            <?php echo $post['status'] === 'published' ? '下書きにする' : '公開する'; ?>
                        </button>
                    </form>

                    <form method="post" action="/NexaPress/public/admin/posts/delete/<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>" style="display:inline;">
                        <button type="submit" class="btn-danger" onclick="return confirm('削除しますか？');">削除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</main>
</div>
</body>
</html>