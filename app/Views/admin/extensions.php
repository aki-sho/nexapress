<?php
$title = '拡張機能';
?>

<h1>拡張機能</h1>

<section class="extension-upload">
    <h2>拡張機能を追加</h2>

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

<?php if (empty($extensions)): ?>
    <p>追加済みの拡張機能はありません。</p>
<?php else: ?>
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
                    <td><?= e($extension['description']) ?></td>
                    <td><?= e($extension['version']) ?></td>

                    <td>
                        <?= $extension['is_enabled'] ? '有効' : '無効' ?>
                    </td>

                    <td>
                        <?php if ($extension['is_enabled']): ?>
                            <?php if ($extension['has_dashboard']): ?>
                                <a href="<?= url(
                                    'admin/extensions/' .
                                    rawurlencode($extension['key']) .
                                    '/dashboard'
                                ) ?>">
                                    ダッシュボード
                                </a>
                            <?php endif; ?>

                            <form
                                action="<?= url(
                                    'admin/extensions/disable/' .
                                    rawurlencode($extension['key'])
                                ) ?>"
                                method="post"
                            >
                                <button type="submit">無効化</button>
                            </form>
                            <?php else: ?>
                                <form
                                    action="<?= url(
                                        'admin/extensions/enable/' .
                                        rawurlencode($extension['key'])
                                    ) ?>"
                                    method="post"
                                >
                                    <button type="submit">有効化</button>
                                </form>

                                <form
                                    action="<?= url(
                                        'admin/extensions/delete/' .
                                        rawurlencode($extension['key'])
                                    ) ?>"
                                    method="post"
                                    onsubmit="return confirm('この拡張機能を削除しますか？');"
                                >
                                    <button type="submit">削除</button>
                                </form>
                            <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>