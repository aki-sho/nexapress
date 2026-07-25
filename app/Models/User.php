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
    * ユーザー一覧を取得
    */
    public static function all(): array
    {
        $pdo = Database::connect();
        $users = Database::table('users');

        $stmt = $pdo->query("
            SELECT
                id,
                username,
                name,
                email,
                role,
                created_at
            FROM {$users}
            ORDER BY id ASC
        ");

        return $stmt->fetchAll();
    }

    /*
    * IDからユーザーを取得
    */
    public static function find(
        int $id
    ): ?array {
        $pdo = Database::connect();
        $users = Database::table('users');

        $stmt = $pdo->prepare("
            SELECT
                id,
                username,
                name,
                email,
                role,
                created_at
            FROM {$users}
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    /*
    * ユーザー名の重複を確認
    */
    public static function usernameExists(
        string $username,
        ?int $excludeId = null
    ): bool {
        $pdo = Database::connect();
        $users = Database::table('users');

        $sql = "
            SELECT COUNT(*)
            FROM {$users}
            WHERE username = :username
        ";

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $pdo->prepare($sql);

        $params = [
            ':username' => $username,
        ];

        if ($excludeId !== null) {
            $params[':exclude_id'] =
                $excludeId;
        }

        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /*
    * メールアドレスの重複を確認
    */
    public static function emailExists(
        string $email,
        ?int $excludeId = null
    ): bool {
        $pdo = Database::connect();
        $users = Database::table('users');

        $sql = "
            SELECT COUNT(*)
            FROM {$users}
            WHERE email = :email
        ";

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $pdo->prepare($sql);

        $params = [
            ':email' => $email,
        ];

        if ($excludeId !== null) {
            $params[':exclude_id'] =
                $excludeId;
        }

        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /*
    * ユーザーを追加
    */
    public static function create(
        array $data
    ): void {
        $pdo = Database::connect();
        $users = Database::table('users');

        $stmt = $pdo->prepare("
            INSERT INTO {$users} (
                username,
                name,
                email,
                password_hash,
                role
            )
            VALUES (
                :username,
                :name,
                :email,
                :password_hash,
                :role
            )
        ");

        $stmt->execute([
            ':username' => $data['username'],
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':password_hash' =>
                $data['password_hash'],
            ':role' => $data['role'],
        ]);
    }

    /*
    * ユーザーを更新
    */
    public static function update(
        int $id,
        array $data
    ): void {
        $pdo = Database::connect();
        $users = Database::table('users');

        $sql = "
            UPDATE {$users}
            SET
                username = :username,
                name = :name,
                email = :email,
                role = :role
        ";

        if (!empty($data['password_hash'])) {
            $sql .= ",
                password_hash =
                    :password_hash
            ";
        }

        $sql .= "
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);

        $params = [
            ':username' => $data['username'],
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':role' => $data['role'],
            ':id' => $id,
        ];

        if (!empty($data['password_hash'])) {
            $params[':password_hash'] =
                $data['password_hash'];
        }

        $stmt->execute($params);
    }

    /*
    * ユーザーを削除
    */
    public static function delete(
        int $id
    ): void {
        $pdo = Database::connect();
        $users = Database::table('users');

        $stmt = $pdo->prepare("
            DELETE FROM {$users}
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
        ]);
    }

    /*
    * 管理者数を取得
    */
    public static function administratorCount(): int
    {
        $pdo = Database::connect();
        $users = Database::table('users');

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM {$users}
            WHERE role = 'administrator'
        ");

        return (int) $stmt->fetchColumn();
    }

    /*
    * ユーザーが作成データを所有しているか確認
    */
    public static function hasRelatedContent(
        int $id
    ): bool {
        $pdo = Database::connect();

        $posts = Database::table('posts');
        $pages = Database::table('pages');
        $media = Database::table('media');

        $stmt = $pdo->prepare("
            SELECT
                EXISTS (
                    SELECT 1
                    FROM {$posts}
                    WHERE user_id = :posts_user_id
                )
                OR EXISTS (
                    SELECT 1
                    FROM {$pages}
                    WHERE user_id = :pages_user_id
                )
                OR EXISTS (
                    SELECT 1
                    FROM {$media}
                    WHERE user_id = :media_user_id
                )
        ");

        $stmt->execute([
            ':posts_user_id' => $id,
            ':pages_user_id' => $id,
            ':media_user_id' => $id,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}