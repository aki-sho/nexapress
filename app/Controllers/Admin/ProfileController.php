<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Csrf;
use app\Models\User;
use Throwable;

class ProfileController extends Controller
{
    public function index(): void
    {
        $user = User::find((int)Auth::user()['id']);
        if (!$user) {
            Auth::logout();
            redirect_to('admin/login');
        }
        $notice = $_SESSION['profile_notice'] ?? null;
        unset($_SESSION['profile_notice']);
        $this->view('admin/profile', ['profile' => $user, 'notice' => $notice]);
    }

    public function update(): void
    {
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        $user = User::find((int)Auth::user()['id']);
        if (!$user) {
            Auth::logout();
            redirect_to('admin/login');
        }
        $value = static fn(string $key): string => is_string($_POST[$key] ?? '') ? $_POST[$key] ?? '' : '';
        $name = trim($value('name'));
        $email = trim($value('email'));
        $password = $value('password');
        $error = null;
        if ($name === '' || nx_text_length($name) > 100 || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '表示名は1〜100文字、メールアドレスは正しい形式で入力してください。';
        } elseif (User::emailExists($email, (int)$user['id'])) {
            $error = 'このメールアドレスは使用されています。';
        } elseif (($email !== $user['email'] || $password !== '') &&
            !User::verifyPassword((int)$user['id'], $value('current_password'))) {
            $error = 'メールまたはパスワードの変更には、現在のパスワードが必要です。';
        } elseif ($password !== '' && (strlen($password) < 8 || strlen($password) > 72 ||
            $password !== $value('password_confirmation'))) {
            $error = '新しいパスワードは8〜72バイトで、確認用と同じ値を入力してください。';
        }
        if (!$error) {
            try {
                // Keep the username and role from the database, never from form input.
                User::update((int)$user['id'], array_merge($user, [
                    'name' => $name, 'email' => $email,
                    'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
                ]));
                Auth::login(User::find((int)$user['id']));
                do_action('profile_saved', (int)$user['id']);
                $_SESSION['profile_notice'] = 'プロフィールを保存しました。';
                redirect_to('admin/profile');
            } catch (Throwable $exception) {
                $error = '保存できませんでした。入力内容を確認してください。';
            }
        }
        $this->view('admin/profile', ['profile' => array_merge($user, compact('name', 'email')), 'error' => $error]);
    }
}
