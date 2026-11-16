<?php

return function (\PDO $pdo): void {
    \app\Core\SiteSchema::ensurePageLayouts($pdo);
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }
};
