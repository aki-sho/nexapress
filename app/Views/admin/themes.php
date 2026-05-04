<?php $title = 'テーマ設定'; ?>

<h1>テーマ設定</h1>

<div class="card">
    <p>公開サイトで使用するテーマを選択できます。</p>
</div>

<?php if (empty($themes)): ?>
    <div class="card">
        <p>利用できるテーマがありません。</p>
    </div>
<?php else: ?>
    <div class="theme-list">
        <?php foreach ($themes as $theme): ?>
            <div class="theme-card">
                <h2><?= e($theme['name']) ?></h2>

                <p class="theme-id">
                    フォルダ名：<?= e($theme['id']) ?>
                </p>

                <?php if (!empty($theme['description'])): ?>
                    <p><?= e($theme['description']) ?></p>
                <?php endif; ?>

                <?php if ($theme['id'] === $activeTheme): ?>
                    <p class="theme-active">現在使用中</p>
                <?php else: ?>
                    <form action="<?= url('admin/themes/update') ?>" method="post">
                        <input type="hidden" name="theme" value="<?= e($theme['id']) ?>">
                        <button type="submit">このテーマを使う</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>