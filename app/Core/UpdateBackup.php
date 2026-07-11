<?php

namespace app\Core;

use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

class UpdateBackup
{
    public static function create(
        string $packageDirectory,
        string $currentVersion,
        string $targetVersion
    ): string {
        self::validateVersion($currentVersion);
        self::validateVersion($targetVersion);

        if (!is_dir($packageDirectory)) {
            throw new RuntimeException(
                '更新ファイルの場所がありません。'
            );
        }

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'PHPのZipArchive拡張機能が必要です。'
            );
        }

        $backupDirectory = BASE_PATH
            . '/storage/backups';

        if (!is_dir($backupDirectory)) {
            mkdir($backupDirectory, 0755, true);
        }

        $backupPath = $backupDirectory
            . '/update-'
            . $currentVersion
            . '-to-'
            . $targetVersion
            . '-'
            . date('Ymd-His')
            . '.zip';

        $databaseBackupPath = self::createDatabaseBackup();

        $zip = new ZipArchive();

        if (
            $zip->open(
                $backupPath,
                ZipArchive::CREATE |
                ZipArchive::OVERWRITE
            ) !== true
        ) {
            unlink($databaseBackupPath);

            throw new RuntimeException(
                'バックアップZIPを作成できません。'
            );
        }

        try {
            $fileResult = self::addProjectFiles(
                $zip,
                $packageDirectory
            );

            if (!$zip->addFile(
                $databaseBackupPath,
                'database.sql'
            )) {
                throw new RuntimeException(
                    'DBバックアップをZIPへ追加できません。'
                );
            }

            $manifest = [
                'created_at' => date('Y-m-d H:i:s'),
                'from_version' => $currentVersion,
                'to_version' => $targetVersion,
                'existing_files' =>
                    $fileResult['existing_files'],
                'new_files' =>
                    $fileResult['new_files'],
            ];

            if (!$zip->addFromString(
                'backup-manifest.json',
                json_encode(
                    $manifest,
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRETTY_PRINT
                )
            )) {
                throw new RuntimeException(
                    'バックアップ情報を保存できません。'
                );
            }

            if (!$zip->close()) {
                throw new RuntimeException(
                    'バックアップZIPを完了できません。'
                );
            }
        } catch (Throwable $exception) {
            $zip->close();

            if (file_exists($backupPath)) {
                unlink($backupPath);
            }

            throw $exception;
        } finally {
            if (file_exists($databaseBackupPath)) {
                unlink($databaseBackupPath);
            }
        }

        return $backupPath;
    }

    public static function restore(
        string $backupPath
    ): void {
        if (!is_file($backupPath)) {
            throw new RuntimeException(
                '復元用バックアップがありません。'
            );
        }

        $restoreDirectory = BASE_PATH
            . '/storage/cache/updates/restore-'
            . bin2hex(random_bytes(8));

        if (!mkdir($restoreDirectory, 0755, true)) {
            throw new RuntimeException(
                '復元用フォルダを作成できません。'
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($backupPath) !== true) {
            self::removeDirectory($restoreDirectory);

            throw new RuntimeException(
                'バックアップZIPを開けません。'
            );
        }

        try {
            self::validateBackupEntries($zip);

            if (!$zip->extractTo($restoreDirectory)) {
                throw new RuntimeException(
                    'バックアップZIPを展開できません。'
                );
            }
        } catch (Throwable $exception) {
            $zip->close();
            self::removeDirectory($restoreDirectory);

            throw $exception;
        }

        $zip->close();

        try {
            $manifest = self::loadBackupManifest(
                $restoreDirectory
            );

            self::removeNewFiles(
                $manifest['new_files']
            );

            self::restoreProjectFiles(
                $restoreDirectory,
                $manifest['existing_files']
            );

            self::restoreDatabase(
                $restoreDirectory . '/database.sql'
            );
        } finally {
            self::removeDirectory($restoreDirectory);
        }
    }

    private static function addProjectFiles(
        ZipArchive $zip,
        string $packageDirectory
    ): array {
        $existingFiles = [];
        $newFiles = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $packageDirectory,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $sourcePath = $file->getPathname();

            $relativePath = substr(
                $sourcePath,
                strlen(rtrim(
                    $packageDirectory,
                    '/\\'
                )) + 1
            );

            $relativePath = str_replace(
                '\\',
                '/',
                $relativePath
            );

            if (self::isProtectedPath($relativePath)) {
                continue;
            }

            $currentFile = BASE_PATH
                . '/'
                . $relativePath;

            if (is_file($currentFile)) {
                if (!$zip->addFile(
                    $currentFile,
                    'files/' . $relativePath
                )) {
                    throw new RuntimeException(
                        'バックアップ対象を追加できません：'
                        . $relativePath
                    );
                }

                $existingFiles[] = $relativePath;
            } else {
                $newFiles[] = $relativePath;
            }
        }

        return [
            'existing_files' => $existingFiles,
            'new_files' => $newFiles,
        ];
    }

    private static function createDatabaseBackup(): string
    {
        $temporaryDirectory = BASE_PATH
            . '/storage/cache/updates';

        if (!is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0755, true);
        }

        $temporaryPath = tempnam(
            $temporaryDirectory,
            'database-'
        );

        if ($temporaryPath === false) {
            throw new RuntimeException(
                'DBバックアップ用ファイルを作成できません。'
            );
        }

        $output = fopen($temporaryPath, 'wb');

        if ($output === false) {
            unlink($temporaryPath);

            throw new RuntimeException(
                'DBバックアップ用ファイルを開けません。'
            );
        }

        try {
            $pdo = Database::connect();

            fwrite(
                $output,
                "SET FOREIGN_KEY_CHECKS = 0;\n\n"
            );

            $tables = $pdo
                ->query('SHOW TABLES')
                ->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                self::writeTable(
                    $pdo,
                    $output,
                    (string)$table
                );
            }

            fwrite(
                $output,
                "SET FOREIGN_KEY_CHECKS = 1;\n"
            );
        } catch (Throwable $exception) {
            fclose($output);
            unlink($temporaryPath);

            throw new RuntimeException(
                'データベースのバックアップに失敗しました。',
                0,
                $exception
            );
        }

        fclose($output);

        return $temporaryPath;
    }

    private static function writeTable(
        PDO $pdo,
        $output,
        string $table
    ): void {
        $escapedTable = str_replace(
            '`',
            '``',
            $table
        );

        $createStatement = $pdo
            ->query(
                'SHOW CREATE TABLE `'
                . $escapedTable
                . '`'
            )
            ->fetch(PDO::FETCH_NUM);

        if (!$createStatement) {
            throw new RuntimeException(
                'テーブル構造を取得できません：'
                . $table
            );
        }

        fwrite(
            $output,
            'DROP TABLE IF EXISTS `'
            . $escapedTable
            . "`;\n"
        );

        fwrite(
            $output,
            $createStatement[1]
            . ";\n\n"
        );

        $rows = $pdo->query(
            'SELECT * FROM `'
            . $escapedTable
            . '`'
        );

        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columns = [];
            $values = [];

            foreach ($row as $column => $value) {
                $columns[] = '`'
                    . str_replace('`', '``', $column)
                    . '`';

                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = $pdo->quote(
                        (string)$value
                    );
                }
            }

            fwrite(
                $output,
                'INSERT INTO `'
                . $escapedTable
                . '` ('
                . implode(', ', $columns)
                . ') VALUES ('
                . implode(', ', $values)
                . ");\n"
            );
        }

        fwrite($output, "\n");
    }

    private static function isProtectedPath(
        string $relativePath
    ): bool {
        $configPath = BASE_PATH
            . '/config/update.php';

        $config = file_exists($configPath)
            ? require $configPath
            : [];

        foreach (
            $config['protected_paths'] ?? []
            as $protectedPath
        ) {
            $protectedPath = trim(
                str_replace(
                    '\\',
                    '/',
                    $protectedPath
                ),
                '/'
            );

            if (
                $relativePath === $protectedPath ||
                str_starts_with(
                    $relativePath,
                    $protectedPath . '/'
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private static function validateBackupEntries(
        ZipArchive $zip
    ): void {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);

            if ($entryName === false) {
                throw new RuntimeException(
                    'バックアップZIPを確認できません。'
                );
            }

            $entryName = str_replace(
                '\\',
                '/',
                $entryName
            );

            if (
                $entryName === '' ||
                str_contains($entryName, "\0") ||
                str_starts_with($entryName, '/') ||
                preg_match('/^[a-zA-Z]:\//', $entryName) ||
                preg_match('#(^|/)\.\.(/|$)#', $entryName)
            ) {
                throw new RuntimeException(
                    'バックアップZIPに不正なパスがあります。'
                );
            }

            if (
                $entryName !== 'backup-manifest.json' &&
                $entryName !== 'database.sql' &&
                $entryName !== 'files/' &&
                !str_starts_with($entryName, 'files/')
            ) {
                throw new RuntimeException(
                    'バックアップZIPに不正なファイルがあります。'
                );
            }

            self::rejectBackupSymbolicLink(
                $zip,
                $index
            );
        }
    }

    private static function rejectBackupSymbolicLink(
        ZipArchive $zip,
        int $index
    ): void {
        $operatingSystem = 0;
        $attributes = 0;

        if (!$zip->getExternalAttributesIndex(
            $index,
            $operatingSystem,
            $attributes
        )) {
            return;
        }

        $fileType = ($attributes >> 16) & 0170000;

        if ($fileType === 0120000) {
            throw new RuntimeException(
                'バックアップZIPにリンクが含まれています。'
            );
        }
    }

    private static function loadBackupManifest(
        string $restoreDirectory
    ): array {
        $manifestPath = $restoreDirectory
            . '/backup-manifest.json';

        if (!is_file($manifestPath)) {
            throw new RuntimeException(
                'バックアップ情報がありません。'
            );
        }

        $content = file_get_contents($manifestPath);
        $manifest = json_decode((string)$content, true);

        if (
            !is_array($manifest) ||
            !is_array($manifest['existing_files'] ?? null) ||
            !is_array($manifest['new_files'] ?? null)
        ) {
            throw new RuntimeException(
                'バックアップ情報が正しくありません。'
            );
        }

        return $manifest;
    }

    private static function removeNewFiles(
        array $newFiles
    ): void {
        foreach ($newFiles as $relativePath) {
            $relativePath = self::validateRestorePath(
                (string)$relativePath
            );

            $targetPath = BASE_PATH
                . '/'
                . $relativePath;

            if (is_file($targetPath)) {
                unlink($targetPath);
            }
        }
    }

    private static function restoreProjectFiles(
        string $restoreDirectory,
        array $existingFiles
    ): void {
        foreach ($existingFiles as $relativePath) {
            $relativePath = self::validateRestorePath(
                (string)$relativePath
            );

            $sourcePath = $restoreDirectory
                . '/files/'
                . $relativePath;

            if (!is_file($sourcePath)) {
                throw new RuntimeException(
                    '復元ファイルがありません：'
                    . $relativePath
                );
            }

            self::restoreFile(
                $sourcePath,
                BASE_PATH . '/' . $relativePath
            );
        }
    }

    private static function restoreFile(
        string $source,
        string $destination
    ): void {
        $parentDirectory = dirname($destination);

        if (!is_dir($parentDirectory)) {
            mkdir($parentDirectory, 0755, true);
        }

        $temporaryFile = $destination
            . '.nexapress-restore';

        if (file_exists($temporaryFile)) {
            unlink($temporaryFile);
        }

        if (!copy($source, $temporaryFile)) {
            throw new RuntimeException(
                '復元ファイルをコピーできません。'
            );
        }

        $currentFile = $destination
            . '.nexapress-current';

        if (file_exists($currentFile)) {
            unlink($currentFile);
        }

        if (
            file_exists($destination) &&
            !rename($destination, $currentFile)
        ) {
            unlink($temporaryFile);

            throw new RuntimeException(
                '現在のファイルを移動できません。'
            );
        }

        if (!rename($temporaryFile, $destination)) {
            if (file_exists($currentFile)) {
                rename($currentFile, $destination);
            }

            throw new RuntimeException(
                'バックアップを復元できません。'
            );
        }

        if (file_exists($currentFile)) {
            unlink($currentFile);
        }
    }

    private static function restoreDatabase(
        string $sqlPath
    ): void {
        if (!is_file($sqlPath)) {
            throw new RuntimeException(
                'DBバックアップがありません。'
            );
        }

        $sql = file_get_contents($sqlPath);

        if ($sql === false) {
            throw new RuntimeException(
                'DBバックアップを読み込めません。'
            );
        }

        $pdo = Database::connect();
        $statements = self::splitSqlStatements($sql);

        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($statements as $statement) {
                if (trim($statement) === '') {
                    continue;
                }

                $pdo->exec($statement);
            }
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'データベースを復元できません。',
                0,
                $exception
            );
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private static function splitSqlStatements(
        string $sql
    ): array {
        $statements = [];
        $statement = '';

        $singleQuote = false;
        $doubleQuote = false;
        $backtick = false;
        $escaped = false;

        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $statement .= $character;

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if (
                $character === '\\' &&
                ($singleQuote || $doubleQuote)
            ) {
                $escaped = true;
                continue;
            }

            if (
                $character === "'" &&
                !$doubleQuote &&
                !$backtick
            ) {
                $singleQuote = !$singleQuote;
                continue;
            }

            if (
                $character === '"' &&
                !$singleQuote &&
                !$backtick
            ) {
                $doubleQuote = !$doubleQuote;
                continue;
            }

            if (
                $character === '`' &&
                !$singleQuote &&
                !$doubleQuote
            ) {
                $backtick = !$backtick;
                continue;
            }

            if (
                $character === ';' &&
                !$singleQuote &&
                !$doubleQuote &&
                !$backtick
            ) {
                $statements[] = trim($statement);
                $statement = '';
            }
        }

        if (trim($statement) !== '') {
            $statements[] = trim($statement);
        }

        return $statements;
    }

    private static function validateRestorePath(
        string $relativePath
    ): string {
        $relativePath = trim(
            str_replace('\\', '/', $relativePath),
            '/'
        );

        if (
            $relativePath === '' ||
            str_contains($relativePath, "\0") ||
            preg_match('#(^|/)\.\.(/|$)#', $relativePath) ||
            self::isProtectedPath($relativePath) ||
            !self::isAllowedPath($relativePath)
        ) {
            throw new RuntimeException(
                '復元対象のパスが正しくありません。'
            );
        }

        return $relativePath;
    }

    private static function isAllowedPath(
        string $relativePath
    ): bool {
        $config = require BASE_PATH
            . '/config/update.php';

        foreach (
            $config['allowed_update_paths'] ?? []
            as $allowedPath
        ) {
            $allowedPath = trim(
                str_replace('\\', '/', $allowedPath),
                '/'
            );

            if (
                $relativePath === $allowedPath ||
                str_starts_with(
                    $relativePath,
                    $allowedPath . '/'
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private static function removeDirectory(
        string $directory
    ): void {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    private static function validateVersion(
        string $version
    ): void {
        if (!preg_match(
            '/^\d+\.\d+\.\d+$/',
            $version
        )) {
            throw new RuntimeException(
                'バックアップ対象のバージョンが正しくありません。'
            );
        }
    }
}