<?php $title = 'メニュー管理'; $token = \app\Core\Csrf::token(); ?>
<section class="admin-page">
    <h1>メニュー管理</h1><p>上から順に公開サイトのメニューへ表示します。下書き・ゴミ箱の固定ページは表示されません。</p>
    <?php if (!empty($notice)): ?><p role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p role="alert" class="error-message"><?= e($error) ?></p><?php endif; ?>
    <?php foreach (array_merge($items, [['id' => null, 'label' => '', 'type' => 'page', 'reference_id' => null, 'url' => '']]) as $item): ?>
    <div class="admin-card navigation-editor">
        <h2><?= $item['id'] ? 'メニュー項目' : '新しい項目を追加' ?></h2>
        <form action="<?= e(url('admin/navigation/save')) ?>" method="post" class="navigation-item-form">
            <input type="hidden" name="_csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <div class="form-group"><label>表示名<input name="label" value="<?= e($item['label']) ?>" maxlength="255" required></label></div>
            <div class="form-group"><label>種類<select name="type" data-navigation-type>
                <?php foreach (['page' => '固定ページ', 'category' => 'カテゴリ', 'custom' => '任意リンク'] as $type => $label): ?>
                    <option value="<?= e($type) ?>" <?= $item['type'] === $type ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select></label></div>
            <?php foreach (['page' => $pages, 'category' => $categories] as $type => $choices): ?>
                <div class="form-group" data-navigation-target="<?= e($type) ?>"><label><?= $type === 'page' ? '固定ページ' : 'カテゴリ' ?>
                    <select name="reference_id" <?= $item['type'] !== $type ? 'disabled' : '' ?>>
                        <option value="">選択してください</option>
                        <?php foreach ($choices as $choice): ?><option value="<?= (int)$choice['id'] ?>" <?= (int)$item['reference_id'] === (int)$choice['id'] ? 'selected' : '' ?>><?= e(($choice['title'] ?? $choice['name']) . (($choice['status'] ?? '') === 'draft' ? '（下書き・公開時に表示）' : '')) ?></option><?php endforeach; ?>
                    </select></label></div>
            <?php endforeach; ?>
            <div class="form-group" data-navigation-target="custom"><label>URL（サイト内は / から入力）
                <input name="url" value="<?= e($item['url']) ?>" maxlength="2048" placeholder="/ または https://example.com/" <?= $item['type'] !== 'custom' ? 'disabled' : '' ?>></label></div>
            <button type="submit"><?= $item['id'] ? '保存' : '追加' ?></button>
        </form>
        <?php if ($item['id']): ?><div class="core-actions">
            <?php foreach (['up' => '上へ', 'down' => '下へ'] as $direction => $label): ?>
            <form action="<?= e(url('admin/navigation/move/' . $item['id'])) ?>" method="post">
                <input type="hidden" name="_csrf_token" value="<?= e($token) ?>"><input type="hidden" name="direction" value="<?= e($direction) ?>"><button class="secondary" type="submit"><?= e($label) ?></button>
            </form><?php endforeach; ?>
            <form action="<?= e(url('admin/navigation/delete/' . $item['id'])) ?>" method="post"><input type="hidden" name="_csrf_token" value="<?= e($token) ?>"><button class="secondary" type="submit">削除</button></form>
        </div><?php endif; ?>
    </div>
    <?php endforeach; ?>
</section>
<script src="<?= e(public_url('assets/js/navigation.js')) ?>" defer></script>
