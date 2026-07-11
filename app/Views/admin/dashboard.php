<?php $title = 'ダッシュボード'; ?>

<h1>ダッシュボード</h1>

<div class="admin-card">
    <h2>NexaPressの更新</h2>

    <?php if (!empty($updateInfo)): ?>
        <?php if (
            $updateInfo['update_available']
        ): ?>
            <p>
                新しいバージョン
                <strong>
                    v<?= e(
                        $updateInfo['latest_version']
                    ) ?>
                </strong>
                を利用できます。
            </p>

            <p>
                <a href="<?= url('admin/updates') ?>">
                    更新画面を開く
                </a>
            </p>
        <?php else: ?>
            <p>
                NexaPressは最新です。
            </p>
        <?php endif; ?>

    <?php elseif (!empty($updateError)): ?>
        <p>
            更新情報を確認できませんでした。
        </p>

        <p>
            <a href="<?= url('admin/updates') ?>">
                更新画面で再確認
            </a>
        </p>
    <?php endif; ?>
</div>