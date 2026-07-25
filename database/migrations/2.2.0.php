<?php

use app\Core\Database;

return function (\PDO $pdo): void {
    $prefix = Database::tablePrefix();

    $tableName = $prefix . 'users';
    $users = Database::table('users');

    /*
     * roleカラムの有無を確認
     */
    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND COLUMN_NAME = 'role'
    ");

    $statement->execute([
        ':table_name' => $tableName,
    ]);

    $columnExists =
        (int) $statement->fetchColumn() > 0;

    /*
     * 既存のusersテーブルへ
     * roleカラムを追加
     */
    if (!$columnExists) {
        $pdo->exec("
            ALTER TABLE {$users}
            ADD COLUMN role
                VARCHAR(30) NOT NULL
                DEFAULT 'administrator'
                AFTER password_hash
        ");
    }

    /*
     * ALTER TABLE後に
     * トランザクションを再開
     */
    if (!$pdo->inTransaction()) {
        if (!$pdo->beginTransaction()) {
            throw new \RuntimeException(
                'マイグレーション処理を'
                . '再開できませんでした。'
            );
        }
    }
};