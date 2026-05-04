<?php

namespace app\Models;

use app\Core\Database;

class User
{
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([
            ':email' => $email,
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }
}