<?php

use app\Core\Database;

return function (\PDO $pdo): void {
    $prefix = Database::tablePrefix();

    $tableName = $prefix . 'users';
    $users = Database::table('users');

    /*
     * usernameカラムの有無を確認
     */
    $columnStatement = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND COLUMN_NAME = 'username'
    ");

    $columnStatement->execute([
        ':table_name' => $tableName,
    ]);

    $columnExists =
        (int) $columnStatement->fetchColumn() > 0;

    /*
     * v1.9.1のusersテーブルへ
     * usernameカラムを追加
     */
    if (!$columnExists) {
        $pdo->exec("
            ALTER TABLE {$users}
            ADD COLUMN username
                VARCHAR(100) NULL
                AFTER id
        ");
    }

    /*
     * 既存ユーザーへ一意のユーザー名を設定
     */
    $pdo->exec("
        UPDATE {$users}
        SET username = CONCAT(
            'user_',
            id
        )
        WHERE username IS NULL
           OR username = ''
    ");

    /*
     * usernameを必須項目へ変更
     */
    $pdo->exec("
        ALTER TABLE {$users}
        MODIFY COLUMN username
            VARCHAR(100) NOT NULL
    ");

    /*
     * UNIQUEインデックスの有無を確認
     */
    $indexStatement = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND INDEX_NAME = 'username_unique'
    ");

    $indexStatement->execute([
        ':table_name' => $tableName,
    ]);

    $indexExists =
        (int) $indexStatement->fetchColumn() > 0;

    if (!$indexExists) {
        $pdo->exec("
            ALTER TABLE {$users}
            ADD UNIQUE KEY username_unique (
                username
            )
        ");
    }

    /*
     * MySQLのALTER TABLEによる
     * 暗黙的コミット後に、
     * Migratorが実行履歴を保存できるよう
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