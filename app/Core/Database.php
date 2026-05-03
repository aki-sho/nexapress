<?php

namespace app\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $configPath = BASE_PATH . '/config/config.php';

        if (!file_exists($configPath)) {
            redirect_to('install');
            exit;
        }

        $config = require $configPath;

        if (!is_array($config)) {
            redirect_to('install');
            exit;
        }

        if (
            empty($config['db_host']) ||
            empty($config['db_name']) ||
            empty($config['db_user'])
        ) {
            redirect_to('install');
            exit;
        }

        try {
            self::$pdo = new PDO(
                'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
                $config['db_user'],
                $config['db_pass'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            return self::$pdo;
        } catch (PDOException $e) {
            exit('データベース接続に失敗しました。');
        }
    }
}