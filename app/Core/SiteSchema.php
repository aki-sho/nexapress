<?php

namespace app\Core;

use PDO;

class SiteSchema
{
    /** Shared by fresh installations and the 2.4.0 migration. */
    public static function ensure(PDO $pdo): void
    {
        $pages = Database::table('pages');
        $users = Database::table('users');
        $revisions = Database::table('page_revisions');
        $menus = Database::table('navigation_items');
        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $check->execute([Database::tablePrefix() . 'pages', 'deleted_at']);
        if (!(int)$check->fetchColumn()) {
            $pdo->exec("ALTER TABLE {$pages} ADD COLUMN deleted_at DATETIME NULL,
                ADD INDEX idx_pages_deleted (deleted_at, status)");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$revisions} (
            id INT AUTO_INCREMENT PRIMARY KEY, page_id INT NOT NULL, editor_user_id INT NULL,
            title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL,
            content MEDIUMTEXT NOT NULL, status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_page_revisions (page_id, id),
            FOREIGN KEY (page_id) REFERENCES {$pages}(id) ON DELETE CASCADE,
            FOREIGN KEY (editor_user_id) REFERENCES {$users}(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$menus} (
            id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(255) NOT NULL,
            type VARCHAR(20) NOT NULL, reference_id INT NULL,
            url VARCHAR(2048) NOT NULL DEFAULT '', position INT NOT NULL DEFAULT 0,
            INDEX idx_navigation_position (position, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function ensureDesign(PDO $pdo): void
    {
        $table = Database::table('site_design');
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (
            id TINYINT UNSIGNED PRIMARY KEY, settings MEDIUMTEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO {$table} (id, settings) VALUES (1, '{}')");
    }

    public static function ensurePageLayouts(PDO $pdo): void
    {
        $table = Database::table('page_layouts');
        $pages = Database::table('pages');
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (
            page_id INT PRIMARY KEY, settings MEDIUMTEXT NOT NULL,
            FOREIGN KEY (page_id) REFERENCES {$pages}(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function ensureMediaFolders(PDO $pdo): void
    {
        $folders = Database::table('media_folders');
        $media = Database::table('media');
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$folders} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $check->execute([Database::tablePrefix() . 'media', 'folder_id']);
        if (!(int)$check->fetchColumn()) {
            $pdo->exec("ALTER TABLE {$media}
                ADD COLUMN folder_id INT NULL,
                ADD INDEX idx_media_folder (folder_id),
                ADD FOREIGN KEY (folder_id) REFERENCES {$folders}(id) ON DELETE SET NULL");
        }
    }
}
