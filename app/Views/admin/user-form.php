<?php

use app\Core\Csrf;

$title = $editing
    ? 'ユーザー編集'
    : 'ユーザー追加';

$username = $user['username'] ?? '';
$name = $user['name'] ?? '';
$email = $user['email'] ?? '';
$selectedRole = $user['role'] ?? 'viewer';
?>

<section class="admin-page">
    <div class="admin-page-header">
        <h1><?= e($title) ?></h1>

        <a
            class="button secondary"
            href="<?= url('admin/users') ?>"
        >
            ユーザー一覧へ戻る
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <p class="error-message">
            <?= e($error) ?>
        </p>
    <?php endif; ?>

    <div class="admin-card user-form-card">
        <form
            action="<?= e($action) ?>"
            method="post"
            class="user-form"
        >
            <input
                type="hidden"
                name="_csrf_token"
                value="<?= e(Csrf::token()) ?>"
            >

            <div class="form-group">
                <label for="username">
                    ユーザー名
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= e($username) ?>"
                    autocomplete="username"
                    required
                >

                <p class="form-help">
                    半角英数字、ハイフン、
                    アンダーバーが使用できます。
                </p>
            </div>

            <div class="form-group">
                <label for="name">
                    表示名
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= e($name) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">
                    メールアドレス
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($email) ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="role">
                    権限
                </label>

                <select
                    id="role"
                    name="role"
                    required
                >
                    <?php foreach ($roles as $role => $settings): ?>
                        <option
                            value="<?= e($role) ?>"
                            <?= $selectedRole === $role
                                ? 'selected'
                                : '' ?>
                        >
                            <?= e(
                                $settings['label']
                                ?? $role
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="password">
                    パスワード
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    <?= !$editing ? 'required' : '' ?>
                >

                <?php if ($editing): ?>
                    <p class="form-help">
                        変更しない場合は空欄にしてください。
                    </p>
                <?php else: ?>
                    <p class="form-help">
                        8文字以上で入力してください。
                    </p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password_confirmation">
                    パスワード確認
                </label>

                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    <?= !$editing ? 'required' : '' ?>
                >
            </div>

            <button type="submit">
                保存
            </button>
        </form>
    </div>
</section>