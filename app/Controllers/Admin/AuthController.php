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
            $this->redirect('/admin');
        }

        $this->view('admin/login');
    }

    public function authenticate(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->view('admin/login', [
                'error' => 'メールアドレスとパスワードを入力してください。'
            ]);
            return;
        }

        $user = User::findByEmail($email);

        if (!$user) {
            $this->view('admin/login', [
                'error' => 'ログイン情報が正しくありません。'
            ]);
            return;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->view('admin/login', [
                'error' => 'ログイン情報が正しくありません。'
            ]);
            return;
        }

        Auth::login($user);

        $this->redirect('/admin');
    }

    public function logout(): void
    {
        Auth::logout();

        $this->redirect('/admin/login');
    }
}