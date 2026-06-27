<?php

namespace app\Core;

use PDO;
use PDOException;

class Installer
{
    public static function isInstalled(): bool
    {
        return file_exists(BASE_PATH . '/storage/installed.lock');
    }

    public static function install(array $data): bool
    {
        try {
            $pdo = new PDO(
                'mysql:host=' . $data['db_host'] . ';charset=utf8mb4',
                $data['db_user'],
                $data['db_pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]
            );

            $dbName = $data['db_name'];

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS categories (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    slug VARCHAR(255) NOT NULL UNIQUE,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL
                )
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS posts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL UNIQUE,
                    content TEXT NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'draft',
                    user_id INT NOT NULL,
                    category_id INT NULL,
                    published_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS pages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL UNIQUE,
                    content TEXT NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'draft',
                    user_id INT NOT NULL,
                    published_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS media (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    original_name VARCHAR(255) NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    mime_type VARCHAR(100) NOT NULL,
                    file_size BIGINT NOT NULL,
                    file_type VARCHAR(50) NOT NULL,
                    user_id INT NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");

            $passwordHash = password_hash($data['admin_password'], PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password_hash)
                VALUES (:name, :email, :password_hash)
            ");

            $stmt->execute([
                ':name' => $data['admin_name'],
                ':email' => $data['admin_email'],
                ':password_hash' => $passwordHash,
            ]);

            self::createConfig($data);
            self::createLockFile();

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    private static function createConfig(array $data): void
    {
        $content = "<?php\n\nreturn [\n";
        $content .= "    'db_host' => '" . addslashes($data['db_host']) . "',\n";
        $content .= "    'db_name' => '" . addslashes($data['db_name']) . "',\n";
        $content .= "    'db_user' => '" . addslashes($data['db_user']) . "',\n";
        $content .= "    'db_pass' => '" . addslashes($data['db_pass']) . "',\n";
        $content .= "];\n";

        file_put_contents(BASE_PATH . '/config/config.php', $content);
    }

    private static function createLockFile(): void
    {
        file_put_contents(BASE_PATH . '/storage/installed.lock', 'installed');
    }
}