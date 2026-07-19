<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Models\User;

class AuthController extends Controller
{
    public function login(): void
    {
        if (Auth::check()) {
            redirect_to('admin');
        }

        $this->view('admin/login');
    }

    public function authenticate(): void
    {
        $login = trim(
            $_POST['login'] ?? ''
        );

        $password =
            $_POST['password'] ?? '';

        if (
            $login === '' ||
            $password === ''
        ) {
            $this->showLoginError(
                'ユーザー名またはメールアドレスと'
                . 'パスワードを入力してください。',
                $login
            );

            return;
        }

        $user = User::findForLogin($login);

        if (
            !$user ||
            !password_verify(
                $password,
                $user['password_hash']
            )
        ) {
            $this->showLoginError(
                'ログイン情報が正しくありません。',
                $login
            );

            return;
        }

        Auth::login($user);

        redirect_to('admin');
    }

    public function logout(): void
    {
        Auth::logout();

        redirect_to('admin/login');
    }

    private function showLoginError(
        string $error,
        string $login
    ): void {
        $this->view('admin/login', [
            'error' => $error,
            'login' => $login,
        ]);
    }
}