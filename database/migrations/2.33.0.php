<?php

return function (\PDO $pdo): void {
    $type = $pdo->prepare(
        "SELECT DATA_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'content'"
    );
    foreach (['posts', 'pages', 'post_revisions'] as $name) {
        $type->execute([\app\Core\Database::tablePrefix() . $name]);
        $dataType = $type->fetchColumn();
        $type->closeCursor();
        if (in_array($dataType, ['mediumtext', 'longtext'], true)) {
            continue;
        }
        if ($dataType !== 'text') {
            throw new \RuntimeException('本文欄のデータ型を確認できませんでした。');
        }

        $table = \app\Core\Database::table($name);
        $pdo->exec("ALTER TABLE {$table} MODIFY COLUMN content MEDIUMTEXT NOT NULL");
    }

    // Each ALTER TABLE commits implicitly, so start a transaction for the migration record.
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }
};
