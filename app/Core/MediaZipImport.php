<?php

namespace app\Core;

use app\Models\Media;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZipArchive;

final class MediaZipImport
{
    public const MAX_ARCHIVE_BYTES = 26214400;
    public const MAX_IMAGE_BYTES = 16777216;
    public const MAX_TOTAL_BYTES = 67108864;
    public const MAX_IMAGES = 200;
    public const MAX_DIMENSION = 4096;

    private const MAX_ENTRIES = self::MAX_IMAGES * 2;
    private const MAX_DIRECTORY_BYTES = 262144;
    private const MAX_TOTAL_PIXELS = 67108864;
    private const INVALID_ZIP = 'ZIPを読み込めませんでした。ファイルを確認してください。';
    private const MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    public static function available(): bool
    {
        return class_exists(ZipArchive::class) && defined('ZipArchive::RDONLY') &&
            class_exists(\finfo::class) && extension_loaded('gd') && extension_loaded('zlib');
    }

    /** @return array{imported: int, skipped: int, optimized: int, folders: int} */
    public static function import(string $path, int $userId, ?int $folderId): array
    {
        if (!self::available()) {
            throw new InvalidArgumentException('ZIPの取り込みにはZipArchive、Fileinfo、GD、zlibが必要です。');
        }
        $size = @filesize($path);
        if ($size === false || $size < 1 || $size > self::MAX_ARCHIVE_BYTES) {
            throw new InvalidArgumentException('25 MB以下のZIPを選んでください。');
        }
        self::privatePath($path);
        $expectedEntries = self::preflight($path, $size);

        $zip = new ZipArchive();
        if (@$zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new InvalidArgumentException(self::INVALID_ZIP);
        }

        $images = [];
        try {
            if ($zip->numFiles !== $expectedEntries) {
                throw new InvalidArgumentException(self::INVALID_ZIP);
            }
            $archive = self::inspect($zip);
            $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
            $pixelsRemaining = self::MAX_TOTAL_PIXELS;
            $optimized = 0;
            foreach ($archive['entries'] as $entry) {
                $image = self::prepareImage($zip, $entry, $fileInfo, $pixelsRemaining);
                $images[] = $image;
                if ($image['bytes'] < $entry['size']) {
                    $optimized++;
                }
            }
            $folders = self::register($images, $userId, $folderId);
            return [
                'imported' => count($images), 'skipped' => $archive['skipped'],
                'optimized' => $optimized, 'folders' => $folders,
            ];
        } finally {
            $zip->close();
            foreach ($images as $image) {
                fclose($image['stream']);
            }
        }
    }

    private static function preflight(string $path, int $size): int
    {
        // Bound the central directory before asking libzip to allocate its entry table.
        $tailSize = min($size, 22 + 65535);
        $tail = @file_get_contents($path, false, null, $size - $tailSize, $tailSize);
        $end = $tail === false ? false : strrpos($tail, "PK\x05\x06");
        if ($end === false || $end + 22 > strlen($tail)) {
            throw new InvalidArgumentException(self::INVALID_ZIP);
        }
        $directory = unpack('vdisk/vstartDisk/vonDisk/vcount/Vlength/Voffset/vcommentLength', substr($tail, $end + 4, 18));
        if ($directory['count'] > self::MAX_ENTRIES || $directory['length'] > self::MAX_DIRECTORY_BYTES) {
            throw new InvalidArgumentException('ZIP内の項目数または目録の容量が上限を超えています。');
        }
        if ($directory['disk'] !== 0 || $directory['startDisk'] !== 0 ||
            $directory['onDisk'] !== $directory['count'] || $directory['offset'] < 0 || $directory['length'] < 0 ||
            $directory['offset'] + $directory['length'] !== $size - $tailSize + $end ||
            $end + 22 + $directory['commentLength'] !== strlen($tail) ||
            ($directory['count'] > 0 && @file_get_contents($path, false, null, 0, 4) !== "PK\x03\x04")) {
            throw new InvalidArgumentException(self::INVALID_ZIP);
        }
        return $directory['count'];
    }

    private static function inspect(ZipArchive $zip): array
    {
        $entries = [];
        $seen = [];
        $skipped = 0;
        $remaining = self::MAX_TOTAL_BYTES;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if ($stat === false || !is_int($stat['size'] ?? null) || $stat['size'] < 0 ||
                !is_int($stat['crc'] ?? null) || ($stat['encryption_method'] ?? null) !== ZipArchive::EM_NONE ||
                !in_array($stat['comp_method'] ?? null, [ZipArchive::CM_STORE, ZipArchive::CM_DEFLATE], true)) {
                throw new InvalidArgumentException('ZIPの圧縮形式または暗号化の設定を確認してください。');
            }
            $name = $stat['name'];
            $directory = str_ends_with($name, '/');
            $basename = self::entryBasename($name, $directory);
            $key = strtolower(rtrim($name, '/'));
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('ZIP内に重複した名前があります。');
            }
            $seen[$key] = true;

