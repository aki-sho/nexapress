<?php

namespace app\Models;

use app\Core\Database;
use PDO;

class Post
{
    /*
     * 管理画面：
     * 通常投稿を全件取得
     *
     * 既存処理との互換用
     */
    public static function all(): array
    {
        $pdo = Database::connect();

        $stmt = $pdo->query(
            self::selectSql() . "
                WHERE p.deleted_at IS NULL
                ORDER BY
                    p.created_at DESC,
                    p.id DESC
            "
        );

        return $stmt->fetchAll();
    }

    /*
     * 公開投稿を全件取得
     *
     * 既存処理との互換用
     */
    public static function published(): array
    {
        $pdo = Database::connect();

        $stmt = $pdo->query(
            self::selectSql() . "
                WHERE p.status = 'published'
                  AND p.deleted_at IS NULL
                ORDER BY
                    p.published_at DESC,
                    p.created_at DESC
            "
        );

        return $stmt->fetchAll();
    }

    /*
     * 公開投稿をページ単位で取得
     */
    public static function publishedPage(
        int $page,
        int $perPage
    ): array {
        $page = max(1, $page);

        $perPage =
            self::normalizePerPage(
                $perPage
            );

        $offset =
            ($page - 1) * $perPage;

        $pdo = Database::connect();

        $stmt = $pdo->prepare(
            self::selectSql() . "
                WHERE p.status = 'published'
                  AND p.deleted_at IS NULL
                ORDER BY
                    p.published_at DESC,
                    p.created_at DESC
                LIMIT :limit
                OFFSET :offset
            "
        );

        $stmt->bindValue(
            ':limit',
            $perPage,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /*
     * 公開投稿の総件数
     */
    public static function countPublished(): int
    {
        $pdo = Database::connect();

        $posts =
            Database::table('posts');

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM {$posts}
            WHERE status = 'published'
              AND deleted_at IS NULL
        ");

        return (int)$stmt->fetchColumn();
    }

    /*
     * 管理画面：
     * 検索・絞り込み・ページ分割
     */
    public static function adminPage(
        array $filters,
        int $page,
        int $perPage
    ): array {
        $page = max(1, $page);

        $perPage =
            self::normalizePerPage(
                $perPage
            );

        $offset =
            ($page - 1) * $perPage;

        $params = [];

        $where =
            self::buildAdminWhere(
                $filters,
                $params
            );

        /*
         * 通常一覧は作成日、
         * ゴミ箱は削除日で並べる
         */
        $orderColumn =
            !empty($filters['trash'])
                ? 'p.deleted_at'
                : 'p.created_at';

        $pdo = Database::connect();

        $stmt = $pdo->prepare(
            self::selectSql() . "
                {$where}
                ORDER BY
                    {$orderColumn} DESC,
                    p.id DESC
                LIMIT :limit
                OFFSET :offset
            "
        );

        self::bindValues(
            $stmt,
            $params
        );

        $stmt->bindValue(
            ':limit',
            $perPage,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /*
     * 管理画面：
     * 検索条件に一致する総件数
     */
    public static function countAdmin(
        array $filters
    ): int {
        $pdo = Database::connect();

        $posts =
            Database::table('posts');

        $params = [];

        $where =
            self::buildAdminWhere(
                $filters,
                $params
            );

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM {$posts} AS p
            {$where}
        ");

        self::bindValues(
            $stmt,
            $params
        );

        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /*
     * IDで投稿を取得
     *
     * 権限判定・復元処理で使うため、
     * ゴミ箱内の投稿も取得する
     */
    public static function find(
        int $id
    ): ?array {
        $pdo = Database::connect();

        $stmt = $pdo->prepare(
            self::selectSql() . "
                WHERE p.id = :id
                LIMIT 1
            "
        );

        $stmt->execute([
            ':id' => $id,
        ]);

        $post = $stmt->fetch();

        return $post ?: null;
    }

    /*
     * 公開中の投稿を
     * スラッグから取得
     */
    public static function findBySlug(
        string $slug
    ): ?array {
        $pdo = Database::connect();

        $stmt = $pdo->prepare(
            self::selectSql() . "
                WHERE p.slug = :slug
                  AND p.status = 'published'
                  AND p.deleted_at IS NULL
                LIMIT 1
            "
        );

        $stmt->execute([
            ':slug' => $slug,
        ]);

        $post = $stmt->fetch();

        return $post ?: null;
    }

    /*
     * 下書きプレビュー用
     *
     * 公開状態は問わないが、
     * ゴミ箱内の投稿は除外する
     */
    public static function findPreview(
        int $id
    ): ?array {
        $post = self::find($id);

        if (
            !$post ||
            !empty($post['deleted_at'])
        ) {
            return null;
        }

        return $post;
    }

    /*
     * スラッグ重複確認
     *
     * DBのUNIQUE制約と同じく、
     * ゴミ箱内の投稿も確認対象にする
     */
    public static function slugExists(
        string $slug,
        ?int $excludeId = null
    ): bool {
        $pdo = Database::connect();

        $posts =
            Database::table('posts');

        $sql = "
            SELECT COUNT(*)
            FROM {$posts}
            WHERE slug = :slug
        ";

        $params = [
            ':slug' => $slug,
        ];

        /*
         * 投稿更新時は
         * 現在の投稿自身を除外
         */
        if ($excludeId !== null) {
            $sql .= "
                AND id <> :exclude_id
            ";

            $params[':exclude_id'] =
                $excludeId;
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute($params);

        return
            (int)$stmt->fetchColumn() > 0;
    }

    /*
     * 投稿を新規作成
     *
     * 作成した投稿IDを返す
     */
    public static function create(
        array $data
    ): int {
        $pdo = Database::connect();

        $posts =
            Database::table('posts');

        $publishedAt =
            $data['status'] === 'published'
                ? date('Y-m-d H:i:s')
                : null;

        $stmt = $pdo->prepare("
            INSERT INTO {$posts} (
                title,
                slug,
                excerpt,
                content,
                status,
                user_id,
                category_id,
                featured_media_id,
                published_at
            )
            VALUES (
                :title,
                :slug,
                :excerpt,
                :content,
                :status,
                :user_id,
                :category_id,
                :featured_media_id,
                :published_at
            )
        ");

        $stmt->execute([
            ':title' =>
                $data['title'],

            ':slug' =>
                $data['slug'],

            ':excerpt' =>
                $data['excerpt'] ?? '',

            ':content' =>
                $data['content'],

            ':status' =>
                $data['status'],

            ':user_id' =>
                $data['user_id'],

            ':category_id' =>
                $data['category_id']
                ?? null,

            ':featured_media_id' =>
                $data['featured_media_id']
                ?? null,

            ':published_at' =>
                $publishedAt,
        ]);

        return
            (int)$pdo->lastInsertId();
    }

    /*
     * 投稿更新
     */
    public static function update(
        int $id,
        array $data
    ): void {
        $post = self::find($id);

        if (
            !$post ||
            !empty($post['deleted_at'])
        ) {
            return;
        }

        $publishedAt =
            $post['published_at']
            ?? null;

        /*
         * 初回公開時だけ
         * 公開日時を設定
         */
        if (
            $data['status'] === 'published' &&
            empty($publishedAt)
        ) {
            $publishedAt =
                date('Y-m-d H:i:s');
        } elseif (
            $data['status'] === 'draft'
        ) {
            $publishedAt = null;
        }

        $pdo = Database::connect();

        $posts =
            Database::table('posts');

        $stmt = $pdo->prepare("
            UPDATE {$posts}
            SET title = :title,
                slug = :slug,
                excerpt = :excerpt,
                content = :content,
                status = :status,
                category_id = :category_id,
                featured_media_id =
                    :featured_media_id,
                published_at =
                    :published_at,
                updated_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':title' =>
                $data['title'],

            ':slug' =>
                $data['slug'],

            ':excerpt' =>
                $data['excerpt'] ?? '',

            ':content' =>
                $data['content'],

            ':status' =>
                $data['status'],

            ':category_id' =>
                $data['category_id']
                ?? null,

            ':featured_media_id' =>
                $data['featured_media_id']
                ?? null,

            ':published_at' =>
                $publishedAt,

            ':id' => $id,
        ]);
    }

    /*
     * 投稿をゴミ箱へ移動
     *
     * 従来の完全削除処理から変更
     */
    public static function delete(
        int $id
    ): void {
        $pdo = Database::connect();

        $posts =
            Database::table('posts');

        $stmt = $pdo->prepare("
            UPDATE {$posts}
            SET deleted_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':id' => $id,
        ]);
    }

    /*
     * ゴミ箱から復元
     */
    public static function restore(
        int $id
    ): void {
        $pdo = Database::connect();

        $posts =
            Database::table('posts');

        $stmt = $pdo->prepare("
            UPDATE {$posts}
            SET deleted_at = NULL,
                updated_at = NOW()
            WHERE id = :id
              AND deleted_at IS NOT NULL
        ");

        $stmt->execute([
            ':id' => $id,
        ]);
    }

    /*
     * 投稿を完全削除
     *
     * ゴミ箱内の投稿だけ削除可能
     */
    public static function destroy(
        int $id
    ): void {
        $pdo = Database::connect();

        $posts =
            Database::table('posts');

        $stmt = $pdo->prepare("
            DELETE FROM {$posts}
            WHERE id = :id
              AND deleted_at IS NOT NULL
        ");

        $stmt->execute([
            ':id' => $id,
        ]);
    }

    /*
     * 公開・下書きを切り替え
     */
    public static function toggleStatus(
        int $id
    ): void {
        $post = self::find($id);

        if (
            !$post ||
            !empty($post['deleted_at'])
        ) {
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

        $posts =
            Database::table('posts');

        $stmt = $pdo->prepare("
            UPDATE {$posts}
            SET status = :status,
                published_at =
                    :published_at,
                updated_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':status' =>
                $newStatus,

            ':published_at' =>
                $publishedAt,

            ':id' => $id,
        ]);
    }

    /*
     * 投稿・投稿者・カテゴリ・
     * アイキャッチ画像を共通取得
     */
    private static function selectSql(): string
    {
        $posts =
            Database::table('posts');

        $users =
            Database::table('users');

        $categories =
            Database::table('categories');

        $media =
            Database::table('media');

        return "
            SELECT
                p.*,

                u.name AS author_name,

                c.name AS category_name,
                c.slug AS category_slug,

                m.file_path
                    AS featured_media_path,

                m.title
                    AS featured_media_title,

                m.description
                    AS featured_media_description

            FROM {$posts} AS p

            LEFT JOIN {$users} AS u
                ON p.user_id = u.id

            LEFT JOIN {$categories} AS c
                ON p.category_id = c.id

            LEFT JOIN {$media} AS m
                ON p.featured_media_id = m.id
        ";
    }

    /*
     * 管理画面の検索条件を作成
     */
    private static function buildAdminWhere(
        array $filters,
        array &$params
    ): string {
        $conditions = [
            !empty($filters['trash'])
                ? 'p.deleted_at IS NOT NULL'
                : 'p.deleted_at IS NULL',
        ];

        /*
         * キーワード検索
         */
        $keyword = trim(
            (string)(
                $filters['keyword']
                ?? ''
            )
        );

        if ($keyword !== '') {
            $conditions[] = "
                (
                    p.title
                        LIKE :keyword_title
                        ESCAPE '='

                    OR p.slug
                        LIKE :keyword_slug
                        ESCAPE '='

                    OR p.excerpt
                        LIKE :keyword_excerpt
                        ESCAPE '='

                    OR p.content
                        LIKE :keyword_content
                        ESCAPE '='
                )
            ";

            $keywordValue =
                '%'
                . self::escapeLike($keyword)
                . '%';

            /*
             * PDOの設定に左右されないよう、
             * 同じ名前のプレースホルダーを
             * 複数回使用しない
             */
            $params[':keyword_title'] =
                $keywordValue;

            $params[':keyword_slug'] =
                $keywordValue;

            $params[':keyword_excerpt'] =
                $keywordValue;

            $params[':keyword_content'] =
                $keywordValue;
        }

        /*
         * 公開状態
         */
        $status = strtolower(
            trim(
                (string)(
                    $filters['status']
                    ?? ''
                )
            )
        );

        if (
            in_array(
                $status,
                [
                    'draft',
                    'published',
                ],
                true
            )
        ) {
            $conditions[] =
                'p.status = :status';

            $params[':status'] =
                $status;
        }

        /*
         * カテゴリ
         */
        $categoryId = (int)(
            $filters['category_id']
            ?? 0
        );

        if ($categoryId > 0) {
            $conditions[] =
                'p.category_id = :category_id';

            $params[':category_id'] =
                $categoryId;
        }

        /*
         * 投稿者
         */
        $authorId = (int)(
            $filters['author_id']
            ?? 0
        );

        if ($authorId > 0) {
            $conditions[] =
                'p.user_id = :author_id';

            $params[':author_id'] =
                $authorId;
        }

        return
            'WHERE '
            . implode(
                ' AND ',
                $conditions
            );
    }

    /*
     * 検索条件をSQLへ割り当て
     */
    private static function bindValues(
        \PDOStatement $stmt,
        array $params
    ): void {
        foreach (
            $params
            as $name => $value
        ) {
            $stmt->bindValue(
                $name,
                $value,
                is_int($value)
                    ? PDO::PARAM_INT
                    : PDO::PARAM_STR
            );
        }
    }

    /*
     * 1ページの件数を制限
     */
    private static function normalizePerPage(
        int $perPage
    ): int {
        return max(
            1,
            min($perPage, 100)
        );
    }

    /*
     * LIKE検索用の特殊文字を処理
     *
     * 「=」をエスケープ文字として使用
     */
    private static function escapeLike(
        string $value
    ): string {
        return str_replace(
            [
                '=',
                '%',
                '_',
            ],
            [
                '==',
                '=%',
                '=_',
            ],
            $value
        );
    }
}