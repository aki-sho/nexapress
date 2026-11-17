<?php

return function (\PDO $pdo): void {
    \app\Core\SiteSchema::ensureMediaFolders($pdo);
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }
};
