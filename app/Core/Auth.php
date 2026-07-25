<?php

namespace app\Core;

class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /*
    * ログインユーザーの権限を取得
    */
    public static function role(): ?string
    {
        $user = self::user();

        return $user['role'] ?? null;
    }

    public static function login(
        array $user
    ): void {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect_to('admin/login');
            exit;
        }
    }
}