<?php
$title = '拡張機能';
?>

<h1>拡張機能</h1>

<?php if (!empty($notice)): ?>
    <div class="extension-notice extension-notice--success">
        <?= e($notice) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="extension-notice extension-notice--error">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<section class="extension-section">
    <div class="extension-section__heading">
        <div>
            <h2>拡張機能を追加</h2>
            <p>利用する拡張機能を選択してください。</p>
        </div>
    </div>

    <?php if (!empty($catalogError)): ?>
        <div class="extension-notice extension-notice--error">
            <?= e($catalogError) ?>
        </div>
    <?php elseif (empty($catalogExtensions)): ?>
        <p>公開中の拡張機能はありません。</p>
    <?php else: ?>
        <div class="extension-card-grid">
            <?php foreach ($catalogExtensions as $extension): ?>
                <?php
                $imageUrl = !empty($extension['icon_url'])
                    ? $extension['icon_url']
                    : url('assets/images/extension-default.svg');
                ?>

                <article class="extension-card">
                    <div class="extension-card__image">
                        <img
                            src="<?= e($imageUrl) ?>"
                            alt="<?= e($extension['name']) ?>"
                            loading="lazy"
                        >
                    </div>

                    <div class="extension-card__body">
                        <h3><?= e($extension['name']) ?></h3>

                        <p class="extension-card__description">
                            <?= e($extension['description']) ?>
                        </p>

                        <div class="extension-card__footer">
                            <span>
                                バージョン
                                <?= e($extension['version']) ?>
                            </span>

                            <?php if ($extension['is_installed']): ?>
                                <button
                                    type="button"
                                    class="extension-button extension-button--disabled"
                                    disabled
                                >
                                    インストール済み
                                </button>
                            <?php else: ?>
                                <form
                                    action="<?= url(
                                        'admin/extensions/install/' .
                                        rawurlencode($extension['id'])
                                    ) ?>"
                                    method="post"
                                    onsubmit="return confirm('この拡張機能をインストールしますか？');"
                                >
                                    <button
                                        type="submit"
                                        class="extension-button"
                                    >
                                        インストール
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="extension-section extension-upload">
    <h2>ZIPファイルから追加</h2>

    <form
        action="<?= url('admin/extensions/upload') ?>"
        method="post"
        enctype="multipart/form-data"
    >
        <input
            type="file"
            name="extension_file"
            accept=".zip,application/zip"
            required
        >

        <button type="submit">アップロード</button>
    </form>

    <small>ZIP形式・最大10MB</small>
</section>

<section class="extension-section">
    <h2>インストール済み</h2>

    <?php if (empty($extensions)): ?>
        <p>追加済みの拡張機能はありません。</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>拡張機能名</th>
                        <th>説明</th>
                        <th>バージョン</th>
                        <th>状態</th>
                        <th>操作</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($extensions as $extension): ?>
                        <tr>
                            <td><?= e($extension['name']) ?></td>

                            <td>
                                <?= e($extension['description']) ?>
                            </td>

                            <td><?= e($extension['version']) ?></td>

                            <td>
                                <?= $extension['is_enabled']
                                    ? '有効'
                                    : '無効' ?>
                            </td>

                            <td>
                                <div class="extension-actions">
                                    <?php if ($extension['is_enabled']): ?>
                                        <?php if ($extension['has_dashboard']): ?>
                                            <a href="<?= url(
                                                'admin/extensions/' .
                                                rawurlencode(
                                                    $extension['key']
                                                ) .
                                                '/dashboard'
                                            ) ?>">
                                                設定
                                            </a>
                                        <?php endif; ?>

                                        <form
                                            action="<?= url(
                                                'admin/extensions/disable/' .
                                                rawurlencode(
                                                    $extension['key']
                                                )
                                            ) ?>"
                                            method="post"
                                        >
                                            <button type="submit">
                                                無効化
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form
                                            action="<?= url(
                                                'admin/extensions/enable/' .
                                                rawurlencode(
                                                    $extension['key']
                                                )
                                            ) ?>"
                                            method="post"
                                        >
                                            <button type="submit">
                                                有効化
                                            </button>
                                        </form>

                                        <form
                                            action="<?= url(
                                                'admin/extensions/delete/' .
                                                rawurlencode(
                                                    $extension['key']
                                                )
                                            ) ?>"
                                            method="post"
                                            onsubmit="return confirm('この拡張機能を削除しますか？');"
                                        >
                                            <button type="submit">
                                                削除
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>