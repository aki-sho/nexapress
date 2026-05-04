<?php
$title = 'CMS インストール';
$hideHeader = true;
?>

<div class="container">
    <div class="card">
        <h1>CMS インストール</h1>

        <p>
            この画面では、CMSで使用するデータベース情報と、最初にログインする管理者アカウントを設定します。
        </p>

        <?php if (!empty($error)): ?>
            <p style="color: red;"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= url('install') ?>">
            <h2>データベース設定</h2>

            <p>
                XAMPPの初期設定では、DBホストは localhost、DBユーザーは root、DBパスワードは空欄です。
                DB名はこのCMS用の名前を入力してください。例：my_cms
            </p>

            <div class="form-group">
                <label>DBホスト</label>
                <input type="text" name="db_host" value="localhost">
                <small>通常は localhost のままで大丈夫です。</small>
            </div>

            <div class="form-group">
                <label>DB名</label>
                <input type="text" name="db_name" placeholder="例：my_cms">
                <small>このCMSで使うデータベース名です。存在しない場合は自動作成されます。</small>
            </div>

            <div class="form-group">
                <label>DBユーザー</label>
                <input type="text" name="db_user" value="root">
                <small>XAMPPの初期設定では root です。</small>
            </div>

            <div class="form-group">
                <label>DBパスワード</label>
                <input type="password" name="db_pass">
                <small>XAMPPの初期設定では空欄です。</small>
            </div>

            <h2>管理者設定</h2>

            <p>
                ここで作成した管理者情報を使って、インストール後に管理画面へログインします。
            </p>

            <div class="form-group">
                <label>管理者名</label>
                <input type="text" name="admin_name" placeholder="例：admin">
                <small>管理画面に表示される名前です。</small>
            </div>

            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="admin_email" placeholder="例：test@example.com">
                <small>ログイン時に使うメールアドレスです。</small>
            </div>

            <div class="form-group">
                <label>パスワード</label>
                <input type="password" name="admin_password">
                <small>管理画面ログイン用のパスワードです。</small>
            </div>

            <button type="submit">インストールする</button>
        </form>
    </div>
</div>