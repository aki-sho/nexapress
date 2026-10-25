<?php

namespace app\Core;

use app\Models\ExtensionSetting;

class Extension
{
    public static function all(): array
    {
        $extensions = [];
        $baseDir = BASE_PATH . '/extensions';

        if (!is_dir($baseDir)) {
            return [];
        }

        $directories = glob($baseDir . '/*', GLOB_ONLYDIR);

        if (!$directories) {
            return [];
        }

        foreach ($directories as $directory) {
            $manifestFile = $directory . '/manifest.json';

            if (!is_file($manifestFile)) {
                continue;
            }

            $json = file_get_contents($manifestFile);

            if ($json === false) {
                continue;
            }

            $data = json_decode($json, true);

            if (!is_array($data)) {
                continue;
            }

            $folderName = basename($directory);
            $extensionKey = (string)($data['id'] ?? $folderName);

            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $extensionKey)) {
                continue;
            }

            $name = (string)($data['name'] ?? $extensionKey);
            $admin = is_array($data['admin'] ?? null)
                ? $data['admin']
                : [];

            $bootstrapFile = self::resolveFile(
                $directory,
                (string)($data['bootstrap'] ?? '')
            );

            $dashboardFile = self::resolveFile(
                $directory,
                (string)($admin['dashboard'] ?? '')
            );

            $extensions[$extensionKey] = [
                'key' => $extensionKey,
                'folder' => $folderName,
                'name' => $name,
                'description' => (string)($data['description'] ?? ''),
                'requires' => is_array($data['requires'] ?? null) ? $data['requires'] : [],
                'compatibility_issues' => ExtensionCompatibility::issues($data),
                'version' => (string)($data['version'] ?? '1.0.0'),

                'bootstrap_file' => $bootstrapFile,
                'has_bootstrap' => $bootstrapFile !== null,

                'admin_menu_label' => (string)($admin['menu_label'] ?? $name),
                'dashboard_file' => $dashboardFile,
                'has_dashboard' => $dashboardFile !== null,

                'is_enabled' => ExtensionSetting::isEnabled($extensionKey),
            ];
        }

        uasort($extensions, function (array $a, array $b): int {
            return strcmp($a['name'], $b['name']);
        });

        return $extensions;
    }

    public static function find(string $extensionKey): ?array
    {
        return self::all()[$extensionKey] ?? null;
    }

    public static function enabled(): array
    {
        return array_filter(
            self::all(),
            function (array $extension): bool {
                return $extension['is_enabled'] === true;
            }
        );
    }

    public static function bootEnabled(Router $router): void
    {
        foreach (self::enabled() as $extension) {
            if (!empty($extension['compatibility_issues'])) {
                continue;
            }
            if (!$extension['has_bootstrap']) {
                continue;
            }

            $extensionInfo = $extension;

            require_once $extension['bootstrap_file'];
        }

        // Older Nitro Cache versions watch known URLs. New core operations also
        // emit events so their changes are visible immediately with that release.
        $cache = '\\NxNitroCache\\CacheManager';
        if (class_exists($cache, false) && is_callable([$cache, 'clear'])) {
            foreach (['navigation_saved', 'page_saved', 'page_trashed', 'page_restored',
                'page_deleted', 'site_settings_saved', 'profile_saved', 'extension_updated'] as $event) {
                Hook::addAction($event, static function () use ($cache): void { $cache::clear(); });
            }
        }
    }

    private static function resolveFile(
        string $extensionDirectory,
        string $relativePath
    ): ?string {
        $relativePath = trim($relativePath);

        if ($relativePath === '') {
            return null;
        }

        $basePath = realpath($extensionDirectory);
        $filePath = realpath(
            $extensionDirectory . '/' . ltrim($relativePath, '/\\')
        );

        if (
            $basePath === false ||
            $filePath === false ||
            !is_file($filePath)
        ) {
            return null;
        }

        if (!str_starts_with(
            $filePath,
            $basePath . DIRECTORY_SEPARATOR
        )) {
            return null;
        }

        return $filePath;
    }
}
