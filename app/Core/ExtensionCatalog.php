<?php

namespace app\Core;

use RuntimeException;

class ExtensionCatalog
{
    public static function all(bool $force = false): array
    {
        $config = self::loadConfig();

        if (!$force) {
            $cached = self::loadCache(
                (int)($config['cache_ttl'] ?? 21600)
            );

            if ($cached !== null) {
                return $cached;
            }
        }

        $catalogUrl = trim(
            (string)($config['catalog_url'] ?? '')
        );

        if ($catalogUrl === '') {
            throw new RuntimeException(
                '拡張機能一覧URLが設定されていません。'
            );
        }

        $catalog = self::fetchJson(
            $catalogUrl,
            $config
        );

        $catalogItems = $catalog['extensions'] ?? $catalog;

        if (!is_array($catalogItems)) {
            throw new RuntimeException(
                '拡張機能一覧の形式が正しくありません。'
            );
        }

        $extensions = [];

        foreach ($catalogItems as $catalogItem) {
            $manifestUrl = self::manifestUrl(
                $catalogItem
            );

            if ($manifestUrl === null) {
                continue;
            }

            if (
                !self::isAllowedUrl(
                    $manifestUrl,
                    $config['allowed_manifest_hosts'] ?? []
                )
            ) {
                continue;
            }

            try {
                $manifest = self::fetchJson(
                    $manifestUrl,
                    $config
                );

                if (is_array($catalogItem)) {
                    $manifest = array_merge(
                        $manifest,
                        $catalogItem
                    );
                }

                $extension = self::normalizeManifest(
                    $manifest,
                    $manifestUrl,
                    $config
                );
            } catch (RuntimeException $exception) {
                continue;
            }

            if ($extension === null) {
                continue;
            }

            $extensions[$extension['id']] = $extension;
        }

        uasort(
            $extensions,
            function (array $a, array $b): int {
                return strcmp($a['name'], $b['name']);
            }
        );

        self::saveCache($extensions);

        return $extensions;
    }

    public static function find(
        string $extensionId,
        bool $force = false
    ): ?array {
        return self::all($force)[$extensionId] ?? null;
    }

    public static function clearCache(): void
    {
        $cachePath = self::cachePath();

        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    private static function manifestUrl(
        mixed $catalogItem
    ): ?string {
        if (is_string($catalogItem)) {
            $url = trim($catalogItem);

            return $url !== '' ? $url : null;
        }

        if (!is_array($catalogItem)) {
            return null;
        }

        $url = trim(
            (string)($catalogItem['manifest_url'] ?? '')
        );

        return $url !== '' ? $url : null;
    }

    private static function normalizeManifest(
        array $manifest,
        string $manifestUrl,
        array $config
    ): ?array {
        $id = trim((string)($manifest['id'] ?? ''));
        $name = trim((string)($manifest['name'] ?? ''));
        $description = trim(
            (string)($manifest['description'] ?? '')
        );
        $version = trim(
            (string)($manifest['version'] ?? '')
        );
        $iconUrl = trim(
            (string)($manifest['icon_url'] ?? '')
        );
        $bannerUrl = trim(
            (string)($manifest['banner_url'] ?? '')
        );
        $downloadUrl = trim(
            (string)($manifest['download_url'] ?? '')
        );

        if (
            $id === '' ||
            !preg_match('/^[a-zA-Z0-9_-]+$/', $id)
        ) {
            return null;
        }

        if (
            $name === '' ||
            $version === '' ||
            $downloadUrl === ''
        ) {
            return null;
        }

        if (
            !self::isAllowedUrl(
                $downloadUrl,
                $config['allowed_download_hosts'] ?? []
            )
        ) {
            return null;
        }

        if (
            $iconUrl !== '' &&
            !self::isAllowedUrl(
                $iconUrl,
                $config['allowed_image_hosts'] ?? []
            )
        ) {
            $iconUrl = '';
        }

        if (
            $bannerUrl !== '' &&
            !self::isAllowedUrl(
                $bannerUrl,
                $config['allowed_image_hosts'] ?? []
            )
        ) {
            $bannerUrl = '';
        }

        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'version' => $version,
            'icon_url' => $iconUrl,
            'banner_url' => $bannerUrl,
            'download_url' => $downloadUrl,
            'manifest_url' => $manifestUrl,
            'author' => trim(
                (string)($manifest['author'] ?? '')
            ),
        ];
    }

    private static function fetchJson(
        string $url,
        array $config
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'PHPのcURL拡張機能が必要です。'
            );
        }

        if (!self::isHttpsUrl($url)) {
            throw new RuntimeException(
                'HTTPS以外のURLは利用できません。'
            );
        }

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT =>
                (int)($config['timeout'] ?? 15),
            CURLOPT_TIMEOUT =>
                (int)($config['timeout'] ?? 15),
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: NexaPress-Extension-Catalog',
            ],
        ]);

        $response = curl_exec($curl);

        $statusCode = (int)curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        $error = curl_error($curl);

        curl_close($curl);

        if (
            $response === false ||
            $statusCode !== 200
        ) {
            throw new RuntimeException(
                '拡張機能情報の取得に失敗しました。'
                . $error
            );
        }

        $maxJsonSize = (int)(
            $config['max_json_size'] ?? 1048576
        );

        if (strlen($response) > $maxJsonSize) {
            throw new RuntimeException(
                '拡張機能情報のサイズが大きすぎます。'
            );
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new RuntimeException(
                '拡張機能情報のJSONが正しくありません。'
            );
        }

        return $data;
    }

    private static function isAllowedUrl(
        string $url,
        array $allowedHosts
    ): bool {
        if (!self::isHttpsUrl($url)) {
            return false;
        }

        $host = strtolower(
            (string)parse_url($url, PHP_URL_HOST)
        );

        if ($host === '') {
            return false;
        }

        foreach ($allowedHosts as $allowedHost) {
            if (
                $host === strtolower(
                    trim((string)$allowedHost)
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private static function isHttpsUrl(string $url): bool
    {
        return strtolower(
            (string)parse_url($url, PHP_URL_SCHEME)
        ) === 'https';
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

    private static function loadCache(
        int $cacheTtl
    ): ?array {
        $cachePath = self::cachePath();

        if (!is_file($cachePath)) {
            return null;
        }

        if (
            filemtime($cachePath) + $cacheTtl <
            time()
        ) {
            return null;
        }

        $content = file_get_contents($cachePath);

        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    private static function saveCache(
        array $extensions
    ): void {
        $cachePath = self::cachePath();
        $cacheDirectory = dirname($cachePath);

        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0755, true);
        }

        file_put_contents(
            $cachePath,
            json_encode(
                $extensions,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PRETTY_PRINT
            ),
            LOCK_EX
        );
    }

    private static function cachePath(): string
    {
        return BASE_PATH
            . '/storage/cache/extensions/catalog.json';
    }
}