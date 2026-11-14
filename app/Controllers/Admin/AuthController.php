<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Csrf;
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
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Auth::check()) {
            http_response_code(404);
            header('Cache-Control: no-store');
            echo '404 Not Found';
            return;
        }

        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        Auth::logout();

        // The core handles POST logout until an older Access Guard is updated.
        $path = class_exists(\NexaPressAccessGuard\LoginUrl::class, false)
            ? \NexaPressAccessGuard\LoginUrl::path()
            : '/admin/login';
        redirect_to(ltrim($path, '/'));
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
