<?php

namespace app\Core;

use InvalidArgumentException;

/**
 * Rebuilds the image container from the data needed to display it.
 * Pixel data stays encoded in its original format.
 */
final class MediaZipImage
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";
    private const PROFILE_LIMIT = 1048576;

    public static function rebuild(string $data, string $format, int $width, int $height): string
    {
        return match ($format) {
            'jpg' => self::jpeg($data),
            'png' => self::png($data, $width, $height),
            'webp' => self::webp($data, $width, $height),
            'gif' => self::gif($data, $width, $height),
        };
    }

    private static function jpeg(string $data): string
    {
        $length = strlen($data);
        $output = "\xFF\xD8";
        $profiles = [];
        $profileCount = null;
        $sawFrame = false;
        $sawScan = false;
        $sawExif = false;
        if (!str_starts_with($data, $output)) {
            throw self::invalid();
        }
        for ($offset = 2; $offset < $length;) {
            if ($data[$offset] !== "\xFF") {
                throw self::invalid();
            }
            $offset += strspn($data, "\xFF", $offset);
            $marker = ord($data[$offset++] ?? "\0");
            if ($marker === 0xd9 && $sawScan) {
                if ($profiles !== []) {
                    if (count($profiles) !== $profileCount) {
                        throw self::invalid();
                    }
                    ksort($profiles);
                    self::checkProfile(implode('', $profiles));
                }
                return $output . "\xFF\xD9";
            }
            $size = self::number($data, 'n', $offset, 2);
            if ($size < 2 || $size > $length - $offset) {
                throw self::invalid();
            }
            $payload = substr($data, $offset + 2, $size - 2);
            $offset += $size;
            if ($marker === 0xe0 && str_starts_with($payload, "JFIF\0") && strlen($payload) >= 14) {
                // Keep the color-transform hint and density without the thumbnail.
                $payload = substr($payload, 0, 12) . "\0\0";
            } elseif ($marker === 0xe1 && str_starts_with($payload, "Exif\0\0")) {
                if ($sawExif) {
                    continue;
                }
                $sawExif = true;
                $orientation = self::orientation(substr($payload, 6));
                if ($orientation === '') {
                    continue;
                }
                $payload = "Exif\0\0" . $orientation;
            } elseif ($marker === 0xe2 && str_starts_with($payload, "ICC_PROFILE\0")) {
                $index = ord($payload[12] ?? "\0");
                $count = ord($payload[13] ?? "\0");
                if ($index < 1 || $index > $count || isset($profiles[$index]) ||
                    ($profileCount !== null && $profileCount !== $count)) {
                    throw self::invalid();
                }
                $profileCount = $count;
                $profiles[$index] = substr($payload, 14);
            } elseif ($marker === 0xee && str_starts_with($payload, 'Adobe') && strlen($payload) >= 12) {
                $payload = substr($payload, 0, 12);
            } elseif ($marker === 0xfe || ($marker >= 0xe0 && $marker <= 0xef)) {
                continue;
            } elseif (in_array($marker, [0xc0, 0xc1, 0xc2, 0xc9, 0xca], true)) {
                if ($sawFrame) {
                    throw self::invalid();
                }
                $sawFrame = true;
            } elseif (!in_array($marker, [0xc4, 0xcc, 0xda, 0xdb, 0xdd], true)) {
                throw self::invalid();
            }
            $output .= "\xFF" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
            if ($marker === 0xda) {
                if (!$sawFrame) {
                    throw self::invalid();
                }
                $end = self::jpegScanEnd($data, $offset);
                $output .= substr($data, $offset, $end - $offset);
                $offset = $end;
                $sawScan = true;
            }
        }
        throw self::invalid();
    }

    private static function jpegScanEnd(string $data, int $offset): int
    {
        while (($mark = strpos($data, "\xFF", $offset)) !== false) {
            $next = $mark + strspn($data, "\xFF", $mark);
            $code = $data[$next] ?? '';
            if ($code !== "\0" && ($code < "\xD0" || $code > "\xD7")) {
                return $mark;
            }
            $offset = $next + 1;
        }
        throw self::invalid();
    }

    private static function png(string $data, int $width, int $height): string
    {
        if (!str_starts_with($data, self::PNG_SIGNATURE)) {
            throw self::invalid();
        }
        $sizes = [
            'IHDR' => [13], 'PLTE' => range(3, 768, 3), 'tRNS' => range(1, 256),
            'gAMA' => [4], 'cHRM' => [32], 'sRGB' => [1], 'sBIT' => [1, 2, 3, 4], 'cICP' => [4],
            'mDCV' => [24], 'cLLI' => [8], 'bKGD' => [1, 2, 6], 'pHYs' => [9],
        ];
        $before = self::PNG_SIGNATURE;
        $after = $compressed = '';
        $seen = [];
        $length = strlen($data);
        for ($offset = 8; $offset + 12 <= $length;) {
            $size = self::number($data, 'N', $offset, 4);
            if ($size > $length - $offset - 12) {
                throw self::invalid();
            }
            $type = substr($data, $offset + 4, 4);
            $payload = substr($data, $offset + 8, $size);
            if (hash('crc32b', $type . $payload, true) !== substr($data, $offset + 8 + $size, 4) ||
                ($offset === 8 && $type !== 'IHDR')) {
                throw self::invalid();
            }
            $offset += $size + 12;
            if ($type === 'IDAT') {
                $compressed .= $payload;
                continue;
            }
            if ($type === 'IEND' && $size === 0 && $compressed !== '') {
                // Allow 16-bit RGBA samples and the filter bytes for all Adam7 passes.
                $pixels = self::compressPng($compressed, $width * $height * 8 + $height * 2 + 8);
                return $before . self::pngChunk('IDAT', $pixels) . $after . self::pngChunk('IEND', '');
            }
            if (!isset($sizes[$type]) && $type !== 'iCCP' && $type !== 'eXIf') {
                if ((ord($type[0]) & 0x20) === 0) {
                    throw self::invalid();
                }
                continue;
            }
            if (isset($seen[$type]) || (isset($sizes[$type]) && !in_array($size, $sizes[$type], true))) {
                throw self::invalid();
            }
            $seen[$type] = true;
            if ($type === 'iCCP') {
                $payload = self::pngProfile($payload);
            } elseif ($type === 'eXIf') {
                $payload = self::orientation($payload);
                if ($payload === '') {
                    continue;
                }
            }
            if ($compressed === '') {
                $before .= self::pngChunk($type, $payload);
            } else {
                $after .= self::pngChunk($type, $payload);
            }
        }
        throw self::invalid();
    }

    private static function pngChunk(string $type, string $payload): string
    {
        return pack('N', strlen($payload)) . $type . $payload . hash('crc32b', $type . $payload, true);
    }

    private static function compressPng(string $compressed, int $limit): string
    {
        $inflate = inflate_init(ZLIB_ENCODING_DEFLATE);
        $deflate = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        $runLength = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 1, 'strategy' => ZLIB_RLE]);
        if ($inflate === false || $deflate === false || $runLength === false) {
            throw self::invalid();
        }
        $candidates = [[$deflate, ''], [$runLength, '']];
        $length = strlen($compressed);
        for ($offset = 0; $offset < $length; $offset += 2048) {
            $raw = @inflate_add($inflate, substr($compressed, $offset, 2048), ZLIB_NO_FLUSH);
            if ($raw === false || strlen($raw) > $limit) {
                throw self::invalid();
            }
            $limit -= strlen($raw);
            $finished = inflate_get_status($inflate) === ZLIB_STREAM_END;
            foreach ($candidates as $index => [$encoder, $output]) {
                $block = deflate_add($encoder, $raw, $finished ? ZLIB_FINISH : ZLIB_NO_FLUSH);
                if ($block === false || strlen($output) + strlen($block) >= $length) {
                    unset($candidates[$index]);
                } else {
                    $candidates[$index][1] = $output . $block;
                }
            }
            if ($finished) {
                $smallest = substr($compressed, 0, inflate_get_read_len($inflate));
                foreach ($candidates as [, $output]) {
                    if (strlen($output) < strlen($smallest)) {
                        $smallest = $output;
                    }
                }
                return $smallest;
            }
        }
        throw self::invalid();
    }

    private static function pngProfile(string $payload): string
    {
        $end = strpos($payload, "\0");
        if ($end === false || $end < 1 || $end > 79 || ($payload[$end + 1] ?? '') !== "\0") {
            throw self::invalid();
        }
        $encoded = substr($payload, $end + 2);
        $profile = @gzuncompress($encoded, self::PROFILE_LIMIT);
        if ($profile === false) {
            throw self::invalid();
        }
        self::checkProfile($profile);
        return "I\0\0" . self::compressPng($encoded, self::PROFILE_LIMIT);
    }

    private static function webp(string $data, int $width, int $height): string
    {
        $length = self::number($data, 'V', 4, 4) + 8;
        if (!str_starts_with($data, 'RIFF') || substr($data, 8, 4) !== 'WEBP' || $length > strlen($data) || $length % 2) {
            throw self::invalid();
        }
        $canvas = $profile = $alpha = $frame = $exif = null;
        $offset = 12;
        while ($offset + 8 <= $length) {
            $type = substr($data, $offset, 4);
            $size = self::number($data, 'V', $offset + 4, 4);
            $offset += 8;
            $padded = $size + ($size % 2);
            if ($padded > $length - $offset) {
                throw self::invalid();
            }
            $payload = substr($data, $offset, $size);
            $offset += $padded;
            switch ($type) {
                case 'VP8X':
                    if ($canvas !== null || $size !== 10 || $offset !== 30) {
                        throw self::invalid();
                    }
                    if (ord($payload[0]) & 2) {
                        throw new InvalidArgumentException('ZIP内のWebPは静止画を使用してください。');
                    }
                    $canvas = $payload;
                    break;
                case 'ICCP':
                    if ($profile !== null || $alpha !== null || $frame !== null ||
                        $canvas === null || !(ord($canvas[0]) & 0x20)) {
                        throw self::invalid();
                    }
                    self::checkProfile($payload);
                    $profile = self::webpChunk($type, $payload);
                    break;
                case 'ALPH':
                    if ($alpha !== null || $frame !== null || $canvas === null || !(ord($canvas[0]) & 0x10)) {
                        throw self::invalid();
                    }
                    $alpha = self::webpChunk($type, $payload);
                    break;
                case 'VP8 ':
                case 'VP8L':
                    if ($frame !== null || ($type === 'VP8L' && $alpha !== null)) {
                        throw self::invalid();
                    }
                    $frame = self::webpChunk($type, $payload);
                    break;
                case 'EXIF':
                    if ($exif === null) {
                        $prefix = str_starts_with($payload, "Exif\0\0") ? "Exif\0\0" : '';
                        $orientation = self::orientation(substr($payload, strlen($prefix)));
                        $exif = $orientation === '' ? '' : $prefix . $orientation;
                    }
                    break;
                case 'ANIM':
                case 'ANMF':
                    throw new InvalidArgumentException('ZIP内のWebPは静止画を使用してください。');
            }
        }
        if ($offset !== $length || $frame === null ||
            ($canvas === null && ($profile !== null || $alpha !== null || $exif))) {
            throw self::invalid();
        }
        // Check the coded frame size separately from the declared WebP canvas.
        $frameSize = @getimagesizefromstring('RIFF' . pack('V', 4 + strlen($frame)) . 'WEBP' . $frame);
        if ($frameSize === false || $frameSize[0] !== $width || $frameSize[1] !== $height) {
            throw self::invalid();
        }
        $output = 'WEBP';
        if ($canvas !== null) {
            $flags = (ord($canvas[0]) & 0x10) | ($profile === null ? 0 : 0x20) | ($exif ? 8 : 0);
            if ($flags !== 0) {
                $output .= self::webpChunk('VP8X', chr($flags) . "\0\0\0" . substr($canvas, 4));
            }
        }
        $output .= $profile . $alpha . $frame . ($exif ? self::webpChunk('EXIF', $exif) : '');
        return 'RIFF' . pack('V', strlen($output)) . $output;
    }

    private static function webpChunk(string $type, string $payload): string
    {
        $size = strlen($payload);
        return $type . pack('V', $size) . $payload . ($size % 2 ? "\0" : '');
    }

    private static function gif(string $data, int $width, int $height): string
    {
        if (strlen($data) < 13 || !in_array(substr($data, 0, 6), ['GIF87a', 'GIF89a'], true)) {
            throw self::invalid();
        }
        $flags = ord($data[10]);
        $offset = 13 + ($flags & 0x80 ? 3 * (2 << ($flags & 7)) : 0);
        $header = substr($data, 0, $offset);
        $control = '';
        while (($data[$offset] ?? '') === "\x21" && $offset < 65536) {
            $label = $data[$offset + 1] ?? '';
            $start = $offset + 2;
            $offset = self::gifBlockEnd($data, $start);
            if ($label === "\xF9" && $offset === $start + 6 && $data[$start] === "\x04") {
                $control = ord($data[$start + 1]) & 1 ? "\x21\xF9\x04\x01\0\0" . $data[$start + 4] . "\0" : '';
            }
        }
        if ($offset >= 65536 || ($data[$offset] ?? '') !== ',' || $offset + 10 > strlen($data)) {
            throw self::invalid();
        }
        $bounds = unpack('vleft/vtop/vwidth/vheight', substr($data, $offset + 1, 8));
        if (min($bounds['width'], $bounds['height']) < 1 || $bounds['left'] + $bounds['width'] > $width ||
            $bounds['top'] + $bounds['height'] > $height) {
            throw new InvalidArgumentException('ZIP内のGIFの先頭フレームを確認できませんでした。');
        }
        $flags = ord($data[$offset + 9]);
        $image = $offset + 10 + ($flags & 0x80 ? 3 * (2 << ($flags & 7)) : 0);
        $end = self::gifBlockEnd($data, $image + 1);
        return $header . $control . substr($data, $offset, $end - $offset) . ';';
    }

    private static function gifBlockEnd(string $data, int $offset): int
    {
        $length = strlen($data);
        while ($offset < $length) {
            $size = ord($data[$offset++]);
            if ($size === 0) {
                return $offset;
            }
            $offset += $size;
        }
        throw self::invalid();
    }

    private static function orientation(string $tiff): string
    {
        $header = substr($tiff, 0, 4);
        if ($header === "II\x2a\0") {
            [$short, $long] = ['v', 'V'];
        } elseif ($header === "MM\0\x2a") {
            [$short, $long] = ['n', 'N'];
        } else {
            return '';
        }
        $offset = self::number($tiff, $long, 4, 4);
        $count = self::number($tiff, $short, $offset, 2);
        $first = $offset + 2;
        if ($offset < 8 || $first + 12 * $count + 4 > strlen($tiff)) {
            throw self::invalid();
        }
        for ($index = 0; $index < $count; $index++) {
            $position = $first + 12 * $index;
            if (self::number($tiff, $short, $position, 2) !== 0x0112) {
                continue;
            }
            $value = self::number($tiff, $short, $position + 8, 2);
            if (self::number($tiff, $short, $position + 2, 2) === 3 &&
                self::number($tiff, $long, $position + 4, 4) === 1 && $value >= 2 && $value <= 8) {
                return "II\x2a\0" . pack('VvvvVv', 8, 1, 0x0112, 3, 1, $value) . str_repeat("\0", 6);
            }
            break;
        }
        return '';
    }

    private static function checkProfile(string $profile): void
    {
        $length = strlen($profile);
        if ($length < 132 || $length > self::PROFILE_LIMIT || self::number($profile, 'N', 0, 4) !== $length ||
            substr($profile, 36, 4) !== 'acsp') {
            throw self::invalid();
        }
        $count = self::number($profile, 'N', 128, 4);
        $table = 132 + $count * 12;
        if ($table > $length) {
            throw self::invalid();
        }
        for ($entry = 132; $entry < $table; $entry += 12) {
            $offset = self::number($profile, 'N', $entry + 4, 4);
            $size = self::number($profile, 'N', $entry + 8, 4);
            if ($offset < $table || $size < 8 || $size > $length - $offset) {
                throw self::invalid();
            }
        }
    }

    private static function number(string $data, string $format, int $offset, int $size): int
    {
        if ($offset < 0 || $offset + $size > strlen($data)) {
            throw self::invalid();
        }
        $value = unpack($format, $data, $offset)[1];
        if ($value < 0) {
            throw self::invalid();
        }
        return $value;
    }

    private static function invalid(): InvalidArgumentException
    {
        return new InvalidArgumentException('ZIP内に画像データの構成を確認できないファイルがあります。');
    }
}
