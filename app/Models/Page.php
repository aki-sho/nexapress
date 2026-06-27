<?php

namespace app\Models;

use app\Core\Database;

class Page
{
    public static function all(): array
    {
        $pdo = Database::connect();

        $stmt = $pdo->query("
            SELECT pages.*, users.name AS author_name
            FROM pages
            LEFT JOIN users ON pages.user_id = users.id
            ORDER BY pages.created_at DESC
        ");

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = :id LIMIT 1");
        $stmt->execute([
            ':id' => $id,
        ]);

        $page = $stmt->fetch();

        return $page ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT *
            FROM pages
            WHERE slug = :slug
            AND status = 'published'
            LIMIT 1
        ");

        $stmt->execute([
            ':slug' => $slug,
        ]);

        $page = $stmt->fetch();

        return $page ?: null;
    }

    public static function create(array $data): void
    {
        $pdo = Database::connect();

        $publishedAt = $data['status'] === 'published' ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("
            INSERT INTO pages (title, slug, content, status, user_id, published_at)
            VALUES (:title, :slug, :content, :status, :user_id, :published_at)
        ");

        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':content' => $data['content'],
            ':status' => $data['status'],
            ':user_id' => $data['user_id'],
            ':published_at' => $publishedAt,
        ]);
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connect();

        $page = self::find($id);

        $publishedAt = $page['published_at'] ?? null;

        if ($data['status'] === 'published' && empty($publishedAt)) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        if ($data['status'] === 'draft') {
            $publishedAt = null;
        }

        $stmt = $pdo->prepare("
            UPDATE pages
            SET title = :title,
                slug = :slug,
                content = :content,
                status = :status,
                published_at = :published_at,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':content' => $data['content'],
            ':status' => $data['status'],
            ':published_at' => $publishedAt,
            ':id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("DELETE FROM pages WHERE id = :id");
        $stmt->execute([
            ':id' => $id,
        ]);
    }

    public static function toggleStatus(int $id): void
    {
        $page = self::find($id);

        if (!$page) {
            return;
        }

        $newStatus = $page['status'] === 'published' ? 'draft' : 'published';
        $publishedAt = $newStatus === 'published' ? date('Y-m-d H:i:s') : null;

        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            UPDATE pages
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