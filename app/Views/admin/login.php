<?php $title = 'ログイン'; ?>

<div class="login-page">
    <div class="login-card">
        <h1>管理画面ログイン</h1>

        <?php if (!empty($error)): ?>
            <p class="error-message"><?= e($error) ?></p>
        <?php endif; ?>

        <form action="<?= url('admin/login') ?>" method="post">
            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="button full">ログイン</button>
        </form>

        <p class="login-back">
            <a href="<?= url('') ?>">サイトへ戻る</a>
        </p>
    </div>
</div>