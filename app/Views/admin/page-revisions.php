<?php $title = '固定ページの編集履歴'; ?>
<section class="admin-page">
    <h1><?= e($title) ?>：<?= e($page['title']) ?></h1>
    <p><a href="<?= e(url('admin/pages/edit/' . $page['id'])) ?>">編集へ戻る</a></p>
    <?php if (!empty($notice)): ?><p role="status"><?= e($notice) ?></p><?php endif; ?>
    <p>更新前の内容を最新50件まで保存します。復元するときは、現在の内容も履歴に残します。</p>
    <?php if (!$revisions): ?><p>編集履歴はまだありません。</p><?php endif; ?>
    <?php foreach ($revisions as $revision): ?>
    <details class="admin-card">
        <summary><?= e($revision['created_at']) ?> ／ <?= e($revision['editor_name'] ?? '削除済みユーザー') ?> ／ <?= e($revision['title']) ?></summary>
        <p>スラッグ：<?= e($revision['slug']) ?> ／ <?= $revision['status'] === 'published' ? '公開' : '下書き' ?></p>
        <pre class="revision-content"><?= e($revision['content']) ?></pre>
        <form action="<?= e(url('admin/pages/revisions/' . $page['id'] . '/restore/' . $revision['id'])) ?>" method="post">
            <input type="hidden" name="_csrf_token" value="<?= e(\app\Core\Csrf::token()) ?>">
            <button type="submit" onclick="return confirm('この編集履歴を復元しますか？')">この内容を復元</button>
        </form>
    </details>
    <?php endforeach; ?>
</section>
