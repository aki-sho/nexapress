<?php

namespace app\Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(
                random_bytes(32)
            );
        }

        return $_SESSION['_csrf_token'];
    }

    public static function validate(?string $token): bool
    {
        if (
            empty($_SESSION['_csrf_token']) ||
            empty($token)
        ) {
            return false;
        }

        return hash_equals(
            $_SESSION['_csrf_token'],
            $token
        );
    }

    public static function requireValid(
        ?string $token
    ): void {
        if (self::validate($token)) {
            return;
        }

        http_response_code(419);
        exit('不正なリクエストです。');
    }

    public static function regenerate(): void
    {
        $_SESSION['_csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }
}