<?php

use app\Core\Csrf;

$title = 'NexaPressの更新';
?>

<h1>NexaPressの更新</h1>
<?php if (!empty($updateResult)): ?>
    <div class="admin-card">
        <h2>更新が完了しました</h2>

        <p>
            NexaPressを
            <strong>
                v<?= e(
                    $updateResult['previous_version']
                ) ?>
            </strong>
            から
            <strong>
                v<?= e(
                    $updateResult['current_version']
                ) ?>
            </strong>
            へ更新しました。
        </p>

        <?php if (
            !empty($updateResult['migrations'])
        ): ?>
            <p>
                実行したDB更新：
                <?= e(implode(
                    '、',
                    $updateResult['migrations']
                )) ?>
            </p>
        <?php endif; ?>

        <p>
            バックアップ：
            <?= e(basename(
                $updateResult['backup_path']
            )) ?>
        </p>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="admin-card">
        <p class="error-message">
            <?= e($error) ?>
        </p>

        <form
            action="<?= url('admin/updates/check') ?>"
            method="post"
        >
            <input
                type="hidden"
                name="_token"
                value="<?= e(Csrf::token()) ?>"
            >

            <button type="submit">
                もう一度確認
            </button>
        </form>
    </div>

<?php elseif (!empty($updateInfo)): ?>
    <div class="admin-card">
        <p>
            現在のバージョン：
            <strong>
                v<?= e($updateInfo['current_version']) ?>
            </strong>
        </p>

        <p>
            最新バージョン：
            <strong>
                v<?= e($updateInfo['latest_version']) ?>
            </strong>
        </p>

        <p>
            最終確認日時：
            <?= e($checkedAt ?? '') ?>
        </p>
    </div>

    <?php if ($updateInfo['update_available']): ?>
        <div class="admin-card">
            <h2>新しいバージョンを利用できます</h2>

            <?php if ($updateInfo['release_name'] !== ''): ?>
                <p>
                    <?= e($updateInfo['release_name']) ?>
                </p>
            <?php endif; ?>

            <?php if ($updateInfo['release_notes'] !== ''): ?>
                <div class="update-release-notes">
                    <?= nl2br(
                        e($updateInfo['release_notes'])
                    ) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($updateInfo['asset'])): ?>
                <p>
                    更新パッケージ：
                    <?= e($updateInfo['asset']['name']) ?>
                </p>

                <p>
                    更新パッケージが見つかりました。
                </p>
                <form
                    action="<?= url('admin/updates/install') ?>"
                    method="post"
                    onsubmit="return confirm(
                        'NexaPressを更新しますか？'
                    );"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= e(Csrf::token()) ?>"
                    >

                    <button
                        type="submit"
                        class="update-action-button"
                    >
                        今すぐ更新
                    </button>
                </form>

                <p>
                    <small>
                        更新前にファイルとデータベースが
                        自動バックアップされます。
                    </small>
                </p>
            <?php else: ?>
                <p class="error-message">
                    更新用ZIPが見つかりません。
                </p>
            <?php endif; ?>

            <?php if ($updateInfo['release_url'] !== ''): ?>
                <p>
                    <a
                        href="<?= e(
                            $updateInfo['release_url']
                        ) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        更新内容をGitHubで確認
                    </a>
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="admin-card">
            <h2>NexaPressは最新です</h2>

            <p>
                現在利用できる更新はありません。
            </p>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <form
            action="<?= url('admin/updates/check') ?>"
            method="post"
        >
            <input
                type="hidden"
                name="_token"
                value="<?= e(Csrf::token()) ?>"
            >

            <button type="submit">
                最新版を再確認
            </button>
        </form>
    </div>
<?php endif; ?>