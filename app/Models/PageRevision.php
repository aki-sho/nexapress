<?php

namespace app\Models;

use app\Core\Database;

class PageRevision
{
    public static function create(array $page, int $editor): void
    {
        $table = Database::table('page_revisions');
        $stmt = Database::connect()->prepare("INSERT INTO {$table}
            (page_id, editor_user_id, title, slug, content, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$page['id'], $editor, $page['title'], $page['slug'], $page['content'], $page['status']]);
        $ids = Database::connect()->prepare("SELECT id FROM {$table} WHERE page_id = ? ORDER BY id DESC LIMIT 50, 1000000");
        $ids->execute([$page['id']]);
        foreach ($ids->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            Database::connect()->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
        }
    }

    public static function allForPage(int $id): array
    {
        $table = Database::table('page_revisions');
        $users = Database::table('users');
        $stmt = Database::connect()->prepare("SELECT r.*, u.name AS editor_name FROM {$table} r
            LEFT JOIN {$users} u ON r.editor_user_id = u.id WHERE page_id = ? ORDER BY r.id DESC");
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public static function find(int $id, int $pageId): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM ' . Database::table('page_revisions') . ' WHERE id = ? AND page_id = ?');
        $stmt->execute([$id, $pageId]);
        return $stmt->fetch() ?: null;
    }
}
