<?php

namespace app\Models;

use app\Core\Database;

class Media
{
    public const EDITABLE_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    public const MAX_FOLDER_NAME_LENGTH = 100;
    public const RESERVED_FOLDER_NAMES = ['すべて', '未分類'];

    public static function all(?int $folderId = null): array
    {
        $pdo = Database::connect();

        $media = Database::table('media');
        $users = Database::table('users');

        // null = all media, 0 = uncategorized.
        $where = match ($folderId) {
            null => '',
            0 => 'WHERE m.folder_id IS NULL',
            default => 'WHERE m.folder_id = ?',
        };
        $stmt = $pdo->prepare("
            SELECT
                m.*,
                u.name AS author_name
            FROM {$media} AS m
            LEFT JOIN {$users} AS u
                ON m.user_id = u.id
            {$where}
            ORDER BY m.created_at DESC, m.id DESC
        ");
        $stmt->execute($folderId > 0 ? [$folderId] : []);

        return $stmt->fetchAll();
    }

    public static function folders(): array
    {
        $folders = Database::table('media_folders');
        return Database::connect()->query("SELECT id, name FROM {$folders} ORDER BY name, id")->fetchAll();
    }

    public static function createFolder(string $name, bool $reuseExisting = false): int
    {
        $pdo = Database::connect();
        $folders = Database::table('media_folders');
        $sql = "INSERT INTO {$folders} (name) VALUES (?)";
        if ($reuseExisting) {
            $sql .= ' ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name]);
        return (int)$pdo->lastInsertId();
    }

    public static function deleteFolder(int $id): bool
    {
        return self::deleteFolders([$id]) > 0;
    }

    /** Deletes folders. The foreign key clears folder_id on their media. */
    public static function deleteFolders(array $ids): int
    {
        $pdo = Database::connect();
        $folders = Database::table('media_folders');
        $deleted = 0;
        $pdo->beginTransaction();
        try {
            foreach (array_chunk($ids, 500) as $batch) {
                $placeholders = implode(', ', array_fill(0, count($batch), '?'));
                $stmt = $pdo->prepare("DELETE FROM {$folders} WHERE id IN ({$placeholders})");
                $stmt->execute($batch);
                $deleted += $stmt->rowCount();
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        return $deleted;
    }

    /** Returns null if the destination folder was deleted. */
    public static function moveToFolder(array $ids, ?int $folderId): ?int
    {
        $pdo = Database::connect();
        $media = Database::table('media');
        $folders = Database::table('media_folders');
        $moved = 0;

        $pdo->beginTransaction();
        try {
            if ($folderId !== null) {
                $folder = $pdo->prepare("SELECT id FROM {$folders} WHERE id = ? FOR UPDATE");
                $folder->execute([$folderId]);
                if (!$folder->fetchColumn()) {
                    $pdo->rollBack();
                    return null;
                }
            }

            foreach (array_chunk($ids, 500) as $batch) {
                $placeholders = implode(', ', array_fill(0, count($batch), '?'));
                $stmt = $pdo->prepare("UPDATE {$media} SET folder_id = ?
                    WHERE id IN ({$placeholders}) AND NOT (folder_id <=> ?)");
                $stmt->execute([$folderId, ...$batch, $folderId]);
                $moved += $stmt->rowCount();
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return $moved;
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

    public static function find(int $id, bool $forUpdate = false): ?array
    {
        $pdo = Database::connect();

        $media = Database::table('media');

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$media}
            WHERE id = :id
            LIMIT 1
        " . ($forUpdate ? ' FOR UPDATE' : ''));

        $stmt->execute([
            ':id' => $id,
        ]);

        $mediaItem = $stmt->fetch();

        return $mediaItem ?: null;
    }

    public static function updateFileSize(int $id, int $bytes): void
    {
        $media = Database::table('media');
        $statement = Database::connect()->prepare("UPDATE {$media} SET file_size = ? WHERE id = ?");
        $statement->execute([$bytes, $id]);
    }

    public static function create(
        array $data
    ): int {
        $pdo = Database::connect();

        $media = Database::table('media');
        $folders = Database::table('media_folders');

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
                user_id,
                folder_id
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
                :user_id,
                (SELECT id FROM {$folders} WHERE id = :folder_id)
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
            ':folder_id' =>
                $data['folder_id'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
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
    public static function delete(int $id): void
    {
        self::deleteMany([$id]);
    }

    /** @return array{deleted: int, remainingFiles: int} */
    public static function deleteMany(array $ids): array
    {
        $pdo = Database::connect();
        $posts = Database::table('posts');
        $postRevisions = Database::table('post_revisions');
        $media = Database::table('media');
        $files = [];

        $pdo->beginTransaction();
        try {
            foreach (array_chunk($ids, 500) as $batch) {
                $placeholders = implode(', ', array_fill(0, count($batch), '?'));
                $select = $pdo->prepare("SELECT id, file_path FROM {$media} WHERE id IN ({$placeholders}) FOR UPDATE");
                $select->execute($batch);
                foreach ($select->fetchAll() as $item) {
                    $files[$item['id']] = $item['file_path'];
                }

                $pdo->prepare("UPDATE {$posts} SET featured_media_id = NULL, updated_at = NOW()
                    WHERE featured_media_id IN ({$placeholders})")->execute($batch);
                $pdo->prepare("UPDATE {$postRevisions} SET featured_media_id = NULL
                    WHERE featured_media_id IN ({$placeholders})")->execute($batch);
                $pdo->prepare("DELETE FROM {$media} WHERE id IN ({$placeholders})")->execute($batch);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        $root = realpath(BASE_PATH . '/public/uploads');
        $remainingFiles = 0;
        foreach ($files as $path) {
            $file = BASE_PATH . '/public/' . ltrim($path, '/');
            if (!is_file($file)) {
                continue;
            }
            $resolved = realpath($file);
            if ($root === false || $resolved === false ||
                !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR) || !@unlink($file)) {
                $remainingFiles++;
            }
        }

        return ['deleted' => count($files), 'remainingFiles' => $remainingFiles];
    }
}
