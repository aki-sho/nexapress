<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Csrf;
use app\Core\Permission;
use app\Models\User;

class UserController extends Controller
{
    /*
     * ユーザー一覧
     */
    public function index(): void
    {
        Permission::require(
            'users.manage'
        );

        $this->view('admin/users', [
            'users' => User::all(),
        ]);
    }

    /*
     * ユーザー追加画面
     */
    public function create(): void
    {
        Permission::require(
            'users.manage'
        );

        $this->showForm(
            null,
            url('admin/users/store')
        );
    }

    /*
     * ユーザーを追加
     */
    public function store(): void
    {
        Permission::require(
            'users.manage'
        );

        Csrf::requireValid(
            $_POST['_csrf_token'] ?? null
        );

        $data = $this->formData();
        $error = $this->validate(
            $data
        );

        if ($error !== null) {
            $this->showForm(
                $data,
                url('admin/users/store'),
                $error
            );

            return;
        }

        $passwordHash = password_hash(
            $data['password'],
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            $this->showForm(
                $data,
                url('admin/users/store'),
                'パスワードを保存できませんでした。'
            );

            return;
        }

        User::create([
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $passwordHash,
            'role' => $data['role'],
        ]);

        redirect_to('admin/users');
    }

    /*
     * ユーザー編集画面
     */
    public function edit(
        int $id
    ): void {
        Permission::require(
            'users.manage'
        );

        $user = User::find($id);

        if (!$user) {
            http_response_code(404);
            echo 'ユーザーが見つかりません。';

            return;
        }

        $this->showForm(
            $user,
            url(
                'admin/users/update/' . $id
            ),
            null,
            true
        );
    }

    /*
     * ユーザーを更新
     */
    public function update(
        int $id
    ): void {
        Permission::require(
            'users.manage'
        );

        Csrf::requireValid(
            $_POST['_csrf_token'] ?? null
        );

        $user = User::find($id);

        if (!$user) {
            http_response_code(404);
            echo 'ユーザーが見つかりません。';

            return;
        }

        $data = $this->formData();
        $error = $this->validate(
            $data,
            $id,
            true
        );

        if (
            $error === null &&
            $user['role'] ===
                'administrator' &&
            $data['role'] !==
                'administrator' &&
            User::administratorCount() <= 1
        ) {
            $error =
                '最後の管理者の権限は変更できません。';
        }

        if ($error !== null) {
            $data['id'] = $id;

            $this->showForm(
                $data,
                url(
                    'admin/users/update/'
                    . $id
                ),
                $error,
                true
            );

            return;
        }

        $passwordHash = null;

        if ($data['password'] !== '') {
            $passwordHash = password_hash(
                $data['password'],
                PASSWORD_DEFAULT
            );

            if ($passwordHash === false) {
                $data['id'] = $id;

                $this->showForm(
                    $data,
                    url(
                        'admin/users/update/'
                        . $id
                    ),
                    'パスワードを保存できませんでした。',
                    true
                );

                return;
            }
        }

        User::update($id, [
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' =>
                $passwordHash,
            'role' => $data['role'],
        ]);

        redirect_to('admin/users');
    }

    /*
     * ユーザーを削除
     */
    public function delete(
        int $id
    ): void {
        Permission::require(
            'users.manage'
        );

        Csrf::requireValid(
            $_POST['_csrf_token'] ?? null
        );

        $loginUser = Auth::user();
        $user = User::find($id);

        if (!$user) {
            redirect_to('admin/users');
        }

        if (
            (int) $loginUser['id'] === $id
        ) {
            redirect_to(
                'admin/users?error=self_delete'
            );
        }

        if (
            $user['role'] ===
                'administrator' &&
            User::administratorCount() <= 1
        ) {
            redirect_to(
                'admin/users?error=last_admin'
            );
        }

        if (
            User::hasRelatedContent($id)
        ) {
            redirect_to(
                'admin/users'
                . '?error=user_has_content'
            );
        }

        User::delete($id);

        redirect_to('admin/users');
    }

    /*
     * フォーム入力値を取得
     */
    private function formData(): array
    {
        return [
            'username' => trim(
                $_POST['username'] ?? ''
            ),
            'name' => trim(
                $_POST['name'] ?? ''
            ),
            'email' => trim(
                $_POST['email'] ?? ''
            ),
            'role' => trim(
                $_POST['role'] ?? ''
            ),
            'password' =>
                $_POST['password'] ?? '',
            'password_confirmation' =>
                $_POST[
                    'password_confirmation'
                ] ?? '',
        ];
    }

    /*
     * 入力内容を確認
     */
    private function validate(
        array $data,
        ?int $excludeId = null,
        bool $editing = false
    ): ?string {
        if (
            $data['username'] === '' ||
            $data['name'] === '' ||
            $data['email'] === '' ||
            $data['role'] === ''
        ) {
            return '未入力の項目があります。';
        }

        if (
            !preg_match(
                '/^[A-Za-z0-9_-]+$/',
                $data['username']
            )
        ) {
            return
                'ユーザー名は半角英数字、'
                . 'ハイフン、アンダーバーのみ使用できます。';
        }

        if (
            !filter_var(
                $data['email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return
                'メールアドレスの形式が正しくありません。';
        }

        $roles = Permission::roles();

        if (
            !array_key_exists(
                $data['role'],
                $roles
            )
        ) {
            return '権限が正しくありません。';
        }

        if (
            User::usernameExists(
                $data['username'],
                $excludeId
            )
        ) {
            return
                '同じユーザー名が使用されています。';
        }

        if (
            User::emailExists(
                $data['email'],
                $excludeId
            )
        ) {
            return
                '同じメールアドレスが使用されています。';
        }

        if (
            !$editing &&
            $data['password'] === ''
        ) {
            return
                'パスワードを入力してください。';
        }

        if (
            $data['password'] !== '' &&
            strlen($data['password']) < 8
        ) {
            return
                'パスワードは8文字以上で入力してください。';
        }

        if (
            $data['password'] !==
            $data['password_confirmation']
        ) {
            return
                '確認用パスワードが一致しません。';
        }

        return null;
    }

    /*
     * ユーザーフォームを表示
     */
    private function showForm(
        ?array $user,
        string $action,
        ?string $error = null,
        bool $editing = false
    ): void {
        $this->view(
            'admin/user-form',
            [
                'user' => $user,
                'action' => $action,
                'error' => $error,
                'editing' => $editing,
                'roles' =>
                    Permission::roles(),
            ]
        );
    }
}