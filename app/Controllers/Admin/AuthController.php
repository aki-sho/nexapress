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
        $username = trim(
            $_POST['username'] ?? ''
        );

        $password =
            $_POST['password'] ?? '';

        if (
            $username === '' ||
            $password === ''
        ) {
            $this->showLoginError(
                'ユーザー名とパスワードを入力してください。',
                $username
            );

            return;
        }

        $user = User::findByUsername(
            $username
        );

        if (
            !$user ||
            !password_verify(
                $password,
                $user['password_hash']
            )
        ) {
            $this->showLoginError(
                'ユーザー名またはパスワードが正しくありません。',
                $username
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
        string $username
    ): void {
        $this->view('admin/login', [
            'error' => $error,
            'username' => $username,
        ]);
    }
}