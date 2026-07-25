<?php

use app\Core\Auth;
use app\Core\Csrf;
use app\Core\Permission;

$title = 'ユーザー管理';

$currentUser = Auth::user();

$errorCode = $_GET['error'] ?? '';

$errorMessages = [
    'self_delete' =>
        '現在ログイン中のユーザーは削除できません。',

    'last_admin' =>
        '最後の管理者は削除できません。',

    'user_has_content' =>
        '投稿・固定ページ・メディアを所有している'
        . 'ユーザーは削除できません。',
];

$errorMessage =
    $errorMessages[$errorCode] ?? '';
?>

<section class="admin-page">
    <div class="admin-page-header">
        <h1>ユーザー管理</h1>

        <a
            class="button"
            href="<?= url('admin/users/create') ?>"
        >
            新規ユーザー追加
        </a>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <p class="error-message">
            <?= e($errorMessage) ?>
        </p>
    <?php endif; ?>

    <?php if (empty($users)): ?>
        <div class="admin-card">
            <p>ユーザーは登録されていません。</p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ユーザー名</th>
                        <th>表示名</th>
                        <th>メールアドレス</th>
                        <th>権限</th>
                        <th>登録日</th>
                        <th>操作</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $isCurrentUser =
                            (int) ($currentUser['id'] ?? 0)
                            ===
                            (int) ($user['id'] ?? 0);
                        ?>

                        <tr>
                            <td>
                                <?= e($user['id'] ?? '') ?>
                            </td>

                            <td>
                                <?= e(
                                    $user['username']
                                    ?? ''
                                ) ?>

                                <?php if ($isCurrentUser): ?>
                                    <span class="user-current">
                                        ログイン中
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e($user['name'] ?? '') ?>
                            </td>

                            <td>
                                <?= e($user['email'] ?? '') ?>
                            </td>

                            <td>
                                <span class="role-badge">
                                    <?= e(
                                        Permission::roleLabel(
                                            $user['role']
                                            ?? ''
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= e(
                                    $user['created_at']
                                    ?? ''
                                ) ?>
                            </td>

                            <td class="table-actions">
                                <a
                                    class="button small"
                                    href="<?= url(
                                        'admin/users/edit/'
                                        . ($user['id'] ?? '')
                                    ) ?>"
                                >
                                    編集
                                </a>

                                <?php if (!$isCurrentUser): ?>
                                    <form
                                        action="<?= url(
                                            'admin/users/delete/'
                                            . ($user['id'] ?? '')
                                        ) ?>"
                                        method="post"
                                    >
                                        <input
                                            type="hidden"
                                            name="_csrf_token"
                                            value="<?= e(
                                                Csrf::token()
                                            ) ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="button danger small"
                                            onclick="return confirm(
                                                'このユーザーを削除しますか？'
                                            )"
                                        >
                                            削除
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>