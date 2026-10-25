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
}
