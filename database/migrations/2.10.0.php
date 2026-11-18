<?php

return function (\PDO $pdo): void {
    \app\Core\SiteSchema::ensurePostLayouts($pdo);
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }
};
