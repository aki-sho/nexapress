<?php

namespace app\Core;

use RuntimeException;

class UpdateChecker
{
    public static function check(bool $force = false): array
    {
        $config = self::loadConfig();
        $release = null;

        if (!$force) {
            $release = self::loadCache(
                (int)($config['cache_ttl'] ?? 21600)
            );
        }

        if ($release === null) {
            $release = self::fetchLatestRelease($config);
            self::saveCache($release);
        }

        $currentVersion = self::currentVersion();
        $latestVersion = ltrim(
            (string)($release['tag_name'] ?? ''),
            'vV'
        );

        if ($latestVersion === '') {
            throw new RuntimeException(
                '最新バージョンを取得できませんでした。'
            );
        }

        return [
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,

            'update_available' => version_compare(
                $latestVersion,
                $currentVersion,
                '>'
            ),

            'release_name' => $release['name'] ?? '',
            'release_url' => $release['html_url'] ?? '',
            'release_notes' => $release['body'] ?? '',
            'published_at' => $release['published_at'] ?? '',
            'asset' => self::findUpdateAsset(
                $release,
                $latestVersion
            ),
        ];
    }

    private static function currentVersion(): string
    {
        $versionPath = BASE_PATH . '/config/version.php';

        if (!file_exists($versionPath)) {
            return '0.0.0';
        }

        $config = require $versionPath;

        return (string)($config['version'] ?? '0.0.0');
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

    private static function fetchLatestRelease(array $config): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'PHPのcURL拡張機能が必要です。'
            );
        }

        $repository = $config['repository'] ?? '';

        if ($repository === '') {
            throw new RuntimeException(
                '更新元リポジトリが設定されていません。'
            );
        }

        $apiUrl = 'https://api.github.com/repos/'
            . $repository
            . '/releases/latest';

        $curl = curl_init($apiUrl);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT =>
                (int)($config['timeout'] ?? 15),
            CURLOPT_TIMEOUT =>
                (int)($config['timeout'] ?? 15),
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'User-Agent: NexaPress-Updater',
            ],
        ]);

        $response = curl_exec($curl);
        $statusCode = curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );
        $error = curl_error($curl);

        curl_close($curl);

        if ($response === false || $statusCode !== 200) {
            throw new RuntimeException(
                '更新情報の取得に失敗しました。' . $error
            );
        }

        $release = json_decode($response, true);

        if (!is_array($release)) {
            throw new RuntimeException(
                '更新情報の形式が正しくありません。'
            );
        }

        return $release;
    }

    private static function findUpdateAsset(
        array $release,
        string $version
    ): ?array {
        $expectedName = 'nexapress-update-'
            . $version
            . '.zip';

        foreach ($release['assets'] ?? [] as $asset) {
            if (($asset['name'] ?? '') !== $expectedName) {
                continue;
            }

            return [
                'name' => $asset['name'],
                'download_url' =>
                    $asset['browser_download_url'] ?? '',
                'size' => $asset['size'] ?? 0,
                'digest' => $asset['digest'] ?? '',
            ];
        }

        return null;
    }

    private static function loadCache(int $cacheTtl): ?array
    {
        $cachePath = self::cachePath();

        if (!file_exists($cachePath)) {
            return null;
        }

        if (filemtime($cachePath) + $cacheTtl < time()) {
            return null;
        }

        $content = file_get_contents($cachePath);
        $data = json_decode((string)$content, true);

        return is_array($data) ? $data : null;
    }

    private static function saveCache(array $release): void
    {
        $cacheDirectory = dirname(self::cachePath());

        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0755, true);
        }

        file_put_contents(
            self::cachePath(),
            json_encode(
                $release,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PRETTY_PRINT
            )
        );
    }

    private static function cachePath(): string
    {
        return BASE_PATH
            . '/storage/cache/updates/latest-release.json';
    }
}