<?php require BASE_PATH . '/app/Views/layout.php'; ?>

<div class="login-container">
    <div class="login-card">
        <h1>ログイン</h1>
        <p class="login-subtitle">NexaPress 管理画面</p>

        <?php if (!empty($error)): ?>
            <p class="error-message">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>

        <form method="post" action="<?php echo BASE_URL; ?>/admin/login">
            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>パスワード</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="login-button">ログイン</button>
        </form>

        <div class="login-footer">
            <a href="<?php echo BASE_URL; ?>/">サイトを見る</a>
        </div>
    </div>
</div>

</body>
</html>