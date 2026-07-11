<?php $title = 'ダッシュボード'; ?>

<h1>ダッシュボード</h1>

<div class="admin-card">
    <p>使用中のバージョン：v<?= e($version ?? 'unknown') ?></p>
    <p>管理画面にログインしました。</p>

    <p>
        <a class="button" href="<?= url('admin/posts') ?>">投稿一覧へ</a>
    </p>
</div>