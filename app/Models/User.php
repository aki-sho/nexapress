<?php

namespace app\Models;

use app\Core\Database;

class User
{
    /*
     * ユーザー名から取得
     */
    public static function findByUsername(
        string $username
    ): ?array {
        $pdo = Database::connect();

        $users = Database::table('users');

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$users}
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username,
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    /*
     * メールアドレスから取得
     */
    public static function findByEmail(
        string $email
    ): ?array {
        $pdo = Database::connect();

        $users = Database::table('users');

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$users}
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email,
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    /*
     * ユーザー名またはメールアドレスから取得
     */
    public static function findForLogin(
        string $login
    ): ?array {
        $pdo = Database::connect();

        $users = Database::table('users');

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$users}
            WHERE username = :username
               OR email = :email
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $login,
            ':email' => $login,
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }
}