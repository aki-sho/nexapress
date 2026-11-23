<?php

namespace app\Core;

use app\Models\Media;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MediaOptimization
{
    public static function available(): bool
    {
        return extension_loaded('zlib');
    }

    /** @return array{bytes: int, saved: int} */
    public static function optimize(int $id): array
    {
        if (!self::available()) {
            throw new InvalidArgumentException('PNGの軽量化にはサーバーのzlibが必要です。');
        }
        $media = Media::find($id);
        $path = self::imagePath($media);
        $original = @file_get_contents($path, false, null, 0, MediaZipImport::MAX_IMAGE_BYTES + 1);
        if ($original === false) {
            throw new RuntimeException('Could not read the media file.');
        }
        $originalSize = strlen($original);
        $dimensions = $originalSize <= MediaZipImport::MAX_IMAGE_BYTES ? @getimagesizefromstring($original) : false;
        if ($dimensions === false || $dimensions['mime'] !== 'image/png' || min($dimensions[0], $dimensions[1]) < 1 ||
            max($dimensions[0], $dimensions[1]) > MediaZipImport::MAX_DIMENSION) {
            throw new InvalidArgumentException('16 MB以下・各辺4,096 px以下のPNGを選んでください。');
        }
        try {
            $output = MediaZipImage::optimizePng($original, $dimensions[0], $dimensions[1]);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException('このPNGは画質を保ったまま軽量化できませんでした。', 0, $exception);
        }
        $saved = max(0, $originalSize - strlen($output));
        if ($saved > 0 || (int)$media['file_size'] !== $originalSize) {
            self::save($id, $path, $original, $output, $saved);
        }
        return ['bytes' => $originalSize - $saved, 'saved' => $saved];
    }

    private static function imagePath(?array $media): string
    {
        if (($media['file_type'] ?? null) !== 'image' || ($media['mime_type'] ?? null) !== 'image/png') {
            throw new InvalidArgumentException('軽量化するPNG画像が見つかりません。');
        }
        $root = realpath(BASE_PATH . '/public/uploads');
        $candidate = BASE_PATH . '/public/' . ltrim($media['file_path'], '/');
        $path = realpath($candidate);
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) ||
            is_link($candidate) || !is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'png') {
            throw new InvalidArgumentException('軽量化する画像ファイルが見つかりません。');
        }
        return $path;
    }

    private static function save(int $id, string $path, string $original, string $output, int $saved): void
    {
        $pdo = Database::connect();
        $replacement = $backup = null;
        $replaced = false;
        $pdo->beginTransaction();
        try {
            clearstatcache(true);
            if (self::imagePath(Media::find($id, forUpdate: true)) !== $path ||
                @filesize($path) !== strlen($original) || @hash_file('sha256', $path) !== hash('sha256', $original)) {
                throw new InvalidArgumentException('画像が変更されました。メディア一覧を再読み込みしてください。');
            }
            if ($saved > 0) {
                $mode = @fileperms($path);
                if ($mode === false) {
                    throw new RuntimeException('Could not read the media file permissions.');
                }
                $replacement = self::stage(dirname($path), $output, $mode);
                $backup = self::stage(dirname($path), $original, $mode);
            }
            Media::updateFileSize($id, strlen($original) - $saved);
            if ($replacement !== null) {
                if (!@rename($replacement, $path)) {
                    throw new RuntimeException('Could not replace the media file.');
                }
                $replaced = true;
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($replaced && !@rename($backup, $path)) {
                // Keep the original in the protected staging file for recovery.
                $backup = null;
            }
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        } finally {
            if ($replacement !== null && !$replaced) {
                @unlink($replacement);
            }
            if ($backup !== null && is_file($backup)) {
                @unlink($backup);
            }
        }
    }

    private static function stage(string $directory, string $data, int $mode): string
    {
        // A hidden file on the same filesystem allows an atomic rename over the public image.
        $path = @tempnam($directory, '.nx-image-');
        if ($path === false || dirname($path) !== $directory ||
            @file_put_contents($path, $data) !== strlen($data) || !@chmod($path, $mode & 0666)) {
            if ($path !== false) {
                @unlink($path);
            }
            throw new RuntimeException('Could not stage the media file.');
        }
        return $path;
    }
}
