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
    public const ARTICLE_PADDING_SIDES = ['top' => '上', 'right' => '右', 'bottom' => '下', 'left' => '左'];

    public static function stylesheetUrl(array $layout, string $themeWidth): ?string
    {
        $sections = $layout['enabled'] ? $layout['sections'] : [];
        $padding = $layout['articlePadding'] ?? [];
        $articleSpacing = Design::settings()['spacing']['article'];
        foreach (array_keys($padding) as $side) {
            if ($articleSpacing['padding' . ucfirst($side)] !== null) {
                unset($padding[$side]);
            }
        }
        if (!$sections && !$padding) {
            return null;
        }
        $width = $layout['width'] === 'inherit' ? $themeWidth : $layout['width'];
        // Include only appearance settings so the same stylesheet works in the draft preview.
        $sections = array_map(static fn(array $section): array => [
            'color' => $section['color'],
            'percent' => $section['widthMode'] === 'percent' ? $section['percent'] : null,
            'share' => $section['share'],
            'gap' => $section['gap'],
            'columnsWidth' => $section['columnsWidth'] === 100 ? null : $section['columnsWidth'],
            'paddingTop' => $section['paddingTop'],
            'paddingBottom' => $section['paddingBottom'],
        ], $sections);

        $query = ['width' => $width, 'sections' => $sections, 'articlePadding' => $padding];
        $background = Design::background();
        if ($background['mode'] === 'color') {
            $query['background'] = $background['color'];
        }
        return url('nexapress/assets/page-layout') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function stylesheet(array $query): string
    {
        $width = $query['width'] ?? null;
        $sections = $query['sections'] ?? [];
        $padding = self::articlePadding($query['articlePadding'] ?? [], true);
        if (!is_string($width) || !isset(self::WIDTHS[$width]) ||
            !is_array($sections) || (!$sections && !$padding) || count($sections) > self::MAX_SECTIONS) {
            throw new InvalidArgumentException('ページのレイアウトを確認してください。');
        }
        $defaultWidth = match ($width) {
            'wide' => '1440px',
            'full' => '100%',
            default => 'var(--theme-content-width, 1080px)',
        };

        $background = DesignColor::normalize($query['background'] ?? null) ?? '#F5F6F8';
        $rules = [];
        if ($padding) {
            $declarations = [];
            foreach ($padding as $side => $pixels) {
                $value = $side === 'left' || $side === 'right' ? "min({$pixels}px, 40%)" : "{$pixels}px";
                $declarations[] = "padding-$side:$value;";
            }
            $rules[] = 'article.post-detail:not(.nx-editor-preview-extra), article.nx-page-article {' .
                implode('', $declarations) . '}';
        }
        foreach (array_values($sections) as $index => $section) {
            $color = DesignColor::normalize($section['color'] ?? null);
            if ($color === null) {
                throw new InvalidArgumentException('セクションの背景色を確認してください。');
            }
            $sectionWidth = isset($section['percent'])
                ? self::range(filter_var($section['percent'], FILTER_VALIDATE_INT), 40, 100) . '%'
                : $defaultWidth;
            $share = self::range(filter_var($section['share'] ?? null, FILTER_VALIDATE_INT), 20, 80);
            $gap = self::range(filter_var($section['gap'] ?? null, FILTER_VALIDATE_INT), 0, 30);
            $foreground = DesignColor::foreground($color, $background);
            $right = 100 - $share;
            $leftRatio = $share / 100;
            $rightRatio = $right / 100;
            $columnsWidth = isset($section['columnsWidth'])
                ? self::range(filter_var($section['columnsWidth'], FILTER_VALIDATE_INT), 1, 100)
                : 100;
            $columnsWidthCss = $columnsWidth === 100 ? '' : "--nx-layout-width:$columnsWidth%;";
            $spacingCss = '';
            foreach (['paddingTop' => 'padding-top', 'paddingBottom' => 'padding-bottom'] as $field => $property) {
                if (isset($section[$field])) {
                    $pixels = self::range(filter_var($section[$field], FILTER_VALIDATE_INT), 0, DesignSpacing::MAX_PIXELS);
                    $spacingCss .= "$property:{$pixels}px;";
                }
            }
            $rules[] = ".nx-page-section[data-layout-section=\"$index\"] {" .
                "--nx-section-width:$sectionWidth;--nx-section-background:$color;--nx-section-foreground:$foreground;" .
                "--nx-section-left:{$share}fr;--nx-section-right:{$right}fr;--nx-section-gap:$gap%;" .
                "--nx-section-left-ratio:$leftRatio;--nx-section-right-ratio:$rightRatio;$columnsWidthCss$spacingCss}";
        }

        return implode("\n", $rules);
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

    public static function editorChanges(mixed $input): array
    {
        if ($input === null) {
            return [];
        }
        if (!is_array($input) || array_diff(array_keys($input), ['title', 'width', 'sections'])) {
            throw new InvalidArgumentException('ページの表示設定を確認してください。');
        }
        $choices = [
            'title' => ['field' => 'showTitle', 'values' => ['inherit' => null, 'show' => true, 'hide' => false]],
            'width' => ['field' => 'width', 'values' => ['inherit' => null] + array_combine(array_keys(self::WIDTHS), array_keys(self::WIDTHS))],
            'sections' => ['field' => 'enabled', 'values' => ['inherit' => null, 'sections' => true, 'blocks' => false]],
        ];
        $changes = [];
        foreach ($input as $key => $value) {
            $choice = $choices[$key];
            if (!is_string($value) || !array_key_exists($value, $choice['values'])) {
                throw new InvalidArgumentException('ページの表示設定を確認してください。');
            }
            $changes[$choice['field']] = $choice['values'][$value];
        }
        return $changes;
    }

    public static function withEditorChanges(array $overrides, array $changes): array
    {
        foreach ($changes as $field => $value) {
            if ($value === null) {
                unset($overrides[$field]);
            } else {
                $overrides[$field] = $value;
            }
        }
        return self::validateOverrides($overrides);
    }

    /** Use the article save transaction to preserve other layout fields. */
    public static function saveEditorDisplay(int $id, string $kind, array $changes): bool
    {
        if (!$changes) {
            return false;
        }
        $snapshot = self::snapshot($id, true, $kind);
        if ($snapshot === null) {
            throw new InvalidArgumentException('保存するページが見つかりません。');
        }
        $overrides = self::withEditorChanges($snapshot['overrides'], $changes);
        if ($overrides === $snapshot['overrides']) {
            return false;
        }
        self::save($id, $overrides, $kind);
        return true;
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
            'percent' => 80, 'columns' => 1, 'share' => 50, 'gap' => 10,
            'columnsWidth' => 100, 'align' => 'flex-start', 'groups' => [[], []],
            'paddingTop' => null, 'paddingBottom' => null,
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
            $patch = $layout['width'] === 'inherit' ? self::EMPTY_OVERRIDES
                : ['format' => 2, 'width' => $layout['width']];
            return $patch + array_intersect_key($layout, ['articlePadding' => true, 'showTitle' => true]);
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
            array_diff(array_keys($input), ['format', 'enabled', 'width', 'sectionCount', 'sections', 'articlePadding', 'showTitle'])) {
            throw new InvalidArgumentException('ページのレイアウト設定を確認してください。');
        }
        $result = self::EMPTY_OVERRIDES;
        if (array_key_exists('enabled', $input)) {
            if (!is_bool($input['enabled'])) {
                throw new InvalidArgumentException('セクションの表示設定を確認してください。');
            }
            $result['enabled'] = $input['enabled'];
        }
        if (array_key_exists('showTitle', $input)) {
            if (!is_bool($input['showTitle'])) {
                throw new InvalidArgumentException('タイトルの表示設定を確認してください。');
            }
            $result['showTitle'] = $input['showTitle'];
        }
        if (array_key_exists('width', $input)) {
            if (!is_string($input['width']) || !isset(self::WIDTHS[$input['width']])) {
                throw new InvalidArgumentException('ページの本文幅を確認してください。');
            }
            $result['width'] = $input['width'];
        }
        if (array_key_exists('articlePadding', $input)) {
            $padding = self::articlePadding($input['articlePadding']);
            if ($padding) {
                $result['articlePadding'] = $padding;
            }
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
            foreach (['enabled', 'width', 'showTitle'] as $field) {
                if (array_key_exists($field, $patch)) {
                    $result[$field] = $patch[$field];
                }
            }
            if (isset($patch['articlePadding'])) {
                $result['articlePadding'] = array_replace($result['articlePadding'] ?? [], $patch['articlePadding']);
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
        $padding = self::articlePadding($layout['articlePadding'] ?? []);
        $validated = ['enabled' => $enabled, 'width' => $width, 'sections' => $result];
        if (isset($layout['showTitle'])) {
            $validated['showTitle'] = $layout['showTitle'];
        }
        if ($padding) {
            $validated['articlePadding'] = $padding;
        }
        return $validated;
    }

    private static function articlePadding(mixed $input, bool $fromQuery = false): array
    {
        if (!is_array($input) || array_diff_key($input, self::ARTICLE_PADDING_SIDES)) {
            throw new InvalidArgumentException('記事枠の余白を確認してください。');
        }
        $padding = [];
        foreach ($input as $side => $value) {
            if ($fromQuery && is_string($value) && ctype_digit($value)) {
                $value = (int)$value;
            }
            if (!is_int($value) || $value < 0 || $value > DesignSpacing::MAX_PIXELS) {
                throw new InvalidArgumentException('記事枠の余白は0〜' . DesignSpacing::MAX_PIXELS . 'の整数で入力してください。');
            }
            $padding[$side] = $value;
        }
        return $padding;
    }

    private static function section(array $input, int &$count): array
    {
        $background = $input['background'] ?? null;
        $color = DesignColor::normalize($input['color'] ?? null);
        $widthMode = $input['widthMode'] ?? null;
        $columns = $input['columns'] ?? null;
        $groups = $input['groups'] ?? null;
        $align = $input['align'] ?? 'flex-start';
        $columnsWidth = LayoutPlacement::width($input['columnsWidth'] ?? 100);
        if (!LayoutPlacement::valid($align) || !in_array($background, ['full', 'content', 'none'], true) ||
            $color === null ||
            !in_array($widthMode, ['theme', 'percent'], true) || !in_array($columns, [1, 2], true) ||
            !is_array($groups) || array_keys($groups) !== [0, 1] || !is_array($groups[0]) || !is_array($groups[1])) {
            throw new InvalidArgumentException('セクションの背景色・幅・カラムを確認してください。');
        }
        $count += count($groups[0]) + count($groups[1]);
        if ($count > self::MAX_ELEMENTS) {
            throw new InvalidArgumentException('ページの要素数が上限を超えました。');
        }
        return [
            'background' => $background, 'color' => $color, 'widthMode' => $widthMode,
            'percent' => self::range($input['percent'] ?? null, 40, 100),
            'columns' => $columns, 'share' => self::range($input['share'] ?? null, 20, 80),
            'gap' => self::range($input['gap'] ?? null, 0, 30),
            'align' => $align, 'columnsWidth' => $columnsWidth,
            'paddingTop' => isset($input['paddingTop']) ? self::range($input['paddingTop'], 0, DesignSpacing::MAX_PIXELS) : null,
            'paddingBottom' => isset($input['paddingBottom']) ? self::range($input['paddingBottom'], 0, DesignSpacing::MAX_PIXELS) : null,
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
