<?php

namespace app\Core;

use PDO;
use RuntimeException;
use Throwable;

class Migrator
{
    public static function run(
        string $migrationDirectory
    ): array {
        if (!is_dir($migrationDirectory)) {
            return [];
        }

        $pdo = Database::connect();

        self::createMigrationTable($pdo);

        $appliedMigrations = self::applied($pdo);
        $migrationFiles = glob(
            rtrim($migrationDirectory, '/\\') . '/*.php'
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

            if (in_array(
                $migrationName,
                $appliedMigrations,
                true
            )) {
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

    private static function createMigrationTable(
        PDO $pdo
    ): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nexapress_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private static function applied(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT migration
            FROM nexapress_migrations
            ORDER BY id ASC
        ");

        return $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );
    }

    private static function execute(
        PDO $pdo,
        string $migrationFile,
        string $migrationName
    ): void {
        $migration = require $migrationFile;

        if (!is_callable($migration)) {
            throw new RuntimeException(
                'マイグレーションの形式が正しくありません：'
                . $migrationName
            );
        }

        try {
            $pdo->beginTransaction();

            $migration($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO nexapress_migrations (
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