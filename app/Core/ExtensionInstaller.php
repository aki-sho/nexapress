<?php

namespace app\Core;

use RuntimeException;
use ZipArchive;

class ExtensionInstaller
{
    public static function install(array $extension): array
    {
        $config = self::loadConfig();

        $extensionId = trim(
            (string)($extension['id'] ?? '')
        );

        $downloadUrl = trim(
            (string)($extension['download_url'] ?? '')
        );

        if (
            $extensionId === '' ||
            !preg_match(
                '/^[a-zA-Z0-9_-]+$/',
                $extensionId
            )
        ) {
            throw new RuntimeException(
                '拡張機能IDが正しくありません。'
            );
        }

        if (Extension::find($extensionId) !== null) {
            throw new RuntimeException(
                'この拡張機能はインストール済みです。'
            );
        }

        if (
            !self::isAllowedUrl(
                $downloadUrl,
                $config['allowed_download_hosts'] ?? []
            )
        ) {
            throw new RuntimeException(
                '拡張機能ZIPのURLが許可されていません。'
            );
        }

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'PHPのZip拡張機能が必要です。'
            );
        }

        $cacheDirectory = BASE_PATH
            . '/storage/cache/extensions';

        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0755, true);
        }

        $token = bin2hex(random_bytes(8));

        $packagePath = $cacheDirectory
            . '/package-'
            . $token
            . '.zip';

        $temporaryDirectory = $cacheDirectory
            . '/install-'
            . $token;

        try {
            self::downloadPackage(
                $downloadUrl,
                $packagePath,
                $config
            );

            $package = self::inspectPackage(
                $packagePath,
                $config
            );

            self::extractPackage(
                $packagePath,
                $temporaryDirectory,
                $package['entries']
            );

            $temporaryExtensionDirectory =
                $temporaryDirectory
                . '/'
                . $package['root_folder'];

            $manifest = self::readManifest(
                $temporaryExtensionDirectory
            );

            if (
                (string)($manifest['id'] ?? '') !==
                $extensionId
            ) {
                throw new RuntimeException(
                    'ZIP内の拡張機能IDが一致しません。'
                );
            }

            $catalogVersion = trim(
                (string)($extension['version'] ?? '')
            );

            $packageVersion = trim(
                (string)($manifest['version'] ?? '')
            );

            if (
                $catalogVersion !== '' &&
                $packageVersion !== $catalogVersion
            ) {
                throw new RuntimeException(
                    'ZIP内のバージョンが一致しません。'
                );
            }

            $extensionsDirectory = BASE_PATH
                . '/extensions';

            if (!is_dir($extensionsDirectory)) {
                mkdir(
                    $extensionsDirectory,
                    0755,
                    true
                );
            }

            $targetDirectory = $extensionsDirectory
                . '/'
                . $package['root_folder'];

            if (file_exists($targetDirectory)) {
                throw new RuntimeException(
                    '同名の拡張機能フォルダが存在します。'
                );
            }

            if (
                !rename(
                    $temporaryExtensionDirectory,
                    $targetDirectory
                )
            ) {
                throw new RuntimeException(
                    '拡張機能を配置できませんでした。'
                );
            }

            return [
                'id' => $extensionId,
                'folder' => $package['root_folder'],
                'version' => $packageVersion,
            ];
        } finally {
            if (is_file($packagePath)) {
                unlink($packagePath);
            }

            self::removeDirectory(
                $temporaryDirectory
            );
        }
    }

    private static function downloadPackage(
        string $url,
        string $destination,
        array $config
    ): void {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'PHPのcURL拡張機能が必要です。'
            );
        }

        $output = fopen($destination, 'wb');

        if ($output === false) {
            throw new RuntimeException(
                'ZIPの一時ファイルを作成できません。'
            );
        }

        $maxPackageSize = (int)(
            $config['max_package_size']
            ?? 52428800
        );

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_FILE => $output,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,

            CURLOPT_CONNECTTIMEOUT =>
                (int)($config['timeout'] ?? 15),

            CURLOPT_TIMEOUT =>
                (int)(
                    $config['download_timeout']
                    ?? 300
                ),

            CURLOPT_HTTPHEADER => [
                'Accept: application/octet-stream',
                'User-Agent: NexaPress-Extension-Installer',
            ],

            CURLOPT_NOPROGRESS => false,

            CURLOPT_PROGRESSFUNCTION =>
                static function (
                    $resource,
                    float $downloadSize,
                    float $downloaded,
                    float $uploadSize,
                    float $uploaded
                ) use ($maxPackageSize): int {
                    if (
                        $downloadSize >
                        $maxPackageSize ||
                        $downloaded >
                        $maxPackageSize
                    ) {
                        return 1;
                    }

                    return 0;
                },
        ]);

        $result = curl_exec($curl);

        $statusCode = (int)curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        $error = curl_error($curl);

        curl_close($curl);
        fclose($output);

        if (
            $result !== true ||
            $statusCode < 200 ||
            $statusCode >= 300
        ) {
            throw new RuntimeException(
                '拡張機能ZIPの取得に失敗しました。'
                . $error
            );
        }

        if (
            !is_file($destination) ||
            filesize($destination) === 0
        ) {
            throw new RuntimeException(
                '取得したZIPが空です。'
            );
        }

        if (
            filesize($destination) >
            $maxPackageSize
        ) {
            throw new RuntimeException(
                '拡張機能ZIPのサイズが大きすぎます。'
            );
        }
    }

    private static function inspectPackage(
        string $packagePath,
        array $config
    ): array {
        $zip = new ZipArchive();

        if ($zip->open($packagePath) !== true) {
            throw new RuntimeException(
                '拡張機能ZIPを開けませんでした。'
            );
        }

        $maxFiles = (int)(
            $config['max_package_files']
            ?? 2000
        );

        $maxExtractedSize = (int)(
            $config['max_extracted_size']
            ?? 104857600
        );

        if ($zip->numFiles > $maxFiles) {
            $zip->close();

            throw new RuntimeException(
                'ZIP内のファイル数が多すぎます。'
            );
        }

        $rootFolder = null;
        $hasManifest = false;
        $totalSize = 0;
        $entries = [];
        $seenPaths = [];

        for (
            $index = 0;
            $index < $zip->numFiles;
            $index++
        ) {
            $stat = $zip->statIndex($index);

            if (!is_array($stat)) {
                $zip->close();

                throw new RuntimeException(
                    'ZIP内の情報を読み取れません。'
                );
            }

            $originalName = (string)(
                $stat['name'] ?? ''
            );

            $entryName = str_replace(
                '\\',
                '/',
                $originalName
            );

            if (
                !self::isSafeEntryName(
                    $entryName
                )
            ) {
                $zip->close();

                throw new RuntimeException(
                    'ZIP内に不正なパスがあります。'
                );
            }

            $trimmedName = trim(
                $entryName,
                '/'
            );

            if ($trimmedName === '') {
                continue;
            }

            $pathKey = strtolower(
                $trimmedName
            );

            if (isset($seenPaths[$pathKey])) {
                $zip->close();

                throw new RuntimeException(
                    'ZIP内に重複したパスがあります。'
                );
            }

            $seenPaths[$pathKey] = true;

            $parts = explode(
                '/',
                $trimmedName
            );

            $currentRoot = $parts[0];

            if (
                !preg_match(
                    '/^[a-zA-Z0-9_-]+$/',
                    $currentRoot
                )
            ) {
                $zip->close();

                throw new RuntimeException(
                    'ZIPのルートフォルダ名が正しくありません。'
                );
            }

            if ($rootFolder === null) {
                $rootFolder = $currentRoot;
            }

            if (
                $rootFolder !==
                $currentRoot
            ) {
                $zip->close();

                throw new RuntimeException(
                    'ZIPには1つのルートフォルダだけを含めてください。'
                );
            }

            if (
                self::isSymlink(
                    $zip,
                    $index
                )
            ) {
                $zip->close();

                throw new RuntimeException(
                    'ZIPにシンボリックリンクは含められません。'
                );
            }

            $isDirectory = str_ends_with(
                $entryName,
                '/'
            );

            if (!$isDirectory) {
                $totalSize += (int)(
                    $stat['size'] ?? 0
                );

                if (
                    $totalSize >
                    $maxExtractedSize
                ) {
                    $zip->close();

                    throw new RuntimeException(
                        'ZIP展開後のサイズが大きすぎます。'
                    );
                }
            }

            if (
                $trimmedName ===
                $rootFolder
                . '/manifest.json'
            ) {
                $hasManifest = true;
            }

            $entries[] = [
                'original' => $originalName,
                'path' => $trimmedName,
                'directory' => $isDirectory,
            ];
        }

        $zip->close();

        if (
            $rootFolder === null ||
            !$hasManifest
        ) {
            throw new RuntimeException(
                'ZIP内にmanifest.jsonがありません。'
            );
        }

        return [
            'root_folder' => $rootFolder,
            'entries' => $entries,
        ];
    }

    private static function extractPackage(
        string $packagePath,
        string $temporaryDirectory,
        array $entries
    ): void {
        if (!is_dir($temporaryDirectory)) {
            mkdir(
                $temporaryDirectory,
                0755,
                true
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($packagePath) !== true) {
            throw new RuntimeException(
                '拡張機能ZIPを開けませんでした。'
            );
        }

        try {
            foreach ($entries as $entry) {
                $destination =
                    $temporaryDirectory
                    . '/'
                    . $entry['path'];

                if ($entry['directory']) {
                    if (!is_dir($destination)) {
                        mkdir(
                            $destination,
                            0755,
                            true
                        );
                    }

                    continue;
                }

                $parentDirectory = dirname(
                    $destination
                );

                if (
                    !is_dir(
                        $parentDirectory
                    )
                ) {
                    mkdir(
                        $parentDirectory,
                        0755,
                        true
                    );
                }

                $input = $zip->getStream(
                    $entry['original']
                );

                if ($input === false) {
                    throw new RuntimeException(
                        'ZIP内のファイルを読み込めません。'
                    );
                }

                $output = fopen(
                    $destination,
                    'wb'
                );

                if ($output === false) {
                    fclose($input);

                    throw new RuntimeException(
                        'ZIP内のファイルを書き込めません。'
                    );
                }

                stream_copy_to_stream(
                    $input,
                    $output
                );

                fclose($input);
                fclose($output);
            }
        } finally {
            $zip->close();
        }
    }

    private static function readManifest(
        string $extensionDirectory
    ): array {
        $manifestPath =
            $extensionDirectory
            . '/manifest.json';

        if (!is_file($manifestPath)) {
            throw new RuntimeException(
                'manifest.jsonが見つかりません。'
            );
        }

        $content = file_get_contents(
            $manifestPath
        );

        if ($content === false) {
            throw new RuntimeException(
                'manifest.jsonを読み込めません。'
            );
        }

        $manifest = json_decode(
            $content,
            true
        );

        if (!is_array($manifest)) {
            throw new RuntimeException(
                'manifest.jsonの形式が正しくありません。'
            );
        }

        return $manifest;
    }

    private static function isSafeEntryName(
        string $entryName
    ): bool {
        if (
            $entryName === '' ||
            str_contains($entryName, "\0") ||
            str_contains($entryName, ':') ||
            str_starts_with($entryName, '/') ||
            preg_match(
                '/^[a-zA-Z]:\//',
                $entryName
            ) ||
            preg_match(
                '#(^|/)\.\.(/|$)#',
                $entryName
            )
        ) {
            return false;
        }

        return true;
    }

    private static function isSymlink(
        ZipArchive $zip,
        int $index
    ): bool {
        $operatingSystem = 0;
        $attributes = 0;

        if (
            !$zip->getExternalAttributesIndex(
                $index,
                $operatingSystem,
                $attributes
            )
        ) {
            return false;
        }

        $fileType =
            ($attributes >> 16)
            & 0xF000;

        return $fileType === 0xA000;
    }

    private static function isAllowedUrl(
        string $url,
        array $allowedHosts
    ): bool {
        if (
            strtolower(
                (string)parse_url(
                    $url,
                    PHP_URL_SCHEME
                )
            ) !== 'https'
        ) {
            return false;
        }

        $host = strtolower(
            (string)parse_url(
                $url,
                PHP_URL_HOST
            )
        );

        foreach (
            $allowedHosts
            as $allowedHost
        ) {
            if (
                $host === strtolower(
                    trim(
                        (string)$allowedHost
                    )
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private static function loadConfig(): array
    {
        $configPath = BASE_PATH
            . '/config/extensions.php';

        if (!is_file($configPath)) {
            throw new RuntimeException(
                '拡張機能設定ファイルがありません。'
            );
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new RuntimeException(
                '拡張機能設定が正しくありません。'
            );
        }

        return $config;
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
            if (
                $item === '.' ||
                $item === '..'
            ) {
                continue;
            }

            $path = $directory
                . '/'
                . $item;

            if (
                is_dir($path) &&
                !is_link($path)
            ) {
                self::removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}