<?php

namespace app\Core;

use app\Models\Media;
use app\Models\Navigation;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class Design
{
    public const MAX_CATEGORY_LINKS = 100;
    public const MAX_WIDGETS = 12;
    public const MAX_HEADER_WIDGETS = 16;
    public const CHROME_WIDTHS = ['theme' => 'テーマの既定幅（現在の表示）'] + PageLayout::WIDTHS;
    public const DEFAULT_BACKGROUND = ['mode' => 'theme', 'color' => '#F5F6F8', 'media_id' => null];

    public const ALIGNMENTS = [
        'flex-start' => '左寄せ',
        'center' => '中央寄せ',
        'flex-end' => '右寄せ',
        'space-between' => '両端揃え',
    ];

    public static function widgetAreas(): array
    {
        $areas = self::REGIONS + ['sidebar' => 'サイドバー', 'header_above' => '上段'];
        foreach (['header' => 'ヘッダー本体', 'header_bottom' => '下段',
            'footer' => 'フッター上段', 'footer_bottom' => 'フッター下段'] as $prefix => $label) {
            for ($column = 1; $column <= 5; $column++) {
                $areas[$prefix . $column] = $label . '・' . $column . '列目';
            }
        }
        return $areas;
    }

    public static function headerRowDefaults(bool $stacked = false): array
    {
        return [
            'split' => $stacked,
            'top' => ['columns' => $stacked ? 1 : 3, 'align' => 'space-between'],
            'bottom' => ['columns' => 1, 'align' => 'center'],
            'positions' => [
                'brand' => ['row' => 'top', 'column' => 1],
                'menu' => ['row' => $stacked ? 'bottom' : 'top', 'column' => $stacked ? 1 : 2],
                'search' => ['row' => $stacked ? 'bottom' : 'top', 'column' => $stacked ? 1 : 3],
            ],
        ];
    }

    public static function isHeaderWidgetArea(string $area): bool
    {
        return $area === 'header_above' || preg_match('/^header(?:_bottom)?[1-5]$/D', $area) === 1;
    }

    public static function headerWidgetAreas(): array
    {
        return array_values(array_filter(array_keys(self::widgetAreas()), static fn(string $area): bool => self::isHeaderWidgetArea($area)));
    }

    public static function footerRowDefaults(): array
    {
        return ['split' => false, 'top' => ['align' => 'center'],
            'bottom' => ['columns' => 1, 'align' => 'center']];
    }

    public const REGIONS = [
        'header_after' => 'ヘッダー直下',
        'footer_before' => 'フッター直前',
    ];
    public const HEADER_WIDGETS = ['site_icon' => 'サイトアイコン', 'site_name' => 'サイト名'];
    public const WIDGETS = self::HEADER_WIDGETS + [
        'recent' => '新着記事',
        'categories' => 'カテゴリ一覧',
        'menu' => 'メニュー',
        'text' => 'テキスト',
        'search' => '検索',
        'copyright' => '著作権表記',
    ] + Navigation::TYPES;

    private static ?array $stored = null;
    private static ?array $preview = null;
    private static bool $draftPreview = false;

    public static function isPreview(): bool
    {
        return self::$preview !== null;
    }

    public static function preview(array $state, bool $draft): void
    {
        self::$preview = $state;
        self::$draftPreview = $draft;
    }

    public static function isDraftPreview(): bool
    {
        return self::$draftPreview;
    }

    public static function settings(): array
    {
        $theme = Theme::active();
        return self::$preview['layout'] ?? self::layout($theme, self::stored());
    }

    public static function background(): array
    {
        return self::$preview['background'] ?? self::stored()['background'] ?? self::DEFAULT_BACKGROUND;
    }

    public static function backgroundStylesheetUrl(): ?string
    {
        $background = self::background();
        $query = match ($background['mode']) {
            'color' => ['color' => $background['color']],
            'image' => $background['media_id'] !== null ? ['media' => $background['media_id']] : [],
            default => [],
        };
        return $query ? url('nexapress/assets/site-background') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : null;
    }

    public static function backgroundStylesheet(array $query): string
    {
        if (array_key_exists('color', $query)) {
            $color = DesignColor::normalize($query['color']);
            if ($color === null) {
                throw new InvalidArgumentException('背景色を確認してください。');
            }
            return "body { background-image: linear-gradient($color, $color); }\n";
        }

        $id = filter_var($query['media'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('背景画像を選んでください。');
        }
        $image = Media::find($id);
        if (($image['file_type'] ?? null) !== 'image') {
            return '';
        }
        $url = str_replace(['\\', '"', "\n", "\r", "\f"], ['\\\\', '\\"', '\\a ', '\\d ', '\\c '],
            public_url($image['file_path']));
        return "body { background-image: url(\"$url\"); background-position: center top; background-repeat: no-repeat; background-size: cover; }\n";
    }

    public static function pageLayout(int $id, string $kind = 'page'): array
    {
        if (self::$preview['reset'] ?? false) {
            return PageLayout::resolve([]);
        }
        $common = self::$preview['contentLayouts'] ?? self::contentLayouts(self::stored());
        $individual = PageLayout::snapshot($id, false, $kind)['overrides'] ?? PageLayout::EMPTY_OVERRIDES;
        foreach (self::$preview['content'] ?? [] as $entry) {
            if ($entry['kind'] === $kind && $entry['id'] === $id) {
                $individual = $entry['overrides'];
                break;
            }
        }
        return PageLayout::resolve([$common['all'], $common[$kind], $individual]);
    }

    public static function menuLinks(string $menu, bool $includeHomeFallback = true): array
    {
        if ($menu === 'footer') {
            return Navigation::resolve(self::$preview['menus']['footer'] ?? self::stored()['footerMenu'] ?? []);
        }

        $items = self::$preview['menus']['header'] ?? Navigation::all();
        $links = Navigation::resolve($items);
        return !$links && $includeHomeFallback ? [['label' => 'トップ', 'url' => url('')]] : $links;
    }

    public static function snapshot(string $theme): array
    {
        return self::state($theme, self::stored(), Navigation::all());
    }

    public static function snapshots(array $themes): array
    {
        return self::editorSnapshot($themes)['states'];
    }

    public static function editorSnapshot(array $themes): array
    {
        $stored = self::stored();
        $navigation = Navigation::all();
        $result = [];
        foreach ($themes as $theme) {
            $result[$theme] = self::state($theme, $stored, $navigation);
        }
        return ['states' => $result, 'resetRevision' => self::resetRevision($stored, $navigation)];
    }

    public static function resetPreview(mixed $theme): array
    {
        if (!is_string($theme) || !Theme::exists($theme)) {
            throw new InvalidArgumentException('テーマを選択してください。');
        }
        return self::state($theme, [], []) + ['reset' => true];
    }

    public static function validate(array $input): array
    {
        $theme = $input['theme'] ?? null;
        if (!is_string($theme) || !Theme::exists($theme)) {
            throw new InvalidArgumentException('テーマを選択してください。');
        }
        $caps = Theme::designAreas($theme);
        $defaults = self::defaults($theme);
        $layout = $input['layout'] ?? [];
        if (!is_array($layout)) {
            throw new InvalidArgumentException('レイアウトが正しくありません。');
        }
        $headerWidgets = $layout['headerWidgets'] ?? false;
        if (!is_bool($headerWidgets)) {
            throw new InvalidArgumentException('ヘッダーのウィジェット設定を確認してください。');
        }
        $defaults['headerWidgets'] = $headerWidgets;
        foreach ($caps as $area) {
            if ($area === 'header') {
                $placement = $layout['header']['placement'] ?? null;
                if (!in_array($placement, ['row', 'stack'], true)) {
                    throw new InvalidArgumentException('ヘッダーの配置が正しくありません。');
                }
                $defaults['header']['placement'] = $placement;
                foreach (['logo', 'name', 'menu', 'search'] as $key) {
                    $value = $layout['header'][$key] ?? null;
                    if (!is_bool($value)) {
                        throw new InvalidArgumentException('ヘッダーの表示設定が正しくありません。');
                    }
                    $defaults['header'][$key] = $value;
                }
            } elseif ($area === 'columns') {
                $columns = $layout['columns'] ?? null;
                if (!in_array($columns, ['none', 'left', 'right'], true)) {
                    throw new InvalidArgumentException('本文のカラム構成が正しくありません。');
                }
                $defaults['columns'] = $columns;
                $defaults['widgets']['sidebar'] = self::widgets($layout['widgets']['sidebar'] ?? [], 'sidebar');
            } elseif ($area === 'footer') {
                $footer = $layout['footer'] ?? null;
                if (!in_array($footer, [1, 2, 3, 4, 5], true)) {
                    throw new InvalidArgumentException('フッターのカラム数が正しくありません。');
                }
                $defaults['footer'] = $footer;
                foreach (['footer1', 'footer2', 'footer3', 'footer4', 'footer5'] as $key) {
                    $defaults['widgets'][$key] = self::widgets($layout['widgets'][$key] ?? [], $key);
                }
            } elseif ($area === 'header_rows' || $area === 'footer_rows') {
                $kind = $area === 'header_rows' ? 'header' : 'footer';
                $defaults[$kind . 'Rows'] = self::rows($layout[$kind . 'Rows'] ?? null, $kind);
                if ($kind === 'header') {
                    foreach (['headerAbove', 'headerBelow'] as $key) {
                        $enabled = $layout[$key] ?? false;
                        if (!is_bool($enabled)) {
                            throw new InvalidArgumentException('ヘッダーの段構成を確認してください。');
                        }
                        $defaults[$key] = $enabled;
                    }
                    $defaults['widgets']['header_above'] = self::widgets($layout['widgets']['header_above'] ?? [], 'header_above');
                    if ($defaults['headerRows'] !== null) {
                        $defaults['headerBelow'] = false;
                        $defaults['header']['placement'] = $defaults['headerRows']['split'] ? 'stack' : 'row';
                    }
                }
                foreach (array_keys(self::widgetAreas()) as $key) {
                    if (($kind === 'header' && preg_match('/^header(?:_bottom)?[1-5]$/D', $key)) ||
                        ($kind === 'footer' && preg_match('/^footer_bottom[1-5]$/D', $key))) {
                        $defaults['widgets'][$key] = self::widgets($layout['widgets'][$key] ?? [], $key);
                    }
                }
            } elseif (isset(self::REGIONS[$area])) {
                $defaults['widgets'][$area] = self::widgets($layout['widgets'][$area] ?? [], $area);
            }
        }
        if (in_array('header', $caps, true) && !in_array('header_rows', $caps, true)) {
            foreach (range(1, 5) as $column) {
                $key = 'header' . $column;
                $defaults['widgets'][$key] = self::widgets($layout['widgets'][$key] ?? [], $key);
            }
        }
        foreach (['header', 'footer'] as $kind) {
            if (!in_array($kind, $caps, true)) {
                continue;
            }
            $key = $kind . 'Width';
            $width = $layout[$key] ?? 'theme';
            if (!is_string($width) || !isset(self::CHROME_WIDTHS[$width])) {
                throw new InvalidArgumentException('ヘッダー・フッターの幅を選択してください。');
            }
            $defaults[$key] = $width;
        }
        $spacing = DesignSpacing::validate($layout['spacing'] ?? []);
        foreach (DesignSpacing::AREAS as $region => $area) {
            if (in_array($area, $caps, true)) {
                $defaults['spacing'][$region] = $spacing[$region];
            }
        }
        $page = null;
        $content = [];
        $common = self::contentLayouts(self::stored());
        if (array_key_exists('contentLayouts', $input)) {
            if (!is_array($input['contentLayouts']) ||
                array_diff(array_keys($input['contentLayouts']), array_keys(PageLayout::SCOPES))) {
                throw new InvalidArgumentException('共通レイアウトを確認してください。');
            }
            foreach ($input['contentLayouts'] as $scope => $patch) {
                if (!is_array($patch)) {
                    throw new InvalidArgumentException('共通レイアウトを確認してください。');
                }
                $common[$scope] = PageLayout::validateOverrides($patch);
            }
        }
        foreach (['page', 'post'] as $kind) {
            PageLayout::resolve([$common['all'], $common[$kind]]);
        }
        $entries = $input['content'] ?? [];
        if (!is_array($entries)) {
            throw new InvalidArgumentException('ページごとの設定を確認してください。');
        }
        foreach ($entries as $entry) {
            $kind = $entry['kind'] ?? null;
            $id = $entry['id'] ?? null;
            $patch = $entry['overrides'] ?? null;
            $entryRevision = $entry['revision'] ?? null;
            if (!in_array($kind, ['page', 'post'], true) || !is_int($id) || $id < 1 ||
                !is_array($patch) || !is_string($entryRevision) ||
                !preg_match('/^[a-f0-9]{64}$/D', $entryRevision) || isset($content[$kind . ':' . $id])) {
                throw new InvalidArgumentException('固定ページまたは投稿を選び直してください。');
            }
            $patch = PageLayout::validateOverrides($patch);
            PageLayout::resolve([$common['all'], $common[$kind], $patch]);
            $content[$kind . ':' . $id] = [
                'kind' => $kind, 'id' => $id, 'overrides' => $patch, 'revision' => $entryRevision,
            ];
        }
        if (in_array('page_layout', $caps, true)) {
            $width = $layout['width'] ?? 'standard';
            if (!is_string($width) || !isset(PageLayout::WIDTHS[$width])) {
                throw new InvalidArgumentException('本文の幅を選択してください。');
            }
            $defaults['width'] = $width;
            $inputPage = $input['page'] ?? null;
            if ($inputPage !== null) {
                $id = $inputPage['id'] ?? null;
                $settings = $inputPage['layout'] ?? null;
                $pageRevision = $inputPage['revision'] ?? null;
                if (!is_int($id) || $id < 1 || !is_array($settings) ||
                    !is_string($pageRevision) || !preg_match('/^[a-f0-9]{64}$/D', $pageRevision)) {
                    throw new InvalidArgumentException('固定ページを選び直してください。');
                }
                $page = ['id' => $id, 'layout' => PageLayout::validate($settings), 'revision' => $pageRevision];
                if (isset($content['page:' . $id])) {
                    throw new InvalidArgumentException('同じページの設定が重複しています。');
                }
                $content['page:' . $id] = [
                    'id' => $id, 'kind' => 'page', 'overrides' => PageLayout::overrides($page['layout']),
                    'revision' => $pageRevision,
                ];
            }
        }
        return [
            'theme' => $theme,
            'layout' => self::headerWidgetLayout($defaults),
            'background' => self::validateBackground($input['background'] ?? self::background()),
            'menus' => [
                'header' => self::menu($input['menus']['header'] ?? null, 100),
                'footer' => self::menu($input['menus']['footer'] ?? null, 100),
            ],
            'page' => $page,
            'contentLayouts' => $common,
            'content' => array_values($content),
        ];
    }

    public static function publish(array $state, string $revision): ?array
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $saved = self::stored(true);
            $current = self::state($state['theme'], $saved, Navigation::all(true));
            if (!hash_equals($current['revision'], $revision)) {
                $pdo->rollBack();
                return null;
            }

            $entries = $state['content'] ?? [];
            usort($entries, static fn(array $a, array $b): int =>
                [$a['kind'], $a['id']] <=> [$b['kind'], $b['id']]);
            foreach ($entries as $entry) {
                $currentPage = PageLayout::snapshot($entry['id'], true, $entry['kind']);
                if ($currentPage === null || !hash_equals($currentPage['revision'], $entry['revision'])) {
                    $pdo->rollBack();
                    return null;
                }
                if ($entry['overrides'] !== $currentPage['overrides']) {
                    PageLayout::save($entry['id'], $entry['overrides'], $entry['kind']);
                }
            }

            if ($current['menus']['header'] !== $state['menus']['header']) {
                Navigation::replace($state['menus']['header'], $current['menus']['header']);
            }
            $saved['footerMenu'] = $state['menus']['footer'];
            $saved['contentLayouts'] = $state['contentLayouts'];
            $saved['background'] = $state['background'];
            if (Theme::designAreas($state['theme'])) {
                $saved['themes'][$state['theme']] = $state['layout'];
            }
            $saved = self::saveSettings($saved);
            $pdo->commit();
            self::$stored = $saved;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        if ($current['menus'] !== $state['menus']) {
            do_action('navigation_saved');
        }
        do_action('design_published', $state['theme']);
        return self::snapshot($state['theme']);
    }

    public static function reset(string $revision): bool
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $saved = self::stored(true);
            $navigation = Navigation::all(true);
            if (!hash_equals(self::resetRevision($saved, $navigation), $revision)) {
                $pdo->rollBack();
                return false;
            }

            $menusChanged = $navigation || !empty($saved['footerMenu']);
            Navigation::replace([], $navigation);
            PageLayout::resetAll();
            unset($saved['themes'], $saved['footerMenu'], $saved['contentLayouts'], $saved['background']);
            $saved = self::saveSettings($saved);
            $pdo->commit();
            self::$stored = $saved;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        if ($menusChanged) {
            do_action('navigation_saved');
        }
        do_action('design_published', Theme::active());
        return true;
    }

    private static function resetRevision(array $stored, array $navigation): string
    {
        return hash('sha256', self::json([$stored, $navigation]));
    }

    private static function saveSettings(array $settings): array
    {
        // Track publications that change only individual page or post layouts.
        $settings['publicationId'] = bin2hex(random_bytes(16));
        $table = Database::table('site_design');
        $stmt = Database::connect()->prepare("UPDATE {$table} SET settings = ? WHERE id = 1");
        $stmt->execute([self::json($settings)]);
        return $settings;
    }

    private static function state(string $theme, array $stored, array $navigation): array
    {
        $state = [
            'theme' => $theme,
            'layout' => self::layout($theme, $stored),
            'background' => $stored['background'] ?? self::DEFAULT_BACKGROUND,
            'contentLayouts' => self::contentLayouts($stored),
            'menus' => [
                'header' => array_map(static fn(array $row): array => [
                    'id' => (int)$row['id'], 'label' => $row['label'], 'type' => $row['type'],
                    'reference_id' => $row['reference_id'] === null ? null : (int)$row['reference_id'],
                    'url' => $row['url'],
                ], $navigation),
                'footer' => $stored['footerMenu'] ?? [],
            ],
        ];
        $state['revision'] = hash('sha256', self::json($state));
        return $state;
    }

    private static function stored(bool $lock = false): array
    {
        if (self::$stored !== null && !$lock) {
            return self::$stored;
        }
        $table = Database::table('site_design');
        $query = "SELECT settings FROM {$table} WHERE id = 1" . ($lock ? ' FOR UPDATE' : '');
        $json = Database::connect()->query($query)->fetchColumn();
        if ($json === false) {
            throw new RuntimeException('デザイン設定を読み込めませんでした。');
        }
        return self::$stored = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    }

    private static function contentLayouts(array $stored): array
    {
        $result = [];
        foreach (PageLayout::SCOPES as $scope => $label) {
            $result[$scope] = PageLayout::overrides($stored['contentLayouts'][$scope] ?? []);
        }
        return $result;
    }

    private static function layout(string $theme, array $stored): array
    {
        $defaults = self::defaults($theme);
        $saved = $stored['themes'][$theme] ?? [];
        $layout = array_replace($defaults, $saved);
        $layout['widgets'] = array_replace($defaults['widgets'], $saved['widgets'] ?? []);
        $layout['spacing'] = DesignSpacing::validate($saved['spacing'] ?? []);
        return self::headerWidgetLayout($layout);
    }

    private static function headerWidgetLayout(array $layout): array
    {
        if (($layout['headerWidgets'] ?? false) === true) {
            return $layout;
        }
        // Older designs store these parts outside the widget lists. Convert once,
        // keeping their order, visibility and row/column destinations.
        $rows = $layout['headerRows'];
        $positions = $rows['positions'] ?? self::headerRowDefaults()['positions'];
        if ($rows === null && $layout['header']['placement'] === 'stack') {
            $positions = array_fill_keys(['brand', 'menu', 'search'], ['row' => 'top', 'column' => 1]);
        }
        $converted = [];
        foreach (['logo' => ['site_icon', 'brand'], 'name' => ['site_name', 'brand'],
            'menu' => ['menu', 'menu'], 'search' => ['search', 'search']] as $flag => [$type, $part]) {
            if (!$layout['header'][$flag]) {
                continue;
            }
            $position = $positions[$part];
            $area = ($position['row'] === 'bottom' ? 'header_bottom' : 'header') . $position['column'];
            $converted[$area][] = ['type' => $type, 'title' => '', 'text' => '', 'menu' => 'header'];
        }
        foreach ($converted as $area => $widgets) {
            $layout['widgets'][$area] = array_merge($widgets, $layout['widgets'][$area]);
            if (count($layout['widgets'][$area]) > self::MAX_HEADER_WIDGETS) {
                throw new InvalidArgumentException('ヘッダーのウィジェット数を確認してください。');
            }
        }
        $layout['headerWidgets'] = true;
        return $layout;
    }

    private static function defaults(string $theme): array
    {
        $widgets = array_fill_keys(array_keys(self::widgetAreas()), []);
        $widgets['footer1'] = [[
            'type' => 'copyright', 'title' => '',
            'text' => $theme === 'monoedge' ? 'NexaPress. Powered by MonoEdge.' : 'NexaPress. All rights reserved.',
            'menu' => 'footer',
        ]];
        return [
            'header' => ['placement' => 'row', 'logo' => true, 'name' => true, 'menu' => true, 'search' => true],
            'headerRows' => null,
            'headerAbove' => false,
            'headerBelow' => false,
            'headerWidth' => 'theme',
            'columns' => 'none',
            'width' => 'standard',
            'footer' => 1,
            'footerRows' => null,
            'footerWidth' => 'theme',
            'spacing' => DesignSpacing::defaults(),
            'widgets' => $widgets,
        ];
    }

    private static function validateBackground(mixed $background): array
    {
        if (!is_array($background) || !in_array($background['mode'] ?? null, ['theme', 'color', 'image'], true)) {
            throw new InvalidArgumentException('サイト全体の背景を選んでください。');
        }
        $color = DesignColor::normalize($background['color'] ?? null);
        $media = $background['media_id'] ?? null;
        if ($color === null || ($media !== null && (!is_int($media) || $media < 1))) {
            throw new InvalidArgumentException('サイト全体の背景色・画像を確認してください。');
        }
        return ['mode' => $background['mode'], 'color' => $color, 'media_id' => $media];
    }

    private static function rows(mixed $input, string $kind): ?array
    {
        if ($input === null) {
            return null;
        }
        $keys = $kind === 'header' ? ['split', 'top', 'bottom', 'positions'] : ['split', 'top', 'bottom'];
        if (!is_array($input) || array_diff(array_keys($input), $keys) || !is_bool($input['split'] ?? null)) {
            throw new InvalidArgumentException('ヘッダー・フッターの段構成を確認してください。');
        }
        $result = ['split' => $input['split']];
        foreach (['top', 'bottom'] as $row) {
            $value = $input[$row] ?? null;
            $hasColumns = $kind === 'header' || $row === 'bottom';
            if (!is_array($value) || array_diff(array_keys($value), $hasColumns ? ['columns', 'align'] : ['align']) ||
                !in_array($value['align'] ?? null, array_keys(self::ALIGNMENTS), true) ||
                ($hasColumns && !in_array($value['columns'] ?? null, [1, 2, 3, 4, 5], true))) {
                throw new InvalidArgumentException('各段のカラム数・配置を確認してください。');
            }
            $result[$row] = $hasColumns
                ? ['columns' => $value['columns'], 'align' => $value['align']]
                : ['align' => $value['align']];
        }
        if ($kind === 'header') {
            $positions = $input['positions'] ?? null;
            if (!is_array($positions) || array_diff(array_keys($positions), ['brand', 'menu', 'search'])) {
                throw new InvalidArgumentException('ヘッダーの中身の配置を確認してください。');
            }
            foreach (['brand', 'menu', 'search'] as $part) {
                $position = $positions[$part] ?? null;
                if (!is_array($position) || array_diff(array_keys($position), ['row', 'column']) ||
                    !in_array($position['row'] ?? null, ['top', 'bottom'], true) ||
                    !is_int($position['column'] ?? null) || $position['column'] < 1 ||
                    $position['column'] > $result[$position['row']]['columns'] ||
                    ($position['row'] === 'bottom' && !$result['split'])) {
                    throw new InvalidArgumentException('ヘッダーの表示場所を選び直してください。');
                }
                $result['positions'][$part] = $position;
            }
        }
        return $result;
    }

    private static function menu(mixed $items, int $limit): array
    {
        if (!is_array($items) || count($items) > $limit) {
            throw new InvalidArgumentException('メニューは100項目まで登録できます。');
        }
        $result = [];
        $ids = [];
        foreach ($items as $item) {
            $label = self::text($item['label'] ?? null, 255);
            $type = $item['type'] ?? null;
            $id = $item['id'] ?? null;
            if ($label === '' || !is_string($type) || !isset(Navigation::TYPES[$type]) ||
                ($id !== null && (!is_int($id) || $id < 1 || isset($ids[$id])))) {
                throw new InvalidArgumentException('メニュー項目の表示名またはリンク先を確認してください。');
            }
            if ($id !== null) {
                $ids[$id] = true;
            }
            $result[] = ['id' => $id, 'label' => $label, 'type' => $type] + self::destination($item);
        }
        return $result;
    }

    private static function widgets(mixed $widgets, string $area): array
    {
        $isHeader = self::isHeaderWidgetArea($area);
        $limit = $isHeader ? self::MAX_HEADER_WIDGETS : self::MAX_WIDGETS;
        if (!is_array($widgets) || count($widgets) > $limit) {
            throw new InvalidArgumentException('ウィジェットは1か所に' . $limit . '個まで配置できます。');
        }
        $result = [];
        foreach ($widgets as $widget) {
            if (!is_array($widget)) {
                throw new InvalidArgumentException('ウィジェットを確認してください。');
            }
            $type = $widget['type'] ?? null;
            $menu = $widget['menu'] ?? 'footer';
            if (!is_string($type) || !isset(self::WIDGETS[$type]) || !in_array($menu, ['header', 'footer'], true)) {
                throw new InvalidArgumentException('ウィジェットの種類を確認してください。');
            }
            if (isset(self::HEADER_WIDGETS[$type]) && !$isHeader) {
                throw new InvalidArgumentException('サイトアイコン・サイト名はヘッダー内に配置してください。');
            }
            $settings = [
                'type' => $type,
                'title' => self::text($widget['title'] ?? '', 255),
                'text' => self::text($widget['text'] ?? '', 1000),
                'menu' => $menu,
            ];
            if (isset(Navigation::TYPES[$type])) {
                if ($type === 'custom' && $settings['title'] === '') {
                    throw new InvalidArgumentException('任意リンクの表示名を入力してください。');
                }
                if ($type === 'category' && array_key_exists('reference_ids', $widget)) {
                    $ids = $widget['reference_ids'];
                    if (!is_array($ids) || !$ids || count($ids) > self::MAX_CATEGORY_LINKS ||
                        array_keys($ids) !== array_keys(array_values($ids))) {
                        throw new InvalidArgumentException('カテゴリは1〜100件で選択してください。');
                    }
                    foreach ($ids as $id) {
                        if (!is_int($id) || $id < 1) {
                            throw new InvalidArgumentException('カテゴリを選び直してください。');
                        }
                    }
                    if (count(array_unique($ids)) !== count($ids)) {
                        throw new InvalidArgumentException('同じカテゴリを重複して選択できません。');
                    }
                    $settings += ['reference_id' => $ids[0], 'reference_ids' => $ids, 'url' => ''];
                } else {
                    $settings += self::destination($widget);
                }
            }
            $result[] = $settings;
        }
        return $result;
    }

    private static function destination(array $item): array
    {
        if ($item['type'] === 'custom') {
            $url = $item['url'] ?? null;
            if (is_string($url) && Navigation::safeUrl($url)) {
                return ['reference_id' => null, 'url' => $url];
            }
        } else {
            $id = $item['reference_id'] ?? null;
            if (is_int($id) && $id > 0) {
                return ['reference_id' => $id, 'url' => ''];
            }
        }
        throw new InvalidArgumentException('リンク先を確認してください。');
    }

    private static function text(mixed $value, int $limit): string
    {
        if (!is_string($value) || nx_text_length($value) > $limit) {
            throw new InvalidArgumentException('入力文字数を確認してください。');
        }
        return trim($value);
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
