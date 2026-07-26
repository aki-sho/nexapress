<?php

use app\Core\Database;

/*
 * NexaPress 2.3.0
 *
 * 投稿機能拡張用マイグレーション
 */
return function (\PDO $pdo): void {
    $prefix = Database::tablePrefix();

    /*
     * 実際のテーブル名
     */
    $postsTableName =
        $prefix . 'posts';

    $posts =
        Database::table('posts');

    $users =
        Database::table('users');

    $postRevisions =
        Database::table('post_revisions');

    /*
     * カラムの存在確認
     */
    $columnExists = static function (
        string $tableName,
        string $columnName
    ) use ($pdo): bool {
        $statement = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");

        $statement->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName,
        ]);

        return
            (int)$statement->fetchColumn() > 0;
    };

    /*
     * インデックスの存在確認
     */
    $indexExists = static function (
        string $tableName,
        string $indexName
    ) use ($pdo): bool {
        $statement = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
        ");

        $statement->execute([
            ':table_name' => $tableName,
            ':index_name' => $indexName,
        ]);

        return
            (int)$statement->fetchColumn() > 0;
    };

    /*
     * 投稿抜粋
     */
    if (
        !$columnExists(
            $postsTableName,
            'excerpt'
        )
    ) {
        $pdo->exec("
            ALTER TABLE {$posts}
            ADD COLUMN excerpt
                TEXT NULL
                AFTER content
        ");
    }

    /*
     * アイキャッチ画像
     *
     * mediaテーブルのIDを保存する。
     * メディア削除時の参照解除は
     * Mediaモデル側で行う。
     */
    if (
        !$columnExists(
            $postsTableName,
            'featured_media_id'
        )
    ) {
        $pdo->exec("
            ALTER TABLE {$posts}
            ADD COLUMN featured_media_id
                INT NULL
                AFTER category_id
        ");
    }

    /*
     * ゴミ箱へ移動した日時
     */
    if (
        !$columnExists(
            $postsTableName,
            'deleted_at'
        )
    ) {
        $pdo->exec("
            ALTER TABLE {$posts}
            ADD COLUMN deleted_at
                DATETIME NULL
                AFTER updated_at
        ");
    }

    /*
     * 公開投稿取得用インデックス
     */
    if (
        !$indexExists(
            $postsTableName,
            'idx_posts_status_published'
        )
    ) {
        $pdo->exec("
            ALTER TABLE {$posts}
            ADD INDEX idx_posts_status_published (
                status,
                published_at
            )
        ");
    }

    /*
     * カテゴリ絞り込み用インデックス
     */
    if (
        !$indexExists(
            $postsTableName,
            'idx_posts_category'
        )
    ) {
        $pdo->exec("
            ALTER TABLE {$posts}
            ADD INDEX idx_posts_category (
                category_id
            )
        ");
    }

    /*
     * アイキャッチ画像検索用インデックス
     */
    if (
        !$indexExists(
            $postsTableName,
            'idx_posts_featured_media'
        )
    ) {
        $pdo->exec("
            ALTER TABLE {$posts}
            ADD INDEX idx_posts_featured_media (
                featured_media_id
            )
        ");
    }

    /*
     * ゴミ箱検索用インデックス
     */
    if (
        !$indexExists(
            $postsTableName,
            'idx_posts_deleted'
        )
    ) {
        $pdo->exec("
            ALTER TABLE {$posts}
            ADD INDEX idx_posts_deleted (
                deleted_at
            )
        ");
    }

    /*
     * 投稿編集履歴テーブル
     *
     * 投稿保存前の内容を保持し、
     * 後から過去の状態へ戻せるようにする。
     */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS {$postRevisions} (
            id INT AUTO_INCREMENT PRIMARY KEY,

            post_id INT NOT NULL,

            title VARCHAR(255) NOT NULL,

            slug VARCHAR(255) NOT NULL,

            excerpt TEXT NULL,

            content TEXT NOT NULL,

            status VARCHAR(20) NOT NULL
                DEFAULT 'draft',

            category_id INT NULL,

            featured_media_id INT NULL,

            editor_user_id INT NOT NULL,

            created_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_post_revisions_post_created (
                post_id,
                created_at
            ),

            INDEX idx_post_revisions_editor (
                editor_user_id
            ),

            FOREIGN KEY (post_id)
                REFERENCES {$posts}(id)
                ON DELETE CASCADE,

            FOREIGN KEY (editor_user_id)
                REFERENCES {$users}(id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ");

    /*
     * ALTER TABLEやCREATE TABLEによって
     * 終了したトランザクションを再開する。
     *
     * この後、Migratorが実行済み情報を
     * nexapress_migrationsへ登録する。
     */
    if (!$pdo->inTransaction()) {
        if (!$pdo->beginTransaction()) {
            throw new \RuntimeException(
                '2.3.0マイグレーションの'
                . 'トランザクションを'
                . '再開できませんでした。'
            );
        }
    }
};