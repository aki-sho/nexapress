<?php

namespace app\Models;

use app\Core\Database;

class Media
{
    public static function all(): array
    {
        $pdo = Database::connect();

        $media = Database::table('media');
        $users = Database::table('users');

        $stmt = $pdo->query("
            SELECT
                m.*,
                u.name AS author_name
            FROM {$media} AS m
            LEFT JOIN {$users} AS u
                ON m.user_id = u.id
            ORDER BY m.created_at DESC
        ");

        return $stmt->fetchAll();
    }

    /*
    * アイキャッチ選択用の
    * 画像メディア一覧を取得
    */
    public static function images(): array
    {
        $pdo = Database::connect();

        $media =
            Database::table('media');

        $stmt = $pdo->prepare("
            SELECT
                id,
                title,
                description,
                original_name,
                file_name,
                file_path,
                mime_type,
                file_size,
                file_type,
                user_id,
                created_at,
                updated_at
            FROM {$media}
            WHERE file_type = :file_type
            ORDER BY
                created_at DESC,
                id DESC
        ");

        $stmt->execute([
            ':file_type' => 'image',
        ]);

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::connect();

        $media = Database::table('media');

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$media}
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
        ]);

        $mediaItem = $stmt->fetch();

        return $mediaItem ?: null;
    }

    public static function create(
        array $data
    ): void {
        $pdo = Database::connect();

        $media = Database::table('media');

        $stmt = $pdo->prepare("
            INSERT INTO {$media} (
                title,
                description,
                original_name,
                file_name,
                file_path,
                mime_type,
                file_size,
                file_type,
                user_id
            )
            VALUES (
                :title,
                :description,
                :original_name,
                :file_name,
                :file_path,
                :mime_type,
                :file_size,
                :file_type,
                :user_id
            )
        ");

        $stmt->execute([
            ':title' => $data['title'],
            ':description' =>
                $data['description'] ?? '',
            ':original_name' =>
                $data['original_name'],
            ':file_name' =>
                $data['file_name'],
            ':file_path' =>
                $data['file_path'],
            ':mime_type' =>
                $data['mime_type'],
            ':file_size' =>
                $data['file_size'],
            ':file_type' =>
                $data['file_type'],
            ':user_id' =>
                $data['user_id'],
        ]);
    }

    public static function updateMeta(
        int $id,
        array $data
    ): void {
        $pdo = Database::connect();

        $media = Database::table('media');

        $stmt = $pdo->prepare("
            UPDATE {$media}
            SET title = :title,
                description = :description,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':title' => $data['title'],
            ':description' =>
                $data['description'] ?? '',
            ':id' => $id,
        ]);
    }

    /*
    * メディアを削除
    *
    * アイキャッチに使用されている場合は
    * 投稿と編集履歴から参照を解除する
    */
    public static function delete(
        int $id
    ): void {
        $mediaItem = self::find($id);

        if (!$mediaItem) {
            return;
        }

        $pdo = Database::connect();

        $posts =
            Database::table('posts');

        $postRevisions =
            Database::table(
                'post_revisions'
            );

        $media =
            Database::table('media');

        try {
            $pdo->beginTransaction();

            /*
            * 現在の投稿から
            * アイキャッチ参照を解除
            */
            $postStmt = $pdo->prepare("
                UPDATE {$posts}
                SET featured_media_id = NULL,
                    updated_at = NOW()
                WHERE featured_media_id = :media_id
            ");

            $postStmt->execute([
                ':media_id' => $id,
            ]);

            /*
            * 編集履歴からも
            * アイキャッチ参照を解除
            */
            $revisionStmt =
                $pdo->prepare("
                    UPDATE {$postRevisions}
                    SET featured_media_id = NULL
                    WHERE featured_media_id =
                        :media_id
                ");

            $revisionStmt->execute([
                ':media_id' => $id,
            ]);

            /*
            * メディア情報を削除
            */
            $deleteStmt = $pdo->prepare("
                DELETE FROM {$media}
                WHERE id = :id
            ");

            $deleteStmt->execute([
                ':id' => $id,
            ]);

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        /*
        * DB削除成功後に
        * 実ファイルを削除
        */
        $file = BASE_PATH
            . '/public/'
            . ltrim(
                (string)(
                    $mediaItem['file_path']
                    ?? ''
                ),
                '/'
            );

        if (is_file($file)) {
            @unlink($file);
        }
    }
}