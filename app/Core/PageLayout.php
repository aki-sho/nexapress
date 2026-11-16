<?php

namespace app\Core;

use app\Models\Navigation;
use InvalidArgumentException;

final class PageLayout
{
    public const WIDTHS = ['standard' => '標準', 'wide' => 'ワイド（最大1440px）', 'full' => '全幅'];
    public const ELEMENTS = [
        'page_title' => '固定ページのタイトル',
        'page_content' => '固定ページの本文',
        'heading' => '見出し',
        'text' => '文章',
        'image' => '画像',
        'button' => 'ボタン',
    ];
    public const MAX_SECTIONS = 24;
    public const MAX_ELEMENTS = 120;

    public static function stylesheetUrl(array $layout, string $themeWidth): string
    {
        $width = $layout['width'] === 'inherit' ? $themeWidth : $layout['width'];
        // Include only appearance settings so the same stylesheet works in the draft preview.
        $sections = array_map(static fn(array $section): array => [
            'color' => $section['color'],
            'percent' => $section['widthMode'] === 'percent' ? $section['percent'] : null,
            'share' => $section['share'],
            'gap' => $section['gap'],
        ], $layout['sections']);

        return url('nexapress/assets/page-layout') . '?' . http_build_query([
            'width' => $width, 'sections' => $sections,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function stylesheet(array $query): string
    {
        $width = $query['width'] ?? null;
        $sections = $query['sections'] ?? null;
        if (!is_string($width) || !isset(self::WIDTHS[$width]) ||
            !is_array($sections) || !$sections || count($sections) > self::MAX_SECTIONS) {
            throw new InvalidArgumentException('固定ページのレイアウトを確認してください。');
        }
        $defaultWidth = match ($width) {
            'wide' => '1440px',
            'full' => '100%',
            default => 'var(--theme-content-width, 1080px)',
        };

        $rules = [];
        foreach (array_values($sections) as $index => $section) {
            $color = $section['color'] ?? null;
            if (!is_string($color) || !preg_match('/^#[0-9A-F]{6}$/iD', $color)) {
                throw new InvalidArgumentException('セクションの背景色を確認してください。');
            }
            $sectionWidth = isset($section['percent'])
                ? self::range(filter_var($section['percent'], FILTER_VALIDATE_INT), 40, 100) . '%'
                : $defaultWidth;
            $share = self::range(filter_var($section['share'] ?? null, FILTER_VALIDATE_INT), 20, 80);
            $gap = self::range(filter_var($section['gap'] ?? null, FILTER_VALIDATE_INT), 0, 30);
            $foreground = self::foreground($color);
            $right = 100 - $share;
            $rules[] = ".nx-page-section[data-layout-section=\"$index\"] {" .
                "--nx-section-width:$sectionWidth;--nx-section-background:$color;--nx-section-foreground:$foreground;" .
                "--nx-section-left:{$share}fr;--nx-section-right:{$right}fr;--nx-section-gap:$gap%;}";
        }

        return implode("\n", $rules);
    }

    public static function foreground(string $hex): string
    {
        $channels = array_map(static function (string $part): float {
            $value = hexdec($part) / 255;
            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));
        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        return $luminance > 0.179 ? '#000000' : '#FFFFFF';
    }

    public static function settings(int $id): array
    {
        $table = Database::table('page_layouts');
        $stmt = Database::connect()->prepare("SELECT settings FROM {$table} WHERE page_id = ?");
        $stmt->execute([$id]);
        return self::decode($stmt->fetchColumn());
    }

    public static function snapshot(int $id, bool $lock = false): ?array
    {
        $pages = Database::table('pages');
        $layouts = Database::table('page_layouts');
        $stmt = Database::connect()->prepare("SELECT l.settings FROM {$pages} p
            LEFT JOIN {$layouts} l ON l.page_id = p.id WHERE p.id = ? AND p.deleted_at IS NULL" .
            ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }
        $layout = self::decode($value);
        return ['id' => $id, 'layout' => $layout, 'revision' => hash('sha256', self::json($layout))];
    }

    /** Called after locking and checking the page revision in Design::publish. */
    public static function save(int $id, array $layout): void
    {
        $table = Database::table('page_layouts');
        $json = self::json($layout);
        $stmt = Database::connect()->prepare("INSERT INTO {$table} (page_id, settings) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE settings = ?");
        $stmt->execute([$id, $json, $json]);
    }

    public static function validate(array $layout): array
    {
        $enabled = $layout['enabled'] ?? null;
        $width = $layout['width'] ?? null;
        $sections = $layout['sections'] ?? null;
        if (!is_bool($enabled) || !is_string($width) || ($width !== 'inherit' && !isset(self::WIDTHS[$width])) ||
            !is_array($sections) || count($sections) > self::MAX_SECTIONS) {
            throw new InvalidArgumentException('固定ページのレイアウトを確認してください。');
        }
        $result = [];
        $count = 0;
        foreach ($sections as $section) {
            if (!is_array($section)) {
                throw new InvalidArgumentException('セクションの設定を確認してください。');
            }
            $result[] = self::section($section, $count);
        }
        return ['enabled' => $enabled, 'width' => $width, 'sections' => $result];
    }

    private static function section(array $input, int &$count): array
    {
        $background = $input['background'] ?? null;
        $color = $input['color'] ?? null;
        $widthMode = $input['widthMode'] ?? null;
        $columns = $input['columns'] ?? null;
        $groups = $input['groups'] ?? null;
        if (!in_array($background, ['full', 'content', 'none'], true) ||
            !is_string($color) || !preg_match('/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/D', $color) ||
            !in_array($widthMode, ['theme', 'percent'], true) || !in_array($columns, [1, 2], true) ||
            !is_array($groups) || array_keys($groups) !== [0, 1] || !is_array($groups[0]) || !is_array($groups[1])) {
            throw new InvalidArgumentException('セクションの背景色・幅・カラムを確認してください。');
        }
        $count += count($groups[0]) + count($groups[1]);
        if ($count > self::MAX_ELEMENTS) {
            throw new InvalidArgumentException('固定ページの要素数が上限を超えました。');
        }
        if (strlen($color) === 4) {
            $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }
        return [
            'background' => $background, 'color' => strtoupper($color), 'widthMode' => $widthMode,
            'percent' => self::range($input['percent'] ?? null, 40, 100),
            'columns' => $columns, 'share' => self::range($input['share'] ?? null, 20, 80),
            'gap' => self::range($input['gap'] ?? null, 0, 30),
            'groups' => [array_values(array_map([self::class, 'element'], $groups[0])),
                array_values(array_map([self::class, 'element'], $groups[1]))],
        ];
    }

    private static function element(mixed $input): array
    {
        $type = $input['type'] ?? null;
        if (!is_string($type) || !isset(self::ELEMENTS[$type])) {
            throw new InvalidArgumentException('セクションの要素の種類を確認してください。');
        }
        $element = ['type' => $type];
        if ($type === 'image') {
            $id = $input['media_id'] ?? null;
            if ($id !== null && (!is_int($id) || $id < 1)) {
                throw new InvalidArgumentException('画像を選び直してください。');
            }
            $element['media_id'] = $id;
            $element['alt'] = self::text($input['alt'] ?? '', 255);
        } elseif (in_array($type, ['heading', 'text', 'button'], true)) {
            $element['text'] = self::text($input['text'] ?? '', $type === 'text' ? 1200 : 120);
            if ($type === 'button') {
                $url = $input['url'] ?? null;
                if (!is_string($url) || ($url !== '' && !Navigation::safeUrl($url))) {
                    throw new InvalidArgumentException('ボタンのリンク先を確認してください。');
                }
                $element['url'] = $url;
            }
        }
        return $element;
    }

    private static function range(mixed $value, int $min, int $max): int
    {
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new InvalidArgumentException('幅・配分・間隔には指定範囲内の整数を入力してください。');
        }
        return $value;
    }

    private static function text(mixed $value, int $max): string
    {
        if (!is_string($value) || nx_text_length($value) > $max) {
            throw new InvalidArgumentException('セクション内の入力文字数を確認してください。');
        }
        return trim($value);
    }

    private static function decode(string|false|null $value): array
    {
        $defaults = ['enabled' => false, 'width' => 'inherit', 'sections' => []];
        return is_string($value) ? array_replace($defaults, json_decode($value, true, 32, JSON_THROW_ON_ERROR)) : $defaults;
    }

    private static function json(array $layout): string
    {
        return json_encode($layout, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
