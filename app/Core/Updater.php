<?php

namespace app\Core;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

class Updater
{
    private static $lockHandle = null;
    public static function install(
        array $updateInfo
    ): array {
        self::validateUpdateInfo($updateInfo);

        $packagePath = null;
        $backupPath = null;

        self::acquireLock();

        try {
            self::prepareExecution();
            self::runPreflightChecks($updateInfo);

            self::writeLog('Update started', [
                'from_version' =>
                    $updateInfo['current_version'],
                'to_version' =>
                    $updateInfo['latest_version'],
            ]);

            $version = $updateInfo['latest_version'];
            $asset = $updateInfo['asset'];

            $packagePath = UpdatePackage::download(
                $asset,
                $version
            );

            $packageInfo = UpdatePackage::extract(
                $packagePath,
                $version
            );

            self::validatePackageFiles(
                $packageInfo['package_directory'],
                $version
            );

            $backupPath = UpdateBackup::create(
                $packageInfo['package_directory'],
                $updateInfo['current_version'],
                $version
            );

            self::enableMaintenanceMode();

            self::applyFiles(
                $packageInfo['package_directory'],
                true
            );

            $migrationDirectory =
                $packageInfo['package_directory']
                . '/database/migrations';

            $migrations = Migrator::run(
                $migrationDirectory
            );

            self::applyVersionFile(
                $packageInfo['package_directory']
            );

            self::disableMaintenanceMode();

            self::writeLog('Update completed', [
                'previous_version' =>
                    $updateInfo['current_version'],
                'current_version' => $version,
                'backup' => basename($backupPath),
                'migrations' => $migrations,
            ]);

            return [
                'success' => true,
                'previous_version' =>
                    $updateInfo['current_version'],
                'current_version' => $version,
                'backup_path' => $backupPath,
                'migrations' => $migrations,
            ];
        } catch (Throwable $exception) {
            self::disableMaintenanceMode();

            $restored = false;
            $restoreError = null;

            if (
                $backupPath !== null &&
                is_file($backupPath)
            ) {
                try {
                    UpdateBackup::restore($backupPath);
                    $restored = true;
                } catch (Throwable $restoreException) {
                    $restoreError =
                        $restoreException->getMessage();
                }
            }

            $message = '更新に失敗しました。';

            if ($restored) {
                $message .= ' 更新前の状態へ復元しました。';
            } elseif ($restoreError !== null) {
                $message .= ' 自動復元にも失敗しました：'
                    . $restoreError;
            }

            if ($backupPath !== null) {
                $message .= ' バックアップ：'
                    . basename($backupPath);
            }

            self::writeLog('Update failed', [
                'from_version' =>
                    $updateInfo['current_version'] ?? '',
                'to_version' =>
                    $updateInfo['latest_version'] ?? '',
                'error' => $exception->getMessage(),
                'restored' => $restored,
                'restore_error' => $restoreError,
                'backup' => $backupPath !== null
                    ? basename($backupPath)
                    : null,
            ]);

            throw new RuntimeException(
                $message,
                0,
                $exception
            );
        } finally {
            self::releaseLock();
        }
    }
    private static function prepareExecution(): void
    {
        ignore_user_abort(true);

        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }
    }

    private static function runPreflightChecks(
        array $updateInfo
    ): void {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'PHPのcURL拡張機能が必要です。'
            );
        }

        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException(
                'PHPのZipArchive拡張機能が必要です。'
            );
        }

        if (!function_exists('hash_file')) {
            throw new RuntimeException(
                'SHA-256を確認できない環境です。'
            );
        }

        $requiredPaths = [
            BASE_PATH,
            BASE_PATH . '/app',
            BASE_PATH . '/config',
            BASE_PATH . '/public',
            BASE_PATH . '/storage',
            BASE_PATH . '/storage/cache',
        ];

        foreach ($requiredPaths as $path) {
            if (!is_dir($path)) {
                throw new RuntimeException(
                    '必要なフォルダがありません：'
                    . $path
                );
            }

            if (!is_writable($path)) {
                throw new RuntimeException(
                    '書き込み権限がありません：'
                    . $path
                );
            }
        }

        $workingDirectories = [
            BASE_PATH . '/storage/cache/updates',
            BASE_PATH . '/storage/backups',
            BASE_PATH . '/database/migrations',
        ];

        foreach ($workingDirectories as $directory) {
            if (
                !is_dir($directory) &&
                !mkdir($directory, 0755, true)
            ) {
                throw new RuntimeException(
                    '作業フォルダを作成できません：'
                    . $directory
                );
            }

            if (!is_writable($directory)) {
                throw new RuntimeException(
                    '作業フォルダへ書き込めません：'
                    . $directory
                );
            }
        }

        $packageSize = (int)(
            $updateInfo['asset']['size'] ?? 0
        );

        $freeSpace = disk_free_space(BASE_PATH);

        if (
            $freeSpace !== false &&
            $packageSize > 0
        ) {
            $requiredSpace = max(
                $packageSize * 5,
                104857600
            );

            if ($freeSpace < $requiredSpace) {
                throw new RuntimeException(
                    '更新に必要な空き容量がありません。'
                );
            }
        }
    }

    private static function writeLog(
        string $message,
        array $context = []
    ): void {
        $logDirectory = BASE_PATH
            . '/storage/logs';

        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0755, true);
        }

        $line = '['
            . date('Y-m-d H:i:s')
            . '] '
            . $message;

        if (!empty($context)) {
            $line .= ' ' . json_encode(
                $context,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );
        }

        $line .= PHP_EOL;

        file_put_contents(
            $logDirectory . '/update.log',
            $line,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function validateUpdateInfo(
        array $updateInfo
    ): void {
        if (empty($updateInfo['update_available'])) {
            throw new RuntimeException(
                '利用できる更新はありません。'
            );
        }

        $currentVersion = (string)(
            $updateInfo['current_version'] ?? ''
        );

        $latestVersion = (string)(
            $updateInfo['latest_version'] ?? ''
        );

        if (
            !preg_match(
                '/^\d+\.\d+\.\d+$/',
                $currentVersion
            ) ||
            !preg_match(
                '/^\d+\.\d+\.\d+$/',
                $latestVersion
            )
        ) {
            throw new RuntimeException(
                '更新バージョンが正しくありません。'
            );
        }

        if (!version_compare(
            $latestVersion,
            $currentVersion,
            '>'
        )) {
            throw new RuntimeException(
                '更新対象のバージョンではありません。'
            );
        }

        $asset = $updateInfo['asset'] ?? null;

        if (
            !is_array($asset) ||
            empty($asset['name']) ||
            empty($asset['download_url']) ||
            empty($asset['size']) ||
            empty($asset['digest'])
        ) {
            throw new RuntimeException(
                '更新ZIPの情報が正しくありません。'
            );
        }
    }

    private static function validatePackageFiles(
        string $packageDirectory,
        string $expectedVersion
    ): void {
        if (!is_dir($packageDirectory)) {
            throw new RuntimeException(
                '更新ファイルの場所がありません。'
            );
        }

        $iterator = self::packageIterator(
            $packageDirectory
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = self::relativePath(
                $packageDirectory,
                $file->getPathname()
            );

            if (self::isProtectedPath($relativePath)) {
                throw new RuntimeException(
                    '保護対象のファイルが含まれています：'
                    . $relativePath
                );
            }

            if (!self::isAllowedPath($relativePath)) {
                throw new RuntimeException(
                    '更新対象外のファイルが含まれています：'
                    . $relativePath
                );
            }
        }

        $versionFile = $packageDirectory
            . '/config/version.php';

        if (!is_file($versionFile)) {
            throw new RuntimeException(
                '更新後のversion.phpがありません。'
            );
        }

        $versionConfig = require $versionFile;

        if (
            !is_array($versionConfig) ||
            ($versionConfig['version'] ?? '') !==
            $expectedVersion
        ) {
            throw new RuntimeException(
                '更新後のバージョンが一致しません。'
            );
        }
    }

    private static function applyFiles(
        string $packageDirectory,
        bool $skipVersionFile
    ): void {
        $iterator = self::packageIterator(
            $packageDirectory
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = self::relativePath(
                $packageDirectory,
                $file->getPathname()
            );

            if (
                $skipVersionFile &&
                $relativePath === 'config/version.php'
            ) {
                continue;
            }

            if (
                self::isProtectedPath($relativePath) ||
                !self::isAllowedPath($relativePath)
            ) {
                continue;
            }

            self::replaceFile(
                $file->getPathname(),
                BASE_PATH . '/' . $relativePath
            );
        }
    }

    private static function applyVersionFile(
        string $packageDirectory
    ): void {
        $source = $packageDirectory
            . '/config/version.php';

        $destination = BASE_PATH
            . '/config/version.php';

        self::replaceFile($source, $destination);
    }

    private static function replaceFile(
        string $source,
        string $destination
    ): void {
        $parentDirectory = dirname($destination);

        if (!is_dir($parentDirectory)) {
            if (!mkdir(
                $parentDirectory,
                0755,
                true
            )) {
                throw new RuntimeException(
                    '更新先フォルダを作成できません。'
                );
            }
        }

        $temporaryFile = $destination
            . '.nexapress-new';

        if (file_exists($temporaryFile)) {
            unlink($temporaryFile);
        }

        if (!copy($source, $temporaryFile)) {
            throw new RuntimeException(
                '更新ファイルをコピーできません：'
                . $destination
            );
        }

        if (file_exists($destination)) {
            $oldFile = $destination
                . '.nexapress-old';

            if (file_exists($oldFile)) {
                unlink($oldFile);
            }

            if (!rename($destination, $oldFile)) {
                unlink($temporaryFile);

                throw new RuntimeException(
                    '既存ファイルを移動できません：'
                    . $destination
                );
            }

            if (!rename(
                $temporaryFile,
                $destination
            )) {
                rename($oldFile, $destination);
                unlink($temporaryFile);

                throw new RuntimeException(
                    '更新ファイルを配置できません：'
                    . $destination
                );
            }

            unlink($oldFile);
            return;
        }

        if (!rename(
            $temporaryFile,
            $destination
        )) {
            unlink($temporaryFile);

            throw new RuntimeException(
                '更新ファイルを配置できません：'
                . $destination
            );
        }
    }

    private static function acquireLock(): void
    {
        $lockPath = BASE_PATH
            . '/storage/cache/updates/update.lock';

        $lockDirectory = dirname($lockPath);

        if (!is_dir($lockDirectory)) {
            mkdir($lockDirectory, 0755, true);
        }

        self::$lockHandle = fopen($lockPath, 'c+');

        if (
            self::$lockHandle === false ||
            !flock(
                self::$lockHandle,
                LOCK_EX | LOCK_NB
            )
        ) {
            throw new RuntimeException(
                '別の更新処理が実行中です。'
            );
        }
    }

    private static function releaseLock(): void
    {
        if (self::$lockHandle === null) {
            return;
        }

        flock(self::$lockHandle, LOCK_UN);
        fclose(self::$lockHandle);

        self::$lockHandle = null;
    }

    private static function enableMaintenanceMode(): void
    {
        file_put_contents(
            BASE_PATH . '/storage/maintenance.lock',
            date('Y-m-d H:i:s')
        );
    }

    private static function disableMaintenanceMode(): void
    {
        $lockPath = BASE_PATH
            . '/storage/maintenance.lock';

        if (file_exists($lockPath)) {
            unlink($lockPath);
        }
    }

    private static function packageIterator(
        string $packageDirectory
    ): RecursiveIteratorIterator {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $packageDirectory,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );
    }

    private static function relativePath(
        string $baseDirectory,
        string $filePath
    ): string {
        $relativePath = substr(
            $filePath,
            strlen(rtrim(
                $baseDirectory,
                '/\\'
            )) + 1
        );

        return str_replace(
            '\\',
            '/',
            $relativePath
        );
    }

    private static function isProtectedPath(
        string $relativePath
    ): bool {
        return self::matchesConfiguredPath(
            $relativePath,
            'protected_paths'
        );
    }

    private static function isAllowedPath(
        string $relativePath
    ): bool {
        return self::matchesConfiguredPath(
            $relativePath,
            'allowed_update_paths'
        );
    }

    private static function matchesConfiguredPath(
        string $relativePath,
        string $configKey
    ): bool {
        $config = require BASE_PATH
            . '/config/update.php';

        foreach ($config[$configKey] ?? [] as $path) {
            $path = trim(
                str_replace('\\', '/', $path),
                '/'
            );

            if (
                $relativePath === $path ||
                str_starts_with(
                    $relativePath,
                    $path . '/'
                )
            ) {
                return true;
            }
        }

        return false;
    }
}