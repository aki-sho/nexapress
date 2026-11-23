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

    public static function read(string $theme, string $file): array
    {
        $path = self::sourcePath($theme, $file);
        $original = $path === null ? null : self::text($path);
        if ($original === null) {
            throw new RuntimeException('ファイルを読み込めませんでした。');
        }

        $edited = self::text(self::editedPath($path));
        $content = $edited ?? $original;

        return [
            'content' => $content,
            'original' => $original,
            'edited' => $edited !== null,
            'compatible' => !self::isTemplate($file) || self::samePhp($original, $content),
            'revision' => hash('sha256', $theme . "\0" . $file . "\0" . $original . "\0" . $content),
        ];
    }

    public static function save(string $theme, string $file, string $revision, ?string $content): bool
    {
        $lock = @fopen(BASE_PATH . '/storage/theme-editor.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('保存用のファイルを開けませんでした。');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('保存を開始できませんでした。');
            }
            $current = self::read($theme, $file);
            if ($theme !== Theme::active() || !hash_equals($current['revision'], $revision)) {
                throw new InvalidArgumentException('テーマまたはファイルが更新されました。編集画面を開き直してください。', 409);
            }
            $path = self::sourcePath($theme, $file);
            if ($path === null) {
                throw new RuntimeException('ファイルが見つかりませんでした。');
            }
            $editedPath = self::editedPath($path);
            if ($content !== null) {
                if (!self::validText($content)) {
                    throw new InvalidArgumentException('UTF-8のテキストを256 KB以内で入力してください。', 422);
                }
                $content = self::newlines($content);
                if (self::isTemplate($file) && !self::samePhp($current['original'], $content)) {
                    throw new InvalidArgumentException('テンプレートのPHP部分を元のコードに戻してから保存してください。', 422);
                }
            }

            if ($content === null || $content === $current['original']) {
                if (!$current['edited']) {
                    return false;
                }
                if (!@unlink($editedPath)) {
                    throw new RuntimeException('編集したファイルを元に戻せませんでした。');
                }
            } elseif ($content === $current['content']) {
                return false;
            } else {
                self::write($editedPath, $content);
            }

            if (self::isTemplate($file) && function_exists('opcache_invalidate')) {
                @opcache_invalidate($editedPath, true);
            }
            return true;
        } finally {
            fclose($lock);
        }
    }

    public static function template(string $theme, string $file, string $originalPath): string
    {
        $editedPath = self::editedPath($originalPath);
        if (!is_file($editedPath) || self::sourcePath($theme, $file) === null) {
            return $originalPath;
        }
        $edited = self::text($editedPath);
        $original = self::text($originalPath);

        return $edited !== null && $original !== null && self::samePhp($original, $edited)
            ? $editedPath : $originalPath;
    }

    public static function stylesheet(string $path): string
    {
        $edited = self::editedPath($path);
        if (self::localFile(BASE_PATH . '/public/' . $edited)) {
            return $edited;
        }
        return $path;
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

    private static function editedPath(string $path): string
    {
        $prefix = str_ends_with($path, '.php') ? '.nexapress-edit-' : 'nexapress-edit-';
        return dirname($path) . '/' . $prefix . basename($path);
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
