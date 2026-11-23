<?php

namespace app\Core;

use InvalidArgumentException;

final class ArticleContent
{
    public const MAX_BYTES = 6553500;

    // Limit the work done by the HTML parser as well as the stored size.
    private const MAX_MARKUP_DELIMITERS = 50000;
    private const MAX_TAG_BYTES = 65535;
    private const MAX_TOTAL_MARKUP_BYTES = 1048576;

    public static function validate(mixed $content): void
    {
        if (!is_string($content)) {
            throw new InvalidArgumentException('本文はUTF-8の文字列で入力してください。');
        }
        if (strlen($content) > self::MAX_BYTES) {
            throw new InvalidArgumentException('本文はHTMLを含めて' . number_format(self::MAX_BYTES) . 'バイト以内で入力してください。');
        }
        if (str_contains($content, "\0") || preg_match('//u', $content) !== 1) {
            throw new InvalidArgumentException('本文はUTF-8の文字列で入力してください。');
        }
        if (substr_count($content, '<') > self::MAX_MARKUP_DELIMITERS
            || (strlen($content) > self::MAX_TAG_BYTES && !self::markupWithinBudget($content))) {
            throw new InvalidArgumentException('本文のHTML構造が複雑すぎます。記事を分けて保存してください。');
        }
    }

    private static function markupWithinBudget(string $content): bool
    {
        $length = strlen($content);
        $total = 0;
        $offset = 0;
        while (($start = strpos($content, '<', $offset)) !== false) {
            $offset = $start + 1;
            $leader = $content[$offset] ?? '';
            if ($leader === '' || strspn($leader, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ/!?') === 0) {
                continue;
            }

            while ($offset < $length) {
                $offset += strcspn($content, "\"'>", $offset);
                $delimiter = $content[$offset] ?? '>';
                if ($delimiter === '>') {
                    break;
                }
                $closing = strpos($content, $delimiter, $offset + 1);
                $offset = $closing === false ? $length : $closing + 1;
                if ($offset - $start > self::MAX_TAG_BYTES) {
                    return false;
                }
            }
            $size = $offset - $start + 1;
            $total += $size;
            if ($size > self::MAX_TAG_BYTES || $total > self::MAX_TOTAL_MARKUP_BYTES) {
                return false;
            }
            $offset++;
            if ($offset >= $length) {
                break;
            }
        }

        return true;
    }

    public static function sanitize(mixed $content): string
    {
        self::validate($content);
        $content = HtmlSanitizer::sanitize($content);
        self::validate($content);
        return $content;
    }
}
