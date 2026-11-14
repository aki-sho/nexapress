<?php

namespace app\Core;

use app\Models\User;

class Auth
{
    private static bool $verified = false;

    public static function startSession(): void
    {
        $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
        $secure = !in_array($https, ['', 'off', '0'], true);
        $cookieSecure = filter_var(ini_get('session.cookie_secure'), FILTER_VALIDATE_BOOLEAN);
        $sameSite = strcasecmp((string)ini_get('session.cookie_samesite'), 'Strict') === 0 ? 'Strict' : 'Lax';
        session_name('NexaPress' . substr(hash('sha256', BASE_PATH), 0, 24));
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure || $cookieSecure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);

        if (!session_start()) {
            http_response_code(503);
            header('Cache-Control: no-store');
            exit('セッションを開始できません。');
        }
    }

    public static function check(): bool
    {
        if (!isset($_SESSION['user'])) {
            return false;
        }

        if (!self::$verified) {
            $id = (int)($_SESSION['user']['id'] ?? 0);
            $fingerprint = $_SESSION['auth_fingerprint'] ?? null;
            $passwordHash = $id > 0 && is_string($fingerprint) ? User::passwordHash($id) : null;
            if ($passwordHash === null || !hash_equals(self::fingerprint($passwordHash), $fingerprint)) {
                self::logout();
                return false;
            }

            self::$verified = true;
        }

        return true;
    }

    public static function user(): ?array
    {
        return self::check() ? $_SESSION['user'] : null;
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
        $fingerprint = self::fingerprint($user['password_hash']);
        if (!session_regenerate_id(true)) {
            self::logout();
            http_response_code(503);
            header('Cache-Control: no-store');
            exit('セッションを更新できません。');
        }

        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        $_SESSION['auth_fingerprint'] = $fingerprint;
        self::$verified = true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        self::$verified = false;
        header('Cache-Control: no-store');
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect_to('admin/login');
            exit;
        }
    }

    private static function fingerprint(string $passwordHash): string
    {
        return hash('sha256', BASE_PATH . "\0" . $passwordHash);
    }
}
