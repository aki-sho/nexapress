<?php

namespace app\Models;

use app\Core\Database;

class Category
{
    public static function findByName(string $name): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM ' . Database::table('categories') . ' WHERE name = ? ORDER BY id LIMIT 1');
        $stmt->execute([$name]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM ' . Database::table('categories') . ' WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

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
        self::createWithId($data);
    }

    public static function createWithId(array $data): int
    {
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
        return (int)$pdo->lastInsertId();
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
