<?php

namespace app\Core;

use RuntimeException;
use Throwable;
use ZipArchive;

class UpdatePackage
{
    public static function download(
        array $asset,
        string $version
    ): string {
        $config = self::loadConfig();

        self::validateVersion($version);
        self::validateAsset($asset, $version, $config);

        $cacheDirectory = BASE_PATH
            . '/storage/cache/updates';

        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0755, true);
        }

        $packagePath = $cacheDirectory
            . '/'
            . $asset['name'];

        $temporaryPath = $packagePath . '.part';

        if (file_exists($temporaryPath)) {
            unlink($temporaryPath);
        }

        self::downloadFile(
            $asset['download_url'],
            $temporaryPath,
            (int)($config['download_timeout'] ?? 300)
        );

        self::validateDownloadedFile(
            $temporaryPath,
            $asset,
            $config
        );

        if (file_exists($packagePath)) {
            unlink($packagePath);
        }

        if (!rename($temporaryPath, $packagePath)) {
            unlink($temporaryPath);

            throw new RuntimeException(
                '更新ZIPを保存できませんでした。'
            );
        }

        return $packagePath;
    }

    public static function extract(
        string $packagePath,
        string $version
    ): array {
        self::validateVersion($version);

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'PHPのZipArchive拡張機能が必要です。'
            );
        }

        if (!is_file($packagePath)) {
            throw new RuntimeException(
                '更新ZIPが見つかりません。'
            );
        }

        $config = self::loadConfig();

        $extractDirectory = BASE_PATH
            . '/storage/cache/updates/extracted-'
            . $version;

        self::removeDirectory($extractDirectory);

        if (!mkdir($extractDirectory, 0755, true)) {
            throw new RuntimeException(
                '更新ZIPの展開先を作成できません。'
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($packagePath) !== true) {
            self::removeDirectory($extractDirectory);

            throw new RuntimeException(
                '更新ZIPを開けませんでした。'
            );
        }

        try {
            self::validateZipEntries($zip, $config);

            self::extractZipEntries(
                $zip,
                $extractDirectory
            );
        } catch (Throwable $exception) {
            $zip->close();
            self::removeDirectory($extractDirectory);

            throw $exception;
        }

        $zip->close();

        try {
            $manifest = self::loadManifest(
                $extractDirectory,
                $version
            );
        } catch (Throwable $exception) {
            self::removeDirectory($extractDirectory);

            throw $exception;
        }

        return [
            'extract_directory' => $extractDirectory,
            'package_directory' => $extractDirectory . '/package',
            'manifest' => $manifest,
        ];
    }



    private static function downloadFile(
        string $downloadUrl,
        string $destination,
        int $timeout
    ): void {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'PHPのcURL拡張機能が必要です。'
            );
        }

        $output = fopen($destination, 'wb');

        if ($output === false) {
            throw new RuntimeException(
                '更新ZIPの一時ファイルを作成できません。'
            );
        }

        $curl = curl_init($downloadUrl);

        curl_setopt_array($curl, [
            CURLOPT_FILE => $output,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FAILONERROR => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/octet-stream',
                'User-Agent: NexaPress-Updater',
            ],
        ]);

        $result = curl_exec($curl);
        $statusCode = curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );
        $error = curl_error($curl);

        curl_close($curl);
        fclose($output);

        if (
            $result === false ||
            $statusCode < 200 ||
            $statusCode >= 300
        ) {
            if (file_exists($destination)) {
                unlink($destination);
            }

            throw new RuntimeException(
                '更新ZIPのダウンロードに失敗しました。'
                . $error
            );
        }
    }

    private static function validateAsset(
        array $asset,
        string $version,
        array $config
    ): void {
        $expectedName = 'nexapress-update-'
            . $version
            . '.zip';

        if (($asset['name'] ?? '') !== $expectedName) {
            throw new RuntimeException(
                '更新ZIPのファイル名が正しくありません。'
            );
        }

        $downloadUrl = $asset['download_url'] ?? '';

        if ($downloadUrl === '') {
            throw new RuntimeException(
                '更新ZIPのURLがありません。'
            );
        }

        $urlParts = parse_url($downloadUrl);

        if (
            ($urlParts['scheme'] ?? '') !== 'https' ||
            ($urlParts['host'] ?? '') !== 'github.com'
        ) {
            throw new RuntimeException(
                '更新ZIPのURLが正しくありません。'
            );
        }

        $maxSize = (int)(
            $config['max_package_size'] ?? 52428800
        );

        $assetSize = (int)($asset['size'] ?? 0);

        if ($assetSize <= 0 || $assetSize > $maxSize) {
            throw new RuntimeException(
                '更新ZIPのサイズが正しくありません。'
            );
        }
    }

    private static function validateDownloadedFile(
        string $filePath,
        array $asset,
        array $config
    ): void {
        if (!file_exists($filePath)) {
            throw new RuntimeException(
                'ダウンロードした更新ZIPがありません。'
            );
        }

        $fileSize = filesize($filePath);
        $expectedSize = (int)($asset['size'] ?? 0);
        $maxSize = (int)(
            $config['max_package_size'] ?? 52428800
        );

        if (
            $fileSize === false ||
            $fileSize <= 0 ||
            $fileSize > $maxSize ||
            $fileSize !== $expectedSize
        ) {
            unlink($filePath);

            throw new RuntimeException(
                '更新ZIPのサイズ確認に失敗しました。'
            );
        }

        $digest = (string)($asset['digest'] ?? '');

        if (!str_starts_with($digest, 'sha256:')) {
            unlink($filePath);

            throw new RuntimeException(
                '更新ZIPのSHA-256情報がありません。'
            );
        }

        $expectedHash = substr($digest, 7);
        $actualHash = hash_file('sha256', $filePath);

        if (
            $actualHash === false ||
            !hash_equals($expectedHash, $actualHash)
        ) {
            unlink($filePath);

            throw new RuntimeException(
                '更新ZIPのSHA-256が一致しません。'
            );
        }
    }


    private static function validateZipEntries(
        ZipArchive $zip,
        array $config
    ): void {
        $maxFiles = (int)(
            $config['max_package_files'] ?? 5000
        );

        $maxExtractedSize = (int)(
            $config['max_extracted_size'] ?? 209715200
        );

        if ($zip->numFiles > $maxFiles) {
            throw new RuntimeException(
                '更新ZIPのファイル数が上限を超えています。'
            );
        }

        $totalSize = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);

            if ($entryName === false) {
                throw new RuntimeException(
                    '更新ZIPの内容を確認できません。'
                );
            }

            $entryName = str_replace(
                '\\',
                '/',
                $entryName
            );

            self::validateEntryName($entryName);

            $stat = $zip->statIndex($index);

            if (is_array($stat)) {
                $totalSize += (int)($stat['size'] ?? 0);
            }

            if ($totalSize > $maxExtractedSize) {
                throw new RuntimeException(
                    '更新ZIPの展開サイズが上限を超えています。'
                );
            }

            self::rejectSymbolicLink($zip, $index);
        }
    }

    private static function validateEntryName(
        string $entryName
    ): void {
        if (
            $entryName === '' ||
            str_contains($entryName, "\0") ||
            str_starts_with($entryName, '/') ||
            preg_match('/^[a-zA-Z]:\//', $entryName) ||
            preg_match('#(^|/)\.\.(/|$)#', $entryName)
        ) {
            throw new RuntimeException(
                '更新ZIPに不正なパスが含まれています。'
            );
        }
    }

    private static function rejectSymbolicLink(
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
                '更新ZIPにシンボリックリンクが含まれています。'
            );
        }
    }

    private static function extractZipEntries(
        ZipArchive $zip,
        string $extractDirectory
    ): void {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $originalName = $zip->getNameIndex($index);

            if ($originalName === false) {
                throw new RuntimeException(
                    '更新ZIPの展開に失敗しました。'
                );
            }

            $entryName = str_replace(
                '\\',
                '/',
                $originalName
            );

            $destination = $extractDirectory
                . '/'
                . $entryName;

            if (str_ends_with($entryName, '/')) {
                if (!is_dir($destination)) {
                    mkdir($destination, 0755, true);
                }

                continue;
            }

            $parentDirectory = dirname($destination);

            if (!is_dir($parentDirectory)) {
                mkdir($parentDirectory, 0755, true);
            }

            $input = $zip->getStream($originalName);

            if ($input === false) {
                throw new RuntimeException(
                    '更新ZIP内のファイルを読み込めません。'
                );
            }

            $output = fopen($destination, 'wb');

            if ($output === false) {
                fclose($input);

                throw new RuntimeException(
                    '更新ZIP内のファイルを保存できません。'
                );
            }

            stream_copy_to_stream($input, $output);

            fclose($input);
            fclose($output);
        }
    }

    private static function loadManifest(
        string $extractDirectory,
        string $version
    ): array {
        $manifestPath = $extractDirectory
            . '/update-manifest.json';

        if (!is_file($manifestPath)) {
            throw new RuntimeException(
                'update-manifest.jsonがありません。'
            );
        }

        $content = file_get_contents($manifestPath);
        $manifest = json_decode((string)$content, true);

        if (!is_array($manifest)) {
            throw new RuntimeException(
                '更新マニフェストの形式が正しくありません。'
            );
        }

        if (($manifest['version'] ?? '') !== $version) {
            throw new RuntimeException(
                '更新マニフェストのバージョンが一致しません。'
            );
        }

        if (
            ($manifest['package_directory'] ?? '') !==
            'package'
        ) {
            throw new RuntimeException(
                '更新ファイルの場所が正しくありません。'
            );
        }


        $minimumNexaPress = (string)(
            $manifest['minimum_nexapress'] ?? '0.0.0'
        );

        $currentVersionPath = BASE_PATH
            . '/config/version.php';

        if (!is_file($currentVersionPath)) {
            throw new RuntimeException(
                '現在のバージョン情報がありません。'
            );
        }

        $currentVersionConfig = require $currentVersionPath;

        $currentVersion = (string)(
            $currentVersionConfig['version'] ?? '0.0.0'
        );

        if (version_compare(
            $currentVersion,
            $minimumNexaPress,
            '<'
        )) {
            throw new RuntimeException(
                'この更新にはNexaPress '
                . $minimumNexaPress
                . '以上が必要です。'
            );
        }


        $minimumPhp = (string)(
            $manifest['minimum_php'] ?? '8.0.0'
        );

        if (version_compare(PHP_VERSION, $minimumPhp, '<')) {
            throw new RuntimeException(
                'この更新にはPHP '
                . $minimumPhp
                . '以上が必要です。'
            );
        }

        if (!is_dir($extractDirectory . '/package')) {
            throw new RuntimeException(
                '更新ファイル本体がありません。'
            );
        }

        return $manifest;
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
                '更新バージョンが正しくありません。'
            );
        }
    }

    private static function loadConfig(): array
    {
        $configPath = BASE_PATH . '/config/update.php';

        if (!file_exists($configPath)) {
            throw new RuntimeException(
                '更新設定ファイルがありません。'
            );
        }

        return require $configPath;
    }
}