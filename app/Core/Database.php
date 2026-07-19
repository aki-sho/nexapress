<?php

namespace app\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $pdo = null;

    private static ?array $config = null;

    /*
     * データベースへ接続
     */
    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $config = self::config();

        try {
            $dsn = 'mysql:host='
                . $config['db_host']
                . ';dbname='
                . $config['db_name']
                . ';charset=utf8mb4';

            self::$pdo = new PDO(
                $dsn,
                $config['db_user'],
                $config['db_pass'] ?? '',
                [
                    PDO::ATTR_ERRMODE =>
                        PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE =>
                        PDO::FETCH_ASSOC,
                ]
            );

            return self::$pdo;
        } catch (PDOException $exception) {
            exit(
                'データベース接続に失敗しました。'
            );
        }
    }

    /*
     * DB設定を取得
     */
    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $configPath = BASE_PATH
            . '/config/config.php';

        if (!file_exists($configPath)) {
            redirect_to('install/database');
        }

        $config = require $configPath;

        if (!is_array($config)) {
            redirect_to('install/database');
        }

        if (
            empty($config['db_host']) ||
            empty($config['db_name']) ||
            empty($config['db_user'])
        ) {
            redirect_to('install/database');
        }

        /*
         * v1.9.1以前の設定ファイルには
         * table_prefixが存在しないため、
         * 空文字を初期値にする
         */
        $config['table_prefix'] =
            $config['table_prefix'] ?? '';

        self::validatePrefix(
            $config['table_prefix']
        );

        self::$config = $config;

        return self::$config;
    }

    /*
     * テーブル接頭辞を取得
     */
    public static function tablePrefix(): string
    {
        $config = self::config();

        return $config['table_prefix'] ?? '';
    }

    /*
     * 接頭辞付きテーブル名を取得
     */
    public static function table(
        string $name
    ): string {
        if (
            !preg_match(
                '/^[A-Za-z0-9_]+$/',
                $name
            )
        ) {
            throw new RuntimeException(
                'テーブル名が正しくありません。'
            );
        }

        $tableName = self::tablePrefix()
            . $name;

        return '`' . $tableName . '`';
    }

    /*
     * テーブル接頭辞を確認
     */
    private static function validatePrefix(
        string $prefix
    ): void {
        if (
            $prefix !== '' &&
            !preg_match(
                '/^[A-Za-z0-9_]+$/',
                $prefix
            )
        ) {
            throw new RuntimeException(
                'テーブル接頭辞が正しくありません。'
            );
        }
    }
}