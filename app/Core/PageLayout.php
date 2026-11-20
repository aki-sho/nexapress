<?php

namespace app\Core;

use app\Models\Navigation;
use InvalidArgumentException;

final class PageLayout
{
    public const WIDTHS = ['standard' => '標準', 'wide' => 'ワイド（最大1440px）', 'full' => '全幅'];
    public const ELEMENTS = [
        'page_title' => 'ページのタイトル',
        'page_content' => 'ページの本文',
        'heading' => '見出し',
        'text' => '文章',
        'image' => '画像',
        'button' => 'ボタン',
    ];
    public const MAX_SECTIONS = 24;
    public const MAX_ELEMENTS = 120;
    public const SCOPES = [
        'all' => '固定と投稿の共通レイアウト',
        'page' => '固定のみの共通レイアウト',
        'post' => '投稿のみの共通レイアウト',
    ];
    public const EMPTY_OVERRIDES = ['format' => 2];

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
            throw new InvalidArgumentException('ページのレイアウトを確認してください。');
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

    public static function settings(int $id, string $kind = 'page'): array
    {
        $table = self::table($kind);
        $stmt = Database::connect()->prepare("SELECT settings FROM {$table} WHERE {$kind}_id = ?");
        $stmt->execute([$id]);
        return self::resolve([self::decode($stmt->fetchColumn())]);
    }

    public static function snapshot(int $id, bool $lock = false, string $kind = 'page'): ?array
    {
        $layouts = self::table($kind);
        $pages = Database::table($kind === 'post' ? 'posts' : 'pages');
        $stmt = Database::connect()->prepare("SELECT l.settings FROM {$pages} p
            LEFT JOIN {$layouts} l ON l.{$kind}_id = p.id WHERE p.id = ? AND p.deleted_at IS NULL" .
            ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }
        $overrides = self::decode($value);
        return [
            'id' => $id, 'kind' => $kind, 'overrides' => $overrides,
            'layout' => self::resolve([$overrides]),
            'revision' => hash('sha256', self::json($overrides)),
        ];
    }

    /** Called after locking and checking the page revision in Design::publish. */
    public static function save(int $id, array $layout, string $kind = 'page'): void
    {
        $table = self::table($kind);
        $json = self::json(self::overrides($layout));
        $stmt = Database::connect()->prepare("INSERT INTO {$table} ({$kind}_id, settings) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE settings = ?");
        $stmt->execute([$id, $json, $json]);
    }

    /** Use the caller's transaction to reset both kinds of layout together. */
    public static function resetAll(): void
    {
        $pdo = Database::connect();
        foreach (['page', 'post'] as $kind) {
            $table = self::table($kind);
            $pdo->exec("DELETE FROM {$table}");
        }
    }

    private static function table(string $kind): string
    {
        if (!in_array($kind, ['page', 'post'], true)) {
            throw new InvalidArgumentException('固定ページまたは投稿を選択してください。');
        }
        return Database::table($kind === 'post' ? 'post_layouts' : 'page_layouts');
    }

    public static function sectionDefaults(): array
    {
        return [
            'background' => 'full', 'color' => '#F4F5F7', 'widthMode' => 'theme',
            'percent' => 80, 'columns' => 1, 'share' => 50, 'gap' => 10, 'groups' => [[], []],
        ];
    }

    /** Convert saved complete layouts without changing their existing appearance. */
    public static function overrides(array $layout): array
    {
        if (($layout['format'] ?? null) === 2) {
            return self::validateOverrides($layout);
        }
        if ($layout === []) {
            return self::EMPTY_OVERRIDES;
        }
        $layout = self::validate($layout);
        // Before inheritance existed, an unused layout was saved with false and no sections.
        if (!$layout['enabled'] && $layout['sections'] === []) {
            return $layout['width'] === 'inherit' ? self::EMPTY_OVERRIDES
                : ['format' => 2, 'width' => $layout['width']];
        }
        $layout['format'] = 2;
        $layout['sectionCount'] = count($layout['sections']);
        if ($layout['width'] === 'inherit') {
            unset($layout['width']);
        }
        return $layout;
    }

    public static function validateOverrides(array $input): array
    {
        if (($input['format'] ?? null) !== 2 ||
            array_diff(array_keys($input), ['format', 'enabled', 'width', 'sectionCount', 'sections'])) {
            throw new InvalidArgumentException('ページのレイアウト設定を確認してください。');
        }
        $result = self::EMPTY_OVERRIDES;
        if (array_key_exists('enabled', $input)) {
            if (!is_bool($input['enabled'])) {
                throw new InvalidArgumentException('セクションの表示設定を確認してください。');
            }
            $result['enabled'] = $input['enabled'];
        }
        if (array_key_exists('width', $input)) {
            if (!is_string($input['width']) || !isset(self::WIDTHS[$input['width']])) {
                throw new InvalidArgumentException('ページの本文幅を確認してください。');
            }
            $result['width'] = $input['width'];
        }
        if (array_key_exists('sectionCount', $input)) {
            $result['sectionCount'] = self::range($input['sectionCount'], 0, self::MAX_SECTIONS);
        }
        if (array_key_exists('sections', $input)) {
            $sections = $input['sections'];
            if (!is_array($sections) || count($sections) > self::MAX_SECTIONS ||
                array_keys($sections) !== array_keys(array_values($sections))) {
                throw new InvalidArgumentException('セクションの設定を確認してください。');
            }
            $result['sections'] = [];
            $count = 0;
            $defaults = self::sectionDefaults();
            foreach ($sections as $section) {
                if (!is_array($section) || array_diff(array_keys($section), array_keys($defaults))) {
                    throw new InvalidArgumentException('セクションの設定を確認してください。');
                }
                $validated = self::section(array_replace($defaults, $section), $count);
                $result['sections'][] = array_intersect_key($validated, $section);
            }
        }
        return $result;
    }

    /** Later layers override only explicitly assigned fields. */
    public static function resolve(array $layers): array
    {
        $result = ['enabled' => false, 'width' => 'inherit', 'sections' => []];
        foreach ($layers as $layer) {
            $patch = self::overrides($layer);
            foreach (['enabled', 'width'] as $field) {
                if (array_key_exists($field, $patch)) {
                    $result[$field] = $patch[$field];
                }
            }
            if (isset($patch['sectionCount'])) {
                $result['sections'] = array_slice($result['sections'], 0, $patch['sectionCount']);
                while (count($result['sections']) < $patch['sectionCount']) {
                    $result['sections'][] = self::sectionDefaults();
                }
            }
            foreach ($patch['sections'] ?? [] as $index => $section) {
                if (isset($result['sections'][$index])) {
                    $result['sections'][$index] = array_replace($result['sections'][$index], $section);
                }
            }
        }
        return self::validate($result);
    }

    public static function validate(array $layout): array
    {
        $enabled = $layout['enabled'] ?? null;
        $width = $layout['width'] ?? null;
        $sections = $layout['sections'] ?? null;
        if (!is_bool($enabled) || !is_string($width) || ($width !== 'inherit' && !isset(self::WIDTHS[$width])) ||
            !is_array($sections) || count($sections) > self::MAX_SECTIONS) {
            throw new InvalidArgumentException('ページのレイアウトを確認してください。');
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
            throw new InvalidArgumentException('ページの要素数が上限を超えました。');
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
        return is_string($value)
            ? self::overrides(json_decode($value, true, 32, JSON_THROW_ON_ERROR))
            : self::EMPTY_OVERRIDES;
    }

    private static function json(array $layout): string
    {
        return json_encode($layout, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
