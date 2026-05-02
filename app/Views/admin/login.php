<?php require BASE_PATH . '/app/Views/layout.php'; ?>

<div class="container">
    <div class="card">
        <h1>ログイン</h1>

        <?php if (!empty($error)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form method="post" action="<?php echo BASE_URL; ?>/admin/login">
            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="email">
            </div>

            <div class="form-group">
                <label>パスワード</label>
                <input type="password" name="password">
            </div>

            <button type="submit">ログイン</button>
        </form>
    </div>
</div>

</body>
</html>