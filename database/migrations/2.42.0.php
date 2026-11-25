<?php

return function (\PDO $pdo): void {
    \app\Core\SiteSchema::ensureCategoryPages($pdo);
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }
};
