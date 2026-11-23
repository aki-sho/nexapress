<?php

namespace app\Core;

use InvalidArgumentException;
use ParseError;
use RuntimeException;

final class ThemeEditor
{
    public const MAX_BYTES = 262144;

    public static function files(string $theme): array
    {
        $styles = [];
        foreach (Theme::stylesheetPaths($theme) as $path) {
            $name = basename($path);
            if (self::sourcePath($theme, $name) !== null) {
                $styles[] = $name;
            }
        }

        $templates = [];
        foreach (['templates', 'templates/parts'] as $directory) {
            foreach (glob(BASE_PATH . '/public/themes/' . $theme . '/' . $directory . '/*.php') ?: [] as $path) {
                $file = $directory . '/' . basename($path);
                if (self::sourcePath($theme, $file) !== null) {
                    $templates[] = $file;
                }
            }
        }

        return ['CSS' => array_values(array_unique($styles)), 'テンプレート' => $templates];
    }

    public static function childFiles(string $theme, array $groups): array
    {
        $children = [];
        foreach (array_merge(...array_values($groups)) as $file) {
            $path = self::sourcePath($theme, $file);
            if ($path !== null && self::localFile(self::editedPath($path, 'child'))) {
                $children[$file] = self::usesChild($path) ? 'child' : 'parent';
            }
        }
        return $children;
    }

    public static function read(string $theme, string $file, string $layer = 'parent'): array
    {
        $path = self::sourcePath($theme, $file);
        $original = $path === null ? null : self::text($path);
        if ($original === null) {
            throw new RuntimeException('ファイルを読み込めませんでした。');
        }

        $edited = self::text(self::editedPath($path, $layer));
        if ($layer === 'child' && $edited === null) {
            throw new InvalidArgumentException('子ファイルが見つかりませんでした。', 404);
        }
        $content = $edited ?? $original;

        return [
            'content' => $content,
            'original' => $original,
            'edited' => $edited !== null,
            'compatible' => !self::isTemplate($file) || self::samePhp($original, $content),
            'revision' => hash('sha256', $theme . "\0" . $file . "\0" . $layer . "\0" . $original . "\0" . $content),
        ];
    }

