<?php

namespace app\Core;

use app\Models\Category;
use app\Models\Media;
use app\Models\Post;

/*
 * 投稿の入力値を整理・検証
 */
class PostValidator
{
    private const TITLE_MAX_LENGTH = 255;

    private const SLUG_MAX_LENGTH = 255;

    private const EXCERPT_MAX_LENGTH = 1000;

    /*
     * posts.contentがTEXT型のため
     * 最大値より少し余裕を持たせる
     */
    private const CONTENT_MAX_BYTES = 65000;

    /*
     * 投稿入力値を検証
     *
     * 戻り値：
     *
     * [
     *     'valid' => trueまたはfalse,
     *     'data' => 整理済み投稿データ,
     *     'errors' => 項目ごとのエラー,
     * ]
     */
    public static function validate(
        array $input,
        ?int $postId = null
    ): array {
        $errors = [];

        /*
         * タイトル
         */
        $title = self::normalizePlainText(
            $input['title'] ?? ''
        );

        if ($title === '') {
            $errors['title'] =
                'タイトルを入力してください。';
        } elseif (
            self::textLength($title)
            > self::TITLE_MAX_LENGTH
        ) {
            $errors['title'] =
                'タイトルは'
                . self::TITLE_MAX_LENGTH
                . '文字以内で入力してください。';
        }

        /*
         * スラッグ
         *
         * 未入力の場合はタイトルから生成
         */
        $originalSlug = trim(
            (string)($input['slug'] ?? '')
        );

        $slugSource = $originalSlug !== ''
            ? $originalSlug
            : $title;

        $slug = self::normalizeSlug(
            $slugSource
        );

        if ($slug === '') {
            $errors['slug'] =
                'スラッグを入力してください。';
        } elseif (
            self::textLength($slug)
            > self::SLUG_MAX_LENGTH
        ) {
            $errors['slug'] =
                'スラッグは'
                . self::SLUG_MAX_LENGTH
                . '文字以内で入力してください。';
        } elseif (
            in_array(
                self::lower($slug),
                self::reservedSlugs(),
                true
            )
        ) {
            $errors['slug'] =
                'このスラッグは'
                . 'システムで使用されているため'
                . '指定できません。';
        } elseif (
            Post::slugExists(
                $slug,
                $postId
            )
        ) {
            $errors['slug'] =
                '同じスラッグの投稿が'
                . 'すでに存在します。';
        }

        /*
         * 本文を安全化
         */
        $content =
            HtmlSanitizer::sanitize(
                (string)(
                    $input['content']
                    ?? ''
                )
            );

        if (!self::hasVisibleContent($content)) {
            $errors['content'] =
                '本文を入力してください。';
        } elseif (
            strlen($content)
            > self::CONTENT_MAX_BYTES
        ) {
            $errors['content'] =
                '本文のデータ量が大きすぎます。'
                . '画像はメディアへアップロードし、'
                . '本文には画像URLを挿入してください。';
        }

        /*
         * 抜粋
         */
        $excerpt =
            self::normalizeExcerpt(
                $input['excerpt'] ?? ''
            );

        if (
            self::textLength($excerpt)
            > self::EXCERPT_MAX_LENGTH
        ) {
            $errors['excerpt'] =
                '抜粋は'
                . self::EXCERPT_MAX_LENGTH
                . '文字以内で入力してください。';
        }

        /*
         * 公開状態
         */
        $status = strtolower(
            trim(
                (string)(
                    $input['status']
                    ?? 'draft'
                )
            )
        );

        if (
            !in_array(
                $status,
                [
                    'draft',
                    'published',
                ],
                true
            )
        ) {
            $errors['status'] =
                '公開状態が正しくありません。';

            $status = 'draft';
        }

        /*
         * カテゴリ
         */
        $categoryId =
            self::normalizeNullableId(
                $input['category_id']
                ?? null,
                'category_id',
                'カテゴリ',
                $errors
            );

        if (
            $categoryId !== null &&
            Category::find($categoryId)
                === null
        ) {
            $errors['category_id'] =
                '選択したカテゴリが'
                . '見つかりません。';
        }

        /*
         * アイキャッチ画像
         */
        $featuredMediaId =
            self::normalizeNullableId(
                $input['featured_media_id']
                ?? null,
                'featured_media_id',
                'アイキャッチ画像',
                $errors
            );

        if ($featuredMediaId !== null) {
            $media = Media::find(
                $featuredMediaId
            );

            if ($media === null) {
                $errors['featured_media_id'] =
                    '選択したアイキャッチ画像が'
                    . '見つかりません。';
            } elseif (
                ($media['file_type'] ?? '')
                !== 'image'
            ) {
                $errors['featured_media_id'] =
                    'アイキャッチには'
                    . '画像ファイルだけを'
                    . '指定できます。';
            }
        }

        return [
            'valid' => $errors === [],

            'data' => [
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt,
                'content' => $content,
                'status' => $status,
                'category_id' => $categoryId,
                'featured_media_id' =>
                    $featuredMediaId,
            ],

            'errors' => $errors,
        ];
    }

    /*
     * スラッグをURL向けに整理
     *
     * 日本語スラッグにも対応する
     */
    public static function normalizeSlug(
        mixed $value
    ): string {
        $slug = html_entity_decode(
            strip_tags((string)$value),
            ENT_QUOTES |
            ENT_HTML5,
            'UTF-8'
        );

        $slug = trim($slug);

        /*
         * 全角英数字などを可能な範囲で
         * 標準形式へ変換
         */
        if (
            class_exists(\Normalizer::class)
        ) {
            $normalized =
                \Normalizer::normalize(
                    $slug,
                    \Normalizer::FORM_KC
                );

            if (is_string($normalized)) {
                $slug = $normalized;
            }
        }

        $slug = self::lower($slug);

        /*
         * 空白とスラッシュをハイフンへ変換
         */
        $slug = preg_replace(
            '/[\s\p{Z}\/\\\\]+/u',
            '-',
            $slug
        ) ?? '';

        /*
         * Unicode文字・数字・
         * ハイフン・アンダースコア以外を除去
         */
        $slug = preg_replace(
            '/[^\p{L}\p{N}\-_]+/u',
            '-',
            $slug
        ) ?? '';

        /*
         * 連続したハイフンを1つへまとめる
         */
        $slug = preg_replace(
            '/-+/',
            '-',
            $slug
        ) ?? '';

        return trim(
            $slug,
            '-_'
        );
    }

    /*
     * 通常文字列を整理
     */
    private static function normalizePlainText(
        mixed $value
    ): string {
        $text = html_entity_decode(
            strip_tags((string)$value),
            ENT_QUOTES |
            ENT_HTML5,
            'UTF-8'
        );

        $text = preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            $text
        ) ?? '';

        return trim($text);
    }

    /*
     * 抜粋を1行の文章として整理
     */
    private static function normalizeExcerpt(
        mixed $value
    ): string {
        $excerpt =
            self::normalizePlainText($value);

        $excerpt = str_replace(
            "\u{00A0}",
            ' ',
            $excerpt
        );

        $excerpt = preg_replace(
            '/\s+/u',
            ' ',
            $excerpt
        ) ?? '';

        return trim($excerpt);
    }

    /*
     * 本文に実際の文章や画像があるか確認
     */
    private static function hasVisibleContent(
        string $content
    ): bool {
        /*
         * 画像だけの投稿も本文ありとする
         */
        if (
            preg_match(
                '/<img\b/i',
                $content
            ) === 1
        ) {
            return true;
        }

        $plainText = html_entity_decode(
            strip_tags($content),
            ENT_QUOTES |
            ENT_HTML5,
            'UTF-8'
        );

        $plainText = str_replace(
            "\u{00A0}",
            ' ',
            $plainText
        );

        return trim($plainText) !== '';
    }

    /*
     * 未選択可能なIDを整数へ変換
     */
    private static function normalizeNullableId(
        mixed $value,
        string $fieldName,
        string $label,
        array &$errors
    ): ?int {
        if (
            $value === null ||
            $value === ''
        ) {
            return null;
        }

        if (
            is_int($value) &&
            $value > 0
        ) {
            return $value;
        }

        if (
            is_string($value) &&
            ctype_digit(trim($value))
        ) {
            $number = (int)$value;

            if ($number > 0) {
                return $number;
            }
        }

        $errors[$fieldName] =
            $label
            . 'の指定が正しくありません。';

        return null;
    }

    /*
     * 予約済みスラッグ
     */
    private static function reservedSlugs(): array
    {
        return [
            'admin',
            'install',
            'public',
        ];
    }

    /*
     * 文字数を取得
     */
    private static function textLength(
        string $value
    ): int {
        if (function_exists('mb_strlen')) {
            return mb_strlen(
                $value,
                'UTF-8'
            );
        }

        return strlen($value);
    }

    /*
     * 小文字へ変換
     */
    private static function lower(
        string $value
    ): string {
        if (
            function_exists('mb_strtolower')
        ) {
            return mb_strtolower(
                $value,
                'UTF-8'
            );
        }

        return strtolower($value);
    }
}