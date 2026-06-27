<?php

namespace app\Models;

use app\Core\Database;

class Media
{
    public static function all(): array
    {
        $pdo = Database::connect();

        $stmt = $pdo->query("
            SELECT media.*, users.name AS author_name
            FROM media
            LEFT JOIN users ON media.user_id = users.id
            ORDER BY media.created_at DESC
        ");

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT * FROM media WHERE id = :id LIMIT 1");
        $stmt->execute([
            ':id' => $id,
        ]);

        $media = $stmt->fetch();

        return $media ?: null;
    }

    public static function create(array $data): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            INSERT INTO media (
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
            ':description' => $data['description'] ?? '',
            ':original_name' => $data['original_name'],
            ':file_name' => $data['file_name'],
            ':file_path' => $data['file_path'],
            ':mime_type' => $data['mime_type'],
            ':file_size' => $data['file_size'],
            ':file_type' => $data['file_type'],
            ':user_id' => $data['user_id'],
        ]);
    }

    public static function updateMeta(int $id, array $data): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            UPDATE media
            SET title = :title,
                description = :description,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'] ?? '',
            ':id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $media = self::find($id);

        if (!$media) {
            return;
        }

        $file = BASE_PATH . '/public/' . ltrim($media['file_path'], '/');

        if (file_exists($file)) {
            unlink($file);
        }

        $pdo = Database::connect();

        $stmt = $pdo->prepare("DELETE FROM media WHERE id = :id");
        $stmt->execute([
            ':id' => $id,
        ]);
    }
}