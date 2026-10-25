<?php

return function (\PDO $pdo): void {
    \app\Core\SiteSchema::ensure($pdo);
    // MySQL DDL commits implicitly; Migrator records completion in a transaction.
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }
};
