<?php

namespace app\Models;

use app\Core\Database;

class Category
{
    public static function all(): array
    {
        $pdo = Database::connect();

        $categories =
            Database::table('categories');

        $stmt = $pdo->query("
            SELECT *
            FROM {$categories}
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::connect();

        $categories =
            Database::table('categories');

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$categories}
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
        ]);

        $category = $stmt->fetch();

        return $category ?: null;
    }

    public static function create(
        array $data
    ): void {
        $pdo = Database::connect();

        $categories =
            Database::table('categories');

        $stmt = $pdo->prepare("
            INSERT INTO {$categories} (
                name,
                slug
            )
            VALUES (
                :name,
                :slug
            )
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
        ]);
    }

    public static function update(
        int $id,
        array $data
    ): void {
        $pdo = Database::connect();

        $categories =
            Database::table('categories');

        $stmt = $pdo->prepare("
            UPDATE {$categories}
            SET name = :name,
                slug = :slug,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':id' => $id,
        ]);
    }

    public static function delete(
        int $id
    ): void {
        $pdo = Database::connect();

        $categories =
            Database::table('categories');

        $stmt = $pdo->prepare("
            DELETE FROM {$categories}
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
        ]);
    }
}