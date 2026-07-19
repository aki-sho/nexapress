<?php

namespace app\Models;

use app\Core\Database;

class Post
{
    public static function all(): array
    {
        $pdo = Database::connect();

        $posts = Database::table('posts');
        $users = Database::table('users');
        $categories = Database::table('categories');

        $stmt = $pdo->query("
            SELECT
                p.*,
                u.name AS author_name,
                c.name AS category_name,
                c.slug AS category_slug
            FROM {$posts} AS p
            LEFT JOIN {$users} AS u
                ON p.user_id = u.id
            LEFT JOIN {$categories} AS c
                ON p.category_id = c.id
            ORDER BY p.created_at DESC
        ");

        return $stmt->fetchAll();
    }

    public static function published(): array
    {
        $pdo = Database::connect();

        $posts = Database::table('posts');
        $categories = Database::table('categories');

        $stmt = $pdo->query("
            SELECT
                p.*,
                c.name AS category_name,
                c.slug AS category_slug
            FROM {$posts} AS p
            LEFT JOIN {$categories} AS c
                ON p.category_id = c.id
            WHERE p.status = 'published'
            ORDER BY p.published_at DESC
        ");

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::connect();

        $posts = Database::table('posts');

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$posts}
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
        ]);

        $post = $stmt->fetch();

        return $post ?: null;
    }

    public static function findBySlug(
        string $slug
    ): ?array {
        $pdo = Database::connect();

        $posts = Database::table('posts');
        $categories = Database::table('categories');

        $stmt = $pdo->prepare("
            SELECT
                p.*,
                c.name AS category_name,
                c.slug AS category_slug
            FROM {$posts} AS p
            LEFT JOIN {$categories} AS c
                ON p.category_id = c.id
            WHERE p.slug = :slug
              AND p.status = 'published'
            LIMIT 1
        ");

        $stmt->execute([
            ':slug' => $slug,
        ]);

        $post = $stmt->fetch();

        return $post ?: null;
    }

    public static function create(
        array $data
    ): void {
        $pdo = Database::connect();

        $posts = Database::table('posts');

        $publishedAt =
            $data['status'] === 'published'
                ? date('Y-m-d H:i:s')
                : null;

        $stmt = $pdo->prepare("
            INSERT INTO {$posts} (
                title,
                slug,
                content,
                status,
                user_id,
                category_id,
                published_at
            )
            VALUES (
                :title,
                :slug,
                :content,
                :status,
                :user_id,
                :category_id,
                :published_at
            )
        ");

        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':content' => $data['content'],
            ':status' => $data['status'],
            ':user_id' => $data['user_id'],
            ':category_id' =>
                $data['category_id'],
            ':published_at' => $publishedAt,
        ]);
    }

    public static function update(
        int $id,
        array $data
    ): void {
        $pdo = Database::connect();

        $posts = Database::table('posts');

        $post = self::find($id);

        if (!$post) {
            return;
        }

        $publishedAt =
            $post['published_at'] ?? null;

        if (
            $data['status'] === 'published' &&
            empty($publishedAt)
        ) {
            $publishedAt =
                date('Y-m-d H:i:s');
        }

        if ($data['status'] === 'draft') {
            $publishedAt = null;
        }

        $stmt = $pdo->prepare("
            UPDATE {$posts}
            SET title = :title,
                slug = :slug,
                content = :content,
                status = :status,
                category_id = :category_id,
                published_at = :published_at,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':content' => $data['content'],
            ':status' => $data['status'],
            ':category_id' =>
                $data['category_id'],
            ':published_at' => $publishedAt,
            ':id' => $id,
        ]);
    }

    public static function delete(
        int $id
    ): void {
        $pdo = Database::connect();

        $posts = Database::table('posts');

        $stmt = $pdo->prepare("
            DELETE FROM {$posts}
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
        ]);
    }

    public static function toggleStatus(
        int $id
    ): void {
        $post = self::find($id);

        if (!$post) {
            return;
        }

        $newStatus =
            $post['status'] === 'published'
                ? 'draft'
                : 'published';

        $publishedAt =
            $newStatus === 'published'
                ? date('Y-m-d H:i:s')
                : null;

        $pdo = Database::connect();

        $posts = Database::table('posts');

        $stmt = $pdo->prepare("
            UPDATE {$posts}
            SET status = :status,
                published_at = :published_at,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':status' => $newStatus,
            ':published_at' => $publishedAt,
            ':id' => $id,
        ]);
    }
}