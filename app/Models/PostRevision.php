<?php

namespace app\Models;

use app\Core\Database;
use InvalidArgumentException;
use PDO;

/*
 * 投稿の編集履歴を管理
 */
class PostRevision
{
    /*
     * 1投稿につき保存する履歴数
     */
    private const MAX_REVISIONS_PER_POST = 20;

    /*
     * 現在の投稿内容を履歴として保存
     */
    public static function createFromPost(
        array $post,
        int $editorUserId
    ): int {
        return self::create([
            'post_id' =>
                (int)($post['id'] ?? 0),

            'title' =>
                (string)($post['title'] ?? ''),

            'slug' =>
                (string)($post['slug'] ?? ''),

            'excerpt' =>
                (string)($post['excerpt'] ?? ''),

            'content' =>
                (string)($post['content'] ?? ''),

            'status' =>
                (string)($post['status'] ?? 'draft'),

            'category_id' =>
                !empty($post['category_id'])
                    ? (int)$post['category_id']
                    : null,

            'featured_media_id' =>
                !empty($post['featured_media_id'])
                    ? (int)$post['featured_media_id']
                    : null,

            'editor_user_id' =>
                $editorUserId,
        ]);
    }

    /*
     * 編集履歴を保存
     */
    public static function create(
        array $data
    ): int {
        $postId =
            (int)($data['post_id'] ?? 0);

        $editorUserId =
            (int)($data['editor_user_id'] ?? 0);

        if (
            $postId <= 0 ||
            $editorUserId <= 0
        ) {
            throw new InvalidArgumentException(
                '編集履歴の投稿情報が正しくありません。'
            );
        }

        $pdo = Database::connect();

        $revisions =
            Database::table('post_revisions');

        $stmt = $pdo->prepare("
            INSERT INTO {$revisions} (
                post_id,
                title,
                slug,
                excerpt,
                content,
                status,
                category_id,
                featured_media_id,
                editor_user_id
            )
            VALUES (
                :post_id,
                :title,
                :slug,
                :excerpt,
                :content,
                :status,
                :category_id,
                :featured_media_id,
                :editor_user_id
            )
        ");

        $stmt->execute([
            ':post_id' =>
                $postId,

            ':title' =>
                (string)($data['title'] ?? ''),

            ':slug' =>
                (string)($data['slug'] ?? ''),

            ':excerpt' =>
                (string)($data['excerpt'] ?? ''),

            ':content' =>
                (string)($data['content'] ?? ''),

            ':status' =>
                (string)($data['status'] ?? 'draft'),

            ':category_id' =>
                $data['category_id'] ?? null,

            ':featured_media_id' =>
                $data['featured_media_id'] ?? null,

            ':editor_user_id' =>
                $editorUserId,
        ]);

        $revisionId =
            (int)$pdo->lastInsertId();

        /*
         * 古い履歴を自動整理
         */
        self::prune(
            $postId,
            self::MAX_REVISIONS_PER_POST
        );

        return $revisionId;
    }

    /*
     * 投稿ごとの履歴一覧を取得
     */
    public static function allForPost(
        int $postId,
        int $limit = 20
    ): array {
        $limit = max(
            1,
            min($limit, 100)
        );

        $pdo = Database::connect();

        $stmt = $pdo->prepare(
            self::selectSql() . "
                WHERE r.post_id = :post_id
                ORDER BY
                    r.created_at DESC,
                    r.id DESC
                LIMIT :limit
            "
        );

        $stmt->bindValue(
            ':post_id',
            $postId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /*
     * 投稿に属する特定の履歴を取得
     */
    public static function findForPost(
        int $revisionId,
        int $postId
    ): ?array {
        $pdo = Database::connect();

        $stmt = $pdo->prepare(
            self::selectSql() . "
                WHERE r.id = :revision_id
                  AND r.post_id = :post_id
                LIMIT 1
            "
        );

        $stmt->execute([
            ':revision_id' =>
                $revisionId,

            ':post_id' =>
                $postId,
        ]);

        $revision = $stmt->fetch();

        return $revision ?: null;
    }

    /*
     * 編集履歴の共通取得SQL
     */
    private static function selectSql(): string
    {
        $revisions =
            Database::table('post_revisions');

        $users =
            Database::table('users');

        $categories =
            Database::table('categories');

        $media =
            Database::table('media');

        return "
            SELECT
                r.*,

                u.name AS editor_name,

                c.name AS category_name,
                c.slug AS category_slug,

                m.title AS featured_media_title,
                m.file_path AS featured_media_path

            FROM {$revisions} AS r

            LEFT JOIN {$users} AS u
                ON r.editor_user_id = u.id

            LEFT JOIN {$categories} AS c
                ON r.category_id = c.id

            LEFT JOIN {$media} AS m
                ON r.featured_media_id = m.id
        ";
    }

    /*
     * 保存数を超えた古い履歴を削除
     */
    private static function prune(
        int $postId,
        int $keepCount
    ): void {
        $keepCount = max(
            1,
            $keepCount
        );

        $pdo = Database::connect();

        $revisions =
            Database::table('post_revisions');

        $stmt = $pdo->prepare("
            SELECT id
            FROM {$revisions}
            WHERE post_id = :post_id
            ORDER BY
                created_at DESC,
                id DESC
        ");

        $stmt->execute([
            ':post_id' => $postId,
        ]);

        $revisionIds = $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );

        if (!is_array($revisionIds)) {
            return;
        }

        $deleteIds = array_slice(
            $revisionIds,
            $keepCount
        );

        if ($deleteIds === []) {
            return;
        }

        $placeholders = [];
        $params = [
            ':post_id' => $postId,
        ];

        foreach (
            array_values($deleteIds)
            as $index => $revisionId
        ) {
            $placeholder =
                ':revision_id_' . $index;

            $placeholders[] =
                $placeholder;

            $params[$placeholder] =
                (int)$revisionId;
        }

        $deleteStmt = $pdo->prepare("
            DELETE FROM {$revisions}
            WHERE post_id = :post_id
              AND id IN (
                " . implode(
                    ', ',
                    $placeholders
                ) . "
              )
        ");

        foreach ($params as $name => $value) {
            $deleteStmt->bindValue(
                $name,
                $value,
                PDO::PARAM_INT
            );
        }

        $deleteStmt->execute();
    }
}