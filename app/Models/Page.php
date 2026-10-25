<?php

namespace app\Models;

use app\Core\Database;

class Page
{
    public static function all(bool $trash = false): array
    {
        $pages = Database::table('pages');
        $users = Database::table('users');
        $condition = $trash ? 'IS NOT NULL' : 'IS NULL';
        return Database::connect()->query("SELECT p.*, u.name AS author_name FROM {$pages} p
            LEFT JOIN {$users} u ON p.user_id = u.id
            WHERE p.deleted_at {$condition} ORDER BY p.created_at DESC, p.id DESC")->fetchAll();
    }

    public static function published(): array
    {
        return array_values(array_filter(self::all(), static fn(array $page): bool => $page['status'] === 'published'));
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM ' . Database::table('pages') . ' WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM ' . Database::table('pages') .
            " WHERE slug = ? AND status = 'published' AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function slugExists(string $slug, ?int $except = null): bool
    {
        $stmt = Database::connect()->prepare('SELECT COUNT(*) FROM ' . Database::table('pages') . ' WHERE slug = ? AND id <> ?');
        $stmt->execute([$slug, $except ?? 0]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connect()->prepare('INSERT INTO ' . Database::table('pages') .
            ' (title, slug, content, status, user_id, published_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data['title'], $data['slug'], $data['content'], $data['status'], $data['user_id'],
            $data['status'] === 'published' ? date('Y-m-d H:i:s') : null]);
        $id = (int)Database::connect()->lastInsertId();
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $page = self::find($id);
        if (!$page || $page['deleted_at'] !== null) {
            return;
        }
        $published = $data['status'] === 'published' ? ($page['published_at'] ?: date('Y-m-d H:i:s')) : null;
        $stmt = Database::connect()->prepare('UPDATE ' . Database::table('pages') .
            ' SET title = ?, slug = ?, content = ?, status = ?, published_at = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$data['title'], $data['slug'], $data['content'], $data['status'], $published, $id]);
    }

    public static function delete(int $id): void
    {
        self::execute('UPDATE %s SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND deleted_at IS NULL', $id);
        do_action('page_trashed', $id);
    }

    public static function restore(int $id): void
    {
        self::execute("UPDATE %s SET deleted_at = NULL, status = 'draft', published_at = NULL,
            updated_at = NOW() WHERE id = ? AND deleted_at IS NOT NULL", $id);
        do_action('page_restored', $id);
    }

    public static function destroy(int $id): void
    {
        self::execute('DELETE FROM %s WHERE id = ? AND deleted_at IS NOT NULL', $id);
        do_action('page_deleted', $id);
    }

    public static function toggleStatus(int $id): void
    {
        $page = self::find($id);
        if ($page && $page['deleted_at'] === null) {
            $page['status'] = $page['status'] === 'published' ? 'draft' : 'published';
            self::update($id, $page);
        }
    }

    private static function execute(string $sql, int $id): void
    {
        $stmt = Database::connect()->prepare(sprintf($sql, Database::table('pages')));
        $stmt->execute([$id]);
    }
}
