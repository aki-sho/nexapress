<?php

return function (\PDO $pdo): void {
    \app\Core\SiteSchema::ensureDesign($pdo);
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }
};
