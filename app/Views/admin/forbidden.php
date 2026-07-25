<?php

$title = $title
    ?? 'アクセス権限がありません';
?>

<section class="admin-page">
    <div class="admin-card forbidden-card">
        <h1>403</h1>

        <h2>アクセス権限がありません</h2>

        <p>
            このページを操作する権限がありません。
        </p>

        <a
            class="button"
            href="<?= url('admin') ?>"
        >
            ダッシュボードへ戻る
        </a>
    </div>
</section>