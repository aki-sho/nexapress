<?php

namespace app\Models;

use app\Core\Database;

class ExtensionSetting
{
    public static function isEnabled(string $extensionKey): bool
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT is_enabled
            FROM extension_settings
            WHERE extension_key = :extension_key
            LIMIT 1
        ");

        $stmt->execute([
            ':extension_key' => $extensionKey,
        ]);

        $setting = $stmt->fetch();

        return $setting ? (bool)$setting['is_enabled'] : false;
    }

    public static function enable(string $extensionKey): void
    {
        self::save($extensionKey, true);
    }

    public static function disable(string $extensionKey): void
    {
        self::save($extensionKey, false);
    }

    public static function save(string $extensionKey, bool $isEnabled): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            INSERT INTO extension_settings (
                extension_key,
                is_enabled,
                created_at,
                updated_at
            )
            VALUES (
                :extension_key,
                :is_enabled,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                is_enabled = :is_enabled_update,
                updated_at = NOW()
        ");

        $stmt->execute([
            ':extension_key' => $extensionKey,
            ':is_enabled' => $isEnabled ? 1 : 0,
            ':is_enabled_update' => $isEnabled ? 1 : 0,
        ]);
    }

    public static function delete(string $extensionKey): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            DELETE FROM extension_settings
            WHERE extension_key = :extension_key
        ");

        $stmt->execute([
            ':extension_key' => $extensionKey,
        ]);
    }
}