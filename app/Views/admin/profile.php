<?php $title = '自分のプロフィール'; ?>
<section class="admin-page">
    <h1><?= e($title) ?></h1>
    <?php if (!empty($notice)): ?><p role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="error-message" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form action="<?= e(url('admin/profile')) ?>" method="post" class="admin-card">
        <input type="hidden" name="_csrf_token" value="<?= e(\app\Core\Csrf::token()) ?>">
        <p>ユーザー名：<?= e($profile['username']) ?></p>
        <p>権限：<?= e(\app\Core\Permission::roleLabel($profile['role'])) ?></p>
        <div class="form-group"><label for="profile-name">表示名</label>
            <input id="profile-name" name="name" value="<?= e($profile['name']) ?>" maxlength="100" required autocomplete="name"></div>
        <div class="form-group"><label for="profile-email">メールアドレス</label>
            <input id="profile-email" type="email" name="email" value="<?= e($profile['email']) ?>" maxlength="255" required autocomplete="email"></div>
        <div class="form-group"><label for="current-password">現在のパスワード（メール・パスワード変更時に必要）</label>
            <input id="current-password" type="password" name="current_password" autocomplete="current-password"></div>
        <div class="form-group"><label for="new-password">新しいパスワード（変更する場合のみ）</label>
            <input id="new-password" type="password" name="password" minlength="8" autocomplete="new-password"></div>
        <div class="form-group"><label for="password-confirmation">新しいパスワードの確認</label>
            <input id="password-confirmation" type="password" name="password_confirmation" autocomplete="new-password"></div>
        <button type="submit">保存</button>
    </form>
</section>
