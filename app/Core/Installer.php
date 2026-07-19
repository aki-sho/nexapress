<?php

namespace app\Core;

use PDO;
use RuntimeException;
use Throwable;

class Installer
{
    /*
     * インストール済みか確認
     */
    public static function isInstalled(): bool
    {
        return file_exists(
            BASE_PATH . '/storage/installed.lock'
        );
    }

    /*
     * DB設定ファイルが作成済みか確認
     */
    public static function isDatabaseConfigured(): bool
    {
        $config = self::databaseConfig();

        return
            !empty($config['db_host']) &&
            !empty($config['db_name']) &&
            !empty($config['db_user']) &&
            !empty($config['table_prefix']);
    }

    /*
     * 保存済みDB設定を取得
     */
    public static function databaseConfig(): array
    {
        $configPath = BASE_PATH
            . '/config/config.php';

        if (!file_exists($configPath)) {
            return [];
        }

        $config = require $configPath;

        if (!is_array($config)) {
            return [];
        }

        return $config;
    }

    /*
     * DB接続確認と設定ファイル作成
     */
    public static function configureDatabase(
        array $data
    ): bool {
        try {
            self::validateDatabaseData($data);

            $pdo = self::connectDatabase($data);

            $pdo->query('SELECT 1');

            self::writePhpConfig(
                BASE_PATH . '/config/config.php',
                [
                    'db_host' => $data['db_host'],
                    'db_name' => $data['db_name'],
                    'db_user' => $data['db_user'],
                    'db_pass' => $data['db_pass'],
                    'table_prefix' =>
                        $data['table_prefix'],
                ]
            );

            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    /*
     * テーブル、管理者、サイト設定を作成
     */
    public static function installSite(
        array $data
    ): bool {
        try {
            if (self::isInstalled()) {
                return false;
            }

            $config = self::databaseConfig();

            if (!self::isDatabaseConfigured()) {
                return false;
            }

            $pdo = self::connectDatabase($config);

            $prefix = $config['table_prefix'];

            self::createTables(
                $pdo,
                $prefix
            );

            self::createAdministrator(
                $pdo,
                $prefix,
                $data
            );

            self::createGeneralConfig($data);

            self::createLockFile();

            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    /*
     * DB入力値を確認
     */
    private static function validateDatabaseData(
        array $data
    ): void {
        if (
            empty($data['db_host']) ||
            empty($data['db_name']) ||
            empty($data['db_user']) ||
            empty($data['table_prefix'])
        ) {
            throw new RuntimeException(
                'データベース設定が不足しています。'
            );
        }

        if (
            !preg_match(
                '/^[A-Za-z0-9_]+$/',
                $data['table_prefix']
            )
        ) {
            throw new RuntimeException(
                'テーブル接頭辞が正しくありません。'
            );
        }
    }

    /*
     * 作成済みDBへ接続
     */
    private static function connectDatabase(
        array $config
    ): PDO {
        $dsn = 'mysql:host='
            . $config['db_host']
            . ';dbname='
            . $config['db_name']
            . ';charset=utf8mb4';

        return new PDO(
            $dsn,
            $config['db_user'],
            $config['db_pass'] ?? '',
            [
                PDO::ATTR_ERRMODE =>
                    PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE =>
                    PDO::FETCH_ASSOC,
            ]
        );
    }

    /*
     * CMS用テーブルを作成
     */
    private static function createTables(
        PDO $pdo,
        string $prefix
    ): void {
        $users = self::tableName(
            $prefix,
            'users'
        );

        $categories = self::tableName(
            $prefix,
            'categories'
        );

        $posts = self::tableName(
            $prefix,
            'posts'
        );

        $pages = self::tableName(
            $prefix,
            'pages'
        );

        $media = self::tableName(
            $prefix,
            'media'
        );

        $extensionSettings = self::tableName(
            $prefix,
            'extension_settings'
        );

        $migrations = self::tableName(
            $prefix,
            'nexapress_migrations'
        );

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS {$users} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS {$categories} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS {$posts} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                content TEXT NOT NULL,
                status VARCHAR(20) NOT NULL
                    DEFAULT 'draft',
                user_id INT NOT NULL,
                category_id INT NULL,
                published_at DATETIME NULL,
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                FOREIGN KEY (user_id)
                    REFERENCES {$users}(id)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS {$pages} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                content TEXT NOT NULL,
                status VARCHAR(20) NOT NULL
                    DEFAULT 'draft',
                user_id INT NOT NULL,
                published_at DATETIME NULL,
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                FOREIGN KEY (user_id)
                    REFERENCES {$users}(id)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS {$media} (
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
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                FOREIGN KEY (user_id)
                    REFERENCES {$users}(id)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS {$extensionSettings} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                extension_key VARCHAR(100)
                    NOT NULL UNIQUE,
                is_enabled TINYINT(1) NOT NULL
                    DEFAULT 0,
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS {$migrations} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255)
                    NOT NULL UNIQUE,
                executed_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    /*
     * 最初の管理者を作成
     */
    private static function createAdministrator(
        PDO $pdo,
        string $prefix,
        array $data
    ): void {
        $users = self::tableName(
            $prefix,
            'users'
        );

        $passwordHash = password_hash(
            $data['admin_password'],
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            throw new RuntimeException(
                'パスワードを暗号化できませんでした。'
            );
        }

        $stmt = $pdo->prepare("
            INSERT INTO {$users} (
                username,
                name,
                email,
                password_hash
            )
            VALUES (
                :username,
                :name,
                :email,
                :password_hash
            )
        ");

        $stmt->execute([
            ':username' =>
                $data['admin_username'],
            ':name' =>
                $data['admin_username'],
            ':email' =>
                $data['admin_email'],
            ':password_hash' =>
                $passwordHash,
        ]);
    }

    /*
     * サイト設定ファイルを作成
     */
    private static function createGeneralConfig(
        array $data
    ): void {
        self::writePhpConfig(
            BASE_PATH . '/config/general.php',
            [
                'site_title' =>
                    $data['site_title'],
                'timezone' => 'Asia/Tokyo',
                'site_icon' => '',
                'discourage_search_engines' =>
                    (bool) (
                        $data[
                            'discourage_search_engines'
                        ] ?? false
                    ),
            ]
        );
    }

    /*
     * PHP設定ファイルを書き込む
     */
    private static function writePhpConfig(
        string $path,
        array $config
    ): void {
        $content = "<?php\n\nreturn "
            . var_export($config, true)
            . ";\n";

        $result = file_put_contents(
            $path,
            $content,
            LOCK_EX
        );

        if ($result === false) {
            throw new RuntimeException(
                '設定ファイルを書き込めませんでした。'
            );
        }
    }

    /*
     * インストール完了ファイルを作成
     */
    private static function createLockFile(): void
    {
        $result = file_put_contents(
            BASE_PATH
                . '/storage/installed.lock',
            'installed',
            LOCK_EX
        );

        if ($result === false) {
            throw new RuntimeException(
                'インストール完了情報を書き込めませんでした。'
            );
        }
    }

    /*
     * 安全なテーブル名を生成
     */
    private static function tableName(
        string $prefix,
        string $name
    ): string {
        if (
            !preg_match(
                '/^[A-Za-z0-9_]+$/',
                $prefix
            )
        ) {
            throw new RuntimeException(
                'テーブル接頭辞が正しくありません。'
            );
        }

        return '`' . $prefix . $name . '`';
    }
}