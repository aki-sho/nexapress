<?php

namespace app\Core;

use PDO;
use RuntimeException;
use Throwable;

class Migrator
{
    /*
     * 未実行のマイグレーションを実行
     */
    public static function run(
        string $migrationDirectory
    ): array {
        if (!is_dir($migrationDirectory)) {
            return [];
        }

        $pdo = Database::connect();

        self::createMigrationTable($pdo);

        $appliedMigrations =
            self::applied($pdo);

        $migrationFiles = glob(
            rtrim(
                $migrationDirectory,
                '/\\'
            ) . '/*.php'
        );

        if (!$migrationFiles) {
            return [];
        }

        natsort($migrationFiles);

        $executed = [];

        foreach ($migrationFiles as $migrationFile) {
            $migrationName = basename(
                $migrationFile,
                '.php'
            );

            if (
                in_array(
                    $migrationName,
                    $appliedMigrations,
                    true
                )
            ) {
                continue;
            }

            self::execute(
                $pdo,
                $migrationFile,
                $migrationName
            );

            $executed[] = $migrationName;
        }

        return $executed;
    }

    /*
     * マイグレーション管理テーブルを作成
     */
    private static function createMigrationTable(
        PDO $pdo
    ): void {
        $table = Database::table(
            'nexapress_migrations'
        );

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS {$table} (
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
     * 実行済みマイグレーションを取得
     */
    private static function applied(
        PDO $pdo
    ): array {
        $table = Database::table(
            'nexapress_migrations'
        );

        $stmt = $pdo->query("
            SELECT migration
            FROM {$table}
            ORDER BY id ASC
        ");

        return $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );
    }

    /*
     * マイグレーションを実行
     */
    private static function execute(
        PDO $pdo,
        string $migrationFile,
        string $migrationName
    ): void {
        $migration = require $migrationFile;

        if (!is_callable($migration)) {
            throw new RuntimeException(
                'マイグレーションの形式が'
                . '正しくありません：'
                . $migrationName
            );
        }

        try {
            $pdo->beginTransaction();

            $migration($pdo);

            $table = Database::table(
                'nexapress_migrations'
            );

            $stmt = $pdo->prepare("
                INSERT INTO {$table} (
                    migration
                )
                VALUES (
                    :migration
                )
            ");

            $stmt->execute([
                ':migration' => $migrationName,
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw new RuntimeException(
                'マイグレーションに失敗しました：'
                . $migrationName,
                0,
                $exception
            );
        }
    }
}