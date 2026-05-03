<?php

namespace app\Core;

class Theme
{
    public static function active(): string
    {
        $configPath = BASE_PATH . '/config/theme.php';

        if (!file_exists($configPath)) {
            return 'default';
        }

        $config = require $configPath;
        $theme = $config['active_theme'] ?? 'default';

        if (!self::exists($theme)) {
            return 'default';
        }

        return $theme;
    }

    public static function exists(string $theme): bool
    {
        $theme = self::sanitize($theme);

        if ($theme === '') {
            return false;
        }

        return is_dir(BASE_PATH . '/public/themes/' . $theme)
            && file_exists(BASE_PATH . '/public/themes/' . $theme . '/style.css');
    }

    public static function all(): array
    {
        $themesPath = BASE_PATH . '/public/themes';

        if (!is_dir($themesPath)) {
            return [];
        }

        $items = scandir($themesPath);
        $themes = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $themePath = $themesPath . '/' . $item;

            if (!is_dir($themePath)) {
                continue;
            }

            if (!file_exists($themePath . '/style.css')) {
                continue;
            }

            $name = $item;
            $description = '';

            if (file_exists($themePath . '/theme.php')) {
                $info = require $themePath . '/theme.php';
                $name = $info['name'] ?? $item;
                $description = $info['description'] ?? '';
            }

            $themes[] = [
                'id' => $item,
                'name' => $name,
                'description' => $description,
            ];
        }

        return $themes;
    }

    public static function set(string $theme): bool
    {
        $theme = self::sanitize($theme);

        if (!self::exists($theme)) {
            return false;
        }

        $configPath = BASE_PATH . '/config/theme.php';

        $content = "<?php\n\nreturn [\n    'active_theme' => '" . $theme . "',\n];\n";

        return file_put_contents($configPath, $content) !== false;
    }

    public static function template(string $name): ?string
    {
        $theme = self::active();

        $name = trim($name, '/');

        $path = BASE_PATH . '/public/themes/' . $theme . '/templates/' . $name . '.php';

        if (file_exists($path)) {
            return $path;
        }

        return null;
    }

    public static function part(string $name): ?string
    {
        $theme = self::active();

        $name = trim($name, '/');

        $path = BASE_PATH . '/public/themes/' . $theme . '/templates/parts/' . $name . '.php';

        if (file_exists($path)) {
            return $path;
        }

        return null;
    }

    private static function sanitize(string $theme): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $theme);
    }
}