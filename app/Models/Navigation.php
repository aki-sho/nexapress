<?php

namespace app\Models;

use app\Core\Database;
use app\Core\Design;
use Throwable;

class Navigation
{
    public const TYPES = [
        'page' => '固定ページ',
        'category' => 'カテゴリ',
        'post' => '投稿記事',
        'custom' => '任意リンク',
    ];

    public static function all(bool $lock = false): array
    {
        return Database::connect()->query('SELECT * FROM ' . Database::table('navigation_items') .
            ' ORDER BY position, id' . ($lock ? ' FOR UPDATE' : ''))->fetchAll();
    }

    public static function safeUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\\\\]/', $url)) {
            return false;
        }
        return (str_starts_with($url, '/') && !str_starts_with($url, '//')) ||
            (filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true));
    }

    public static function links(): array
    {
        return Design::menuLinks('header');
    }

    public static function resolve(array $items): array
    {
        $links = [];
        foreach ($items as $item) {
            $link = self::resolveLink($item);
            if ($link !== null) {
                $links[] = $link;
            }
        }
        return $links;
    }

    public static function resolveLink(array $item): ?array
    {
        $label = $item['label'] ?? '';
        $id = (int)($item['reference_id'] ?? 0);
        switch ($item['type']) {
            case 'page':
                $page = Page::find($id);
                if ($page && $page['status'] === 'published' && $page['deleted_at'] === null) {
                    return ['label' => $label === '' ? $page['title'] : $label, 'url' => page_url($page)];
                }
                break;
            case 'category':
                $category = Category::find($id);
                if ($category) {
                    return ['label' => $label === '' ? $category['name'] : $label, 'url' => category_url($category)];
                }
                break;
            case 'post':
                $post = Post::find($id);
                if ($post && $post['status'] === 'published' && $post['deleted_at'] === null) {
                    return ['label' => $label === '' ? $post['title'] : $label, 'url' => post_url($post)];
                }
                break;
            case 'custom':
                $url = $item['url'] ?? '';
                if ($label !== '' && self::safeUrl($url)) {
                    return ['label' => $label, 'url' => str_starts_with($url, '/') ? url(ltrim($url, '/')) : $url];
                }
                break;
        }
        return null;
    }

    /** Use the caller's transaction so both menus and the layout publish together. */
    public static function replace(array $items, array $original): void
    {
        $pdo = Database::connect();
        $table = Database::table('navigation_items');
        $remaining = array_fill_keys(array_column($original, 'id'), true);
        $insert = $pdo->prepare("INSERT INTO {$table} (label, type, reference_id, url, position) VALUES (?, ?, ?, ?, ?)");
        $update = $pdo->prepare("UPDATE {$table} SET label = ?, type = ?, reference_id = ?, url = ?, position = ? WHERE id = ?");
        $delete = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
        foreach ($items as $position => $item) {
            $values = [$item['label'], $item['type'], $item['reference_id'], $item['url'], $position];
            $id = $item['id'];
            if ($id !== null && isset($remaining[$id])) {
                $update->execute([...$values, $id]);
                unset($remaining[$id]);
            } else {
                $insert->execute($values);
            }
        }
        foreach (array_keys($remaining) as $id) {
            $delete->execute([$id]);
        }
    }

    public static function save(array $data, ?int $id): void
    {
        $table = Database::table('navigation_items');
        $values = [$data['label'], $data['type'], $data['reference_id'], $data['url']];
        if ($id !== null) {
            $stmt = Database::connect()->prepare("UPDATE {$table} SET label = ?, type = ?, reference_id = ?, url = ? WHERE id = ?");
            $values[] = $id;
        } else {
            $stmt = Database::connect()->prepare("INSERT INTO {$table} (label, type, reference_id, url, position)
                SELECT ?, ?, ?, ?, COALESCE(MAX(position), 0) + 1 FROM {$table}");
        }
        $stmt->execute($values);
        do_action('navigation_saved');
    }

    public static function delete(int $id): void
    {
        Database::connect()->prepare('DELETE FROM ' . Database::table('navigation_items') . ' WHERE id = ?')->execute([$id]);
        do_action('navigation_saved');
    }

    public static function move(int $id, int $direction): void
    {
        $pdo = Database::connect();
        $table = Database::table('navigation_items');
        try {
            $pdo->beginTransaction();
            $ids = $pdo->query("SELECT id FROM {$table} ORDER BY position, id FOR UPDATE")->fetchAll(\PDO::FETCH_COLUMN);
            $ids = array_map('intval', $ids);
            $index = array_search($id, $ids, true);
            if ($index !== false && isset($ids[$index + $direction])) {
                [$ids[$index], $ids[$index + $direction]] = [$ids[$index + $direction], $ids[$index]];
                $stmt = $pdo->prepare("UPDATE {$table} SET position = ? WHERE id = ?");
                foreach ($ids as $position => $itemId) {
                    $stmt->execute([$position, $itemId]);
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        do_action('navigation_saved');
    }
}
