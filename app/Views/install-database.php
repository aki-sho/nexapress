<?php

$title = 'データベース設定';

$form = $form ?? [];

?>

<div class="container">
    <div class="card">
        <h1>データベース設定</h1>

        <p>
            NexaPressが使用するデータベースの
            接続情報を入力してください。
        </p>

        <?php if (!empty($error)): ?>
            <p class="error-message">
                <?= e($error) ?>
            </p>
        <?php endif; ?>

        <form
            method="post"
            action="<?= url('install/database') ?>"
        >
            <div class="form-group">
                <label for="db_name">
                    データベース名
                </label>

                <input
                    type="text"
                    id="db_name"
                    name="db_name"
                    value="<?= e($form['db_name'] ?? '') ?>"
                    required
                >

                <small>
                    NexaPressで使用する作成済みの
                    データベース名です。
                </small>
            </div>

            <div class="form-group">
                <label for="db_user">
                    データベースのユーザー名
                </label>

                <input
                    type="text"
                    id="db_user"
                    name="db_user"
                    value="<?= e($form['db_user'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="db_pass">
                    データベースのパスワード
                </label>

                <input
                    type="password"
                    id="db_pass"
                    name="db_pass"
                    value=""
                >

                <small>
                    パスワードが設定されていない場合は
                    空欄にします。
                </small>
            </div>

            <div class="form-group">
                <label for="db_host">
                    データベースホスト
                </label>

                <input
                    type="text"
                    id="db_host"
                    name="db_host"
                    value="<?= e(
                        $form['db_host'] ?? 'localhost'
                    ) ?>"
                    required
                >

                <small>
                    通常は localhost のままです。
                </small>
            </div>

            <div class="form-group">
                <label for="table_prefix">
                    テーブル接頭辞
                </label>

                <input
                    type="text"
                    id="table_prefix"
                    name="table_prefix"
                    value="<?= e(
                        $form['table_prefix'] ?? 'nx_'
                    ) ?>"
                    required
                >

                <small>
                    複数のNexaPressを同じデータベースで
                    使用する場合は変更してください。
                </small>
            </div>

            <button type="submit">
                データベース接続を確認する
            </button>
        </form>

        <p>
            <a href="<?= url('install') ?>">
                前の画面へ戻る
            </a>
        </p>
    </div>
</div>