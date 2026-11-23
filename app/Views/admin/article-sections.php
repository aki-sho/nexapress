<?php if (($articleId ?? 0) > 0 && $legacySections && \app\Core\Permission::can('themes.manage')): ?>
    <details class="article-display">
        <summary>既存のセクションを編集</summary>
        <iframe class="article-sections-frame" title="既存のセクションを編集"
            loading="lazy" src="<?= e(url('admin/design?layout=' . $displayKind . ':' . $articleId)) ?>"></iframe>
    </details>
<?php endif; ?>
