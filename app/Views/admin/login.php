<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン | NexaPress</title>
    <link rel="stylesheet" href="/NexaPress/public/assets/css/style.css">
</head>
<body class="admin-login-page">

<div class="login-container">
    <div class="login-card">
        <h1>NexaPress</h1>
        <p class="login-subtitle">管理画面ログイン</p>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form action="/NexaPress/public/admin/login" method="post">
            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input
                    type="text"
                    id="email"
                    name="email"
                    value=""
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
            </div>

            <button type="submit" class="login-button">ログイン</button>
        </form>

        <div class="login-footer">
            <a href="/NexaPress/public/">サイトを見る</a>
        </div>
    </div>
</div>

</body>
</html>