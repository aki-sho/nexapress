<?php

namespace app\Models;

use app\Core\ArticleContent;
use app\Core\Database;
use app\Core\Paginator;
use InvalidArgumentException;

final class CategoryPage
{
    public const POST_MODES = ['list' => '一覧', 'cards' => 'カード', 'hidden' => '非表示'];
    public const DEFAULTS = ['content' => '', 'posts_mode' => 'list', 'show_title' => '1'];

    public static function find(int $categoryId): ?array
    {
        $table = Database::table('category_pages');
        $stmt = Database::connect()->prepare("SELECT content, posts_mode, show_title FROM {$table} WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        return $stmt->fetch() ?: null;
    }

    public static function fromInput(array $input): array
    {
        $mode = $input['posts_mode'] ?? null;
        $showTitle = $input['show_title'] ?? null;
        if (!is_string($mode) || !isset(self::POST_MODES[$mode]) || !in_array($showTitle, ['0', '1'], true)) {
            throw new InvalidArgumentException('投稿一覧とカテゴリ名の表示設定を確認してください。');
        }

        return [
            'content' => ArticleContent::sanitize($input['content'] ?? ''),
            'posts_mode' => $mode,
            'show_title' => $showTitle,
        ];
    }

    public static function save(int $categoryId, array $page): void
    {
        $table = Database::table('category_pages');
        $stmt = Database::connect()->prepare("INSERT INTO {$table} (category_id, content, posts_mode, show_title)
            VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE
                content = VALUES(content), posts_mode = VALUES(posts_mode), show_title = VALUES(show_title)");
        $stmt->execute([$categoryId, $page['content'], $page['posts_mode'], $page['show_title']]);
    }

    public static function archiveData(array $category, array $page, int $requestedPage = 1): array
    {
        $posts = [];
        $pagination = [];
        if ($page['posts_mode'] !== 'hidden') {
            $id = (int)$category['id'];
            $pagination = Paginator::make(Post::countPublicArchive($id, ''), $requestedPage, 10);
            $posts = Post::publicArchive($id, '', $pagination['per_page'], $pagination['offset']);
        }

        return [
            'categoryPage' => $page,
            'archiveTitle' => $category['name'],
            'archivePath' => nx_public_archive_path('category', $category['slug']),
            'posts' => $posts,
            'pagination' => $pagination,
        ];
    }
}