            $operatingSystem = $attributes = 0;
            if (!$zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
                throw new InvalidArgumentException('ZIP内のファイル属性を確認できませんでした。');
            }
            $type = ($attributes >> 16) & 0170000;
            if ($type !== 0 && $type !== ($directory ? 0040000 : 0100000)) {
                throw new InvalidArgumentException('ZIP内のリンクや特殊ファイルは取り込めません。');
            }
            if ($directory) {
                if ($stat['size'] !== 0) {
                    throw new InvalidArgumentException('ZIP内のフォルダ項目が壊れています。');
                }
                continue;
            }
            $dot = strrpos($basename, '.');
            $extension = $dot === false ? '' : strtolower(substr($basename, $dot + 1));
            $extension = $extension === 'jpeg' ? 'jpg' : $extension;
            if (!isset(self::MIME_TYPES[$extension])) {
                $skipped++;
                continue;
            }
            if (count($entries) >= self::MAX_IMAGES) {
                throw new InvalidArgumentException('ZIPから一度に登録できる画像は' . self::MAX_IMAGES . '枚までです。');
            }
            if ($stat['size'] < 1 || $stat['size'] > self::MAX_IMAGE_BYTES || $stat['size'] > $remaining) {
                throw new InvalidArgumentException('ZIP内の画像の容量または展開後の合計容量が上限を超えています。');
            }
            $remaining -= $stat['size'];
            $slash = strrpos($name, '/');
            $folder = $slash === false ? null : substr($name, 0, $slash);
            if ($folder !== null && (nx_text_length($folder) > Media::MAX_FOLDER_NAME_LENGTH ||
                in_array($folder, Media::RESERVED_FOLDER_NAMES, true))) {
                throw new InvalidArgumentException('ZIP内のフォルダ名には、100文字以内で「すべて」「未分類」以外の名前を使ってください。');
            }
            $entries[] = $stat + [
                'originalName' => $basename,
                'title' => substr($basename, 0, $dot),
                'extension' => $extension,
                'folder' => $folder,
            ];
        }
        if (!$entries) {
            $message = 'ZIPの中に取り込める画像がありません。';
            if ($skipped > 0) {
                $message .= $skipped . '件の対応外ファイルを除外しました。';
            }
            throw new InvalidArgumentException($message);
        }
        return ['entries' => $entries, 'skipped' => $skipped];
    }

    private static function entryBasename(string $name, bool $directory): string
    {
        $parts = explode('/', $directory ? substr($name, 0, -1) : $name);
        if (strlen($name) > 1024 || count($parts) > 12 || str_contains($name, '\\') ||
            str_contains($name, ':') || preg_match('/\p{C}/u', $name) !== 0) {
            throw new InvalidArgumentException('ZIP内に使用できないパスがあります。');
        }
        foreach ($parts as $part) {
            if ($part === '' || str_starts_with($part, '.') || $part !== rtrim($part, ' .') ||
                nx_text_length($part) > 255) {
                throw new InvalidArgumentException('ZIP内に使用できないパスがあります。');
            }
        }
        return $parts[array_key_last($parts)];
    }

    private static function prepareImage(
        ZipArchive $zip, array $entry, \finfo $fileInfo, int &$pixelsRemaining
    ): array {
        $source = self::temporaryFile();
        $output = null;
        try {
            self::readImage($zip, $entry, $source);
            $sourcePath = stream_get_meta_data($source)['uri'];
            $mime = self::MIME_TYPES[$entry['extension']];
            $dimensions = @getimagesize($sourcePath);
            if ($dimensions === false || $dimensions['mime'] !== $mime || @$fileInfo->file($sourcePath) !== $mime) {
                throw new InvalidArgumentException('ZIP内にファイル名と画像形式が一致しない項目があります。');
            }
            [$width, $height] = $dimensions;
            if (min($width, $height) < 1 || max($width, $height) > self::MAX_DIMENSION ||
                $width * $height > $pixelsRemaining) {
                throw new InvalidArgumentException('ZIP内の画像の寸法または合計画素数が上限を超えています。');
            }
            $pixelsRemaining -= $width * $height;

            $data = stream_get_contents($source, $entry['size'], 0);
            if ($data === false || strlen($data) !== $entry['size']) {
                throw new RuntimeException('Could not read the temporary ZIP image.');
            }
            $prepared = MediaZipImage::rebuild($data, $entry['extension'], $width, $height);
            unset($data);
            $outputSize = strlen($prepared);
            if ($outputSize < 1 || $outputSize > $entry['size']) {
                throw new InvalidArgumentException('ZIP内に画質を保ったまま保存できない画像があります。');
            }
            $output = self::temporaryFile();
            if (fwrite($output, $prepared) !== $outputSize || !fflush($output)) {
                throw new RuntimeException('Could not write the validated ZIP image.');
            }
            unset($prepared);
            $outputPath = stream_get_meta_data($output)['uri'];
            $format = $entry['extension'] === 'jpg' ? 'jpeg' : $entry['extension'];
            $decode = 'imagecreatefrom' . $format;
            if (!function_exists($decode)) {
                throw new InvalidArgumentException('サーバーのGDがZIP内の画像形式に対応していません。');
            }
            $image = @$decode($outputPath);
            if ($image === false || @$fileInfo->file($outputPath) !== $mime ||
                ($format !== 'gif' && (imagesx($image) !== $width || imagesy($image) !== $height))) {
                throw new InvalidArgumentException('ZIP内の画像を読み込めません。WebPは静止画を使用してください。');
            }
            $result = $entry + ['stream' => $output, 'bytes' => $outputSize, 'mime' => $mime];
            $output = null;
            return $result;
        } finally {
            fclose($source);
            if ($output !== null) {
                fclose($output);
            }
        }
    }

    private static function readImage(ZipArchive $zip, array $entry, $destination): void
    {
        $input = @$zip->getStream($entry['name']);
        if ($input === false) {
            throw new InvalidArgumentException('ZIP内の画像を読み込めませんでした。');
        }
        try {
            $size = @stream_copy_to_stream($input, $destination, $entry['size'] + 1);
        } finally {
            fclose($input);
        }
        if ($size !== $entry['size'] || !fflush($destination) || !rewind($destination)) {
            throw new InvalidArgumentException('ZIP内の画像の容量が一致しません。');
        }
        $crc = hash_init('crc32b');
        hash_update_stream($crc, $destination);
        if (hash_final($crc) !== substr(sprintf('%08x', $entry['crc']), -8)) {
            throw new InvalidArgumentException('ZIP内の画像のデータが壊れています。');
        }
    }

    private static function temporaryFile()
    {
        $file = @tmpfile();
        if ($file === false) {
            throw new RuntimeException('Could not create a temporary media file.');
        }
        try {
            self::privatePath(stream_get_meta_data($file)['uri']);
        } catch (Throwable $exception) {
            fclose($file);
            throw $exception;
        }
        return $file;
    }

    private static function privatePath(string $path): void
    {
        $resolved = realpath($path);
        if ($resolved === false) {
            throw new RuntimeException('Could not resolve a temporary media path.');
        }
        foreach ([BASE_PATH, $_SERVER['DOCUMENT_ROOT'] ?? ''] as $directory) {
            $root = $directory === '' ? false : realpath($directory);
            if ($root !== false && str_starts_with(
                strtolower($resolved), strtolower(rtrim($root, '/\\') . DIRECTORY_SEPARATOR)
            )) {
                throw new RuntimeException('Temporary media files must be outside the public site.');
            }
        }
    }

    private static function register(array $images, int $userId, ?int $folderId): int
    {
        $directory = BASE_PATH . '/public/uploads/media';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the media directory.');
        }
        $pdo = Database::connect();
        $files = [];
        $folderNames = array_unique(array_column($images, 'folder'));
        sort($folderNames, SORT_STRING);
        $folders = [];
        $pdo->beginTransaction();
        try {
            foreach ($folderNames as $name) {
                if ($name !== null) {
                    $folders[$name] = Media::createFolder($name, reuseExisting: true);
                }
            }
            if ($folderId !== null && in_array(null, $folderNames, true)) {
                $table = Database::table('media_folders');
                $folder = $pdo->prepare("SELECT id FROM {$table} WHERE id = ? FOR UPDATE");
                $folder->execute([$folderId]);
                if ($folder->fetchColumn() === false) {
                    throw new InvalidArgumentException('取り込み先のメディアフォルダが見つかりません。');
                }
            }
            foreach ($images as $image) {
                $name = 'zip_' . bin2hex(random_bytes(16)) . '.' . $image['extension'];
                $path = $directory . '/' . $name;
                $destination = @fopen($path, 'xb');
                if ($destination === false) {
                    throw new RuntimeException('Could not create an imported media file.');
                }
                $files[] = $path;
                try {
                    if (!rewind($image['stream']) ||
                        @stream_copy_to_stream($image['stream'], $destination) !== $image['bytes'] ||
                        !@fflush($destination)) {
                        throw new RuntimeException('Could not write an imported media file.');
                    }
                } finally {
                    fclose($destination);
                }
                Media::create([
                    'title' => $image['title'],
                    'original_name' => $image['originalName'],
                    'file_name' => $name,
                    'file_path' => 'uploads/media/' . $name,
                    'mime_type' => $image['mime'],
                    'file_size' => $image['bytes'],
                    'file_type' => 'image',
                    'user_id' => $userId,
                    'folder_id' => $image['folder'] === null ? $folderId : $folders[$image['folder']],
                ]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } finally {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            throw $exception;
        }
        return count(array_unique($folders));
    }
}