    public static function change(
        string $theme, string $file, string $layer, string $action, ?string $revision, ?string $content
    ): bool {
        $lock = @fopen(BASE_PATH . '/storage/theme-editor.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('保存用のファイルを開けませんでした。');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('保存を開始できませんでした。');
            }
            if ($theme !== Theme::active()) {
                throw new InvalidArgumentException('テーマが切り替わりました。編集画面を開き直してください。', 409);
            }
            $path = self::sourcePath($theme, $file);
            if ($path === null) {
                throw new InvalidArgumentException('テーマファイルが見つかりませんでした。', 404);
            }
            if ($action === 'create_child') {
                $child = self::editedPath($path, 'child');
                if (self::localFile($child)) {
                    return false;
                }
                $parent = self::read($theme, $file);
                self::write($child, $parent['compatible'] ? $parent['content'] : $parent['original']);
                return true;
            }

            $current = self::read($theme, $file, $layer);
            if ($revision === null || !hash_equals($current['revision'], $revision)) {
                throw new InvalidArgumentException('ファイルが更新されました。編集画面を開き直してください。', 409);
            }
            if ($action === 'use') {
                return self::selectLayer($path, $layer, $current['compatible']);
            }

            $editedPath = self::editedPath($path, $layer);
            if ($action === 'save') {
                if ($content === null || !self::validText($content)) {
                    throw new InvalidArgumentException('UTF-8のテキストを256 KB以内で入力してください。', 422);
                }
                $content = self::newlines($content);
                if (self::isTemplate($file) && !self::samePhp($current['original'], $content)) {
                    throw new InvalidArgumentException('テンプレートのPHP部分を元のコードに戻してから保存してください。', 422);
                }
            }

            if ($layer === 'parent' && ($action === 'restore' || $content === $current['original'])) {
                if (!$current['edited']) {
                    return false;
                }
                if (!@unlink($editedPath)) {
                    throw new RuntimeException('編集したファイルを元に戻せませんでした。');
                }
            } elseif ($content !== null && $content !== $current['content']) {
                self::write($editedPath, $content);
            } else {
                return false;
            }

            if (self::isTemplate($file) && function_exists('opcache_invalidate')) {
                @opcache_invalidate($editedPath, true);
            }
            return true;
        } finally {
            fclose($lock);
        }
    }

    private static function selectLayer(string $path, string $layer, bool $compatible): bool
    {
        $selection = self::selectionPath($path);
        if ($layer === 'child') {
            if (!$compatible) {
                throw new InvalidArgumentException('子ファイルに現在のテーマのPHP部分を反映してから切り替えてください。', 422);
            }
            if (self::usesChild($path)) {
                return false;
            }
            self::write($selection, 'child');
        } elseif (self::usesChild($path)) {
            if (!@unlink($selection)) {
                throw new RuntimeException('親ファイルに切り替えられませんでした。');
            }
        } else {
            return false;
        }
        return true;
    }

    public static function template(string $theme, string $file, string $originalPath): string
    {
        $parent = self::editedPath($originalPath);
        $candidate = self::usesChild($originalPath) ? self::editedPath($originalPath, 'child') : $parent;
        if ((!is_file($candidate) && !is_file($parent)) || self::sourcePath($theme, $file) === null) {
            return $originalPath;
        }
        $original = self::text($originalPath);
        if ($original !== null) {
            foreach (array_unique([$candidate, $parent]) as $path) {
                $edited = self::text($path);
                if ($edited !== null && self::samePhp($original, $edited)) {
                    return $path;
                }
            }
        }
        return $originalPath;
    }

    public static function stylesheet(string $path): string
    {
        $base = BASE_PATH . '/public/';
        if (self::usesChild($base . $path)) {
            $child = self::editedPath($path, 'child');
            if (self::localFile($base . $child)) {
                return $child;
            }
        }
        $edited = self::editedPath($path);
        return self::localFile($base . $edited) ? $edited : $path;
    }

    private static function selectionPath(string $path): string
    {
        return dirname($path) . '/.nexapress-use-child-' . basename($path);
    }

    private static function usesChild(string $path): bool
    {
        return self::localFile(self::selectionPath($path));
    }


    private static function sourcePath(string $theme, string $file): ?string
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $theme) || (
            !preg_match('/\A[a-zA-Z0-9_-][a-zA-Z0-9_.-]*\.css\z/', $file) && !self::isTemplate($file)
        )) {
            return null;
        }
        $root = realpath(BASE_PATH . '/public/themes');
        if ($root === false) {
            return null;
        }
        $path = str_replace('\\', '/', $root) . '/' . $theme . '/' . $file;

        return self::localFile($path) ? $path : null;
    }

    private static function localFile(string $path): bool
    {
        $resolved = realpath($path);
        if ($resolved === false || !is_file($resolved)) {
            return false;
        }
        $actual = str_replace('\\', '/', $resolved);
        $expected = str_replace('\\', '/', $path);

        return DIRECTORY_SEPARATOR === '\\' ? strcasecmp($actual, $expected) === 0 : $actual === $expected;
    }

    private static function isTemplate(string $file): bool
    {
        return preg_match('#\Atemplates/(?:parts/)?[a-zA-Z0-9_-]+\.php\z#', $file) === 1;
    }

    private static function editedPath(string $path, string $layer = 'parent'): string
    {
        $prefix = $layer === 'child' ? 'nexapress-child-' : 'nexapress-edit-';
        return dirname($path) . '/' . (str_ends_with($path, '.php') ? '.' : '') . $prefix . basename($path);
    }

    private static function text(string $path): ?string
    {
        if (!self::localFile($path)) {
            return null;
        }
        $text = @file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
        return is_string($text) && self::validText($text) ? self::newlines($text) : null;
    }

    private static function validText(string $text): bool
    {
        return strlen($text) <= self::MAX_BYTES && !str_contains($text, "\0") && preg_match('//u', $text) === 1;
    }

    private static function newlines(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    private static function samePhp(string $original, string $edited): bool
    {
        try {
            $before = self::phpTokens($original);
            $after = self::phpTokens($edited);
            return $before === $after;
        } catch (ParseError) {
            return false;
        }
    }

    private static function phpTokens(string $source): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $signature = [];
        $placementMatters = array_intersect([T_DECLARE, T_NAMESPACE, T_HALT_COMPILER], array_column($tokens, 0)) !== [];
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                $signature[] = $token;
            } elseif ($token[0] !== T_INLINE_HTML) {
                $signature[] = [$token[0], $token[1]];
            } elseif ($placementMatters) {
                $signature[] = [T_INLINE_HTML];
            }
        }
        return $signature;
    }

    private static function write(string $path, string $content): void
    {
        $directory = dirname($path);
        $temporary = @tempnam($directory, '.nexapress-');
        if ($temporary === false) {
            throw new RuntimeException('テーマフォルダに書き込めませんでした。');
        }
        try {
            if (realpath(dirname($temporary)) !== realpath($directory) ||
                @file_put_contents($temporary, $content) !== strlen($content) ||
                !@chmod($temporary, 0644) || !@rename($temporary, $path)) {
                throw new RuntimeException('テーマのファイルを保存できませんでした。');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
