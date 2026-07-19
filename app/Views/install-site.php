<?php

$title = 'サイト設定';

$form = $form ?? [];

?>

<div class="container">
    <div class="card">
        <h1>サイト設定</h1>

        <p>
            サイト情報と最初の管理者アカウントを
            設定してください。
        </p>

        <?php if (!empty($error)): ?>
            <p class="error-message">
                <?= e($error) ?>
            </p>
        <?php endif; ?>

        <form
            method="post"
            action="<?= url('install/site') ?>"
        >
            <h2>サイト情報</h2>

            <div class="form-group">
                <label for="site_title">
                    サイトのタイトル
                </label>

                <input
                    type="text"
                    id="site_title"
                    name="site_title"
                    value="<?= e(
                        $form['site_title'] ?? ''
                    ) ?>"
                    required
                >
            </div>

            <h2>管理者アカウント</h2>

            <div class="form-group">
                <label for="admin_username">
                    ユーザー名
                </label>

                <input
                    type="text"
                    id="admin_username"
                    name="admin_username"
                    value="<?= e(
                        $form['admin_username'] ?? ''
                    ) ?>"
                    minlength="3"
                    maxlength="100"
                    required
                >

                <small>
                    管理画面へのログインに使用します。
                </small>
            </div>

            <div class="form-group">
                <label for="admin_password">
                    パスワード
                </label>

                <input
                    type="password"
                    id="admin_password"
                    name="admin_password"
                    minlength="8"
                    required
                >

                <small>
                    8文字以上で入力してください。
                </small>
            </div>

            <div class="form-group">
                <label for="admin_email">
                    メールアドレス
                </label>

                <input
                    type="email"
                    id="admin_email"
                    name="admin_email"
                    value="<?= e(
                        $form['admin_email'] ?? ''
                    ) ?>"
                    required
                >
            </div>

            <h2>検索エンジンでの表示</h2>

            <div class="form-group">
                <label>
                    <input
                        type="checkbox"
                        name="discourage_search_engines"
                        value="1"
                        <?php if (
                            !empty(
                                $form[
                                    'discourage_search_engines'
                                ]
                            )
                        ): ?>
                            checked
                        <?php endif; ?>
                    >

                    検索エンジンがサイトを
                    インデックスしないようにする
                </label>

                <small>
                    この設定は検索エンジンへのお願いであり、
                    完全な非公開を保証するものではありません。
                </small>
            </div>

            <button type="submit">
                NexaPressをインストールする
            </button>
        </form>

        <p>
            <a href="<?= url('install/database') ?>">
                データベース設定へ戻る
            </a>
        </p>
    </div>
</div>