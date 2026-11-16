<?php

namespace app\Core;

use app\Models\Navigation;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class Design
{
    public const REGIONS = [
        'header_after' => 'ヘッダー直下',
        'footer_before' => 'フッター直前',
    ];
    public const WIDGETS = [
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

    public static function pageLayout(int $id): array
    {
        $preview = self::$preview['page'] ?? null;
        return ($preview['id'] ?? null) === $id ? $preview['layout'] : PageLayout::settings($id);
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
        $stored = self::stored();
        $navigation = Navigation::all();
        $result = [];
        foreach ($themes as $theme) {
            $result[$theme] = self::state($theme, $stored, $navigation);
        }
        return $result;
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
                $defaults['widgets']['sidebar'] = self::widgets($layout['widgets']['sidebar'] ?? []);
            } elseif ($area === 'footer') {
                $footer = $layout['footer'] ?? null;
                if (!in_array($footer, [1, 2, 3], true)) {
                    throw new InvalidArgumentException('フッターのカラム数が正しくありません。');
                }
                $defaults['footer'] = $footer;
                foreach (['footer1', 'footer2', 'footer3'] as $key) {
                    $defaults['widgets'][$key] = self::widgets($layout['widgets'][$key] ?? []);
                }
            } elseif (isset(self::REGIONS[$area])) {
                $defaults['widgets'][$area] = self::widgets($layout['widgets'][$area] ?? []);
            }
        }
        $page = null;
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
            }
        }
        return [
            'theme' => $theme,
            'layout' => $defaults,
            'menus' => [
                'header' => self::menu($input['menus']['header'] ?? null, 100),
                'footer' => self::menu($input['menus']['footer'] ?? null, 100),
            ],
            'page' => $page,
        ];
    }

    public static function publish(array $state, string $revision): ?array
    {
        $pdo = Database::connect();
        $table = Database::table('site_design');
        $pdo->beginTransaction();
        try {
            $saved = self::stored(true);
            $current = self::state($state['theme'], $saved, Navigation::all(true));
            if (!hash_equals($current['revision'], $revision)) {
                $pdo->rollBack();
                return null;
            }

            $page = $state['page'] ?? null;
            if ($page !== null) {
                $currentPage = PageLayout::snapshot($page['id'], true);
                if ($currentPage === null || !hash_equals($currentPage['revision'], $page['revision'])) {
                    $pdo->rollBack();
                    return null;
                }
                if ($page['layout'] !== $currentPage['layout']) {
                    PageLayout::save($page['id'], $page['layout']);
                }
            }

            if ($current['menus']['header'] !== $state['menus']['header']) {
                Navigation::replace($state['menus']['header'], $current['menus']['header']);
            }
            $saved['footerMenu'] = $state['menus']['footer'];
            if (Theme::designAreas($state['theme'])) {
                $saved['themes'][$state['theme']] = $state['layout'];
            }
            $stmt = $pdo->prepare("UPDATE {$table} SET settings = ? WHERE id = 1");
            $stmt->execute([self::json($saved)]);
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

    private static function state(string $theme, array $stored, array $navigation): array
    {
        $state = [
            'theme' => $theme,
            'layout' => self::layout($theme, $stored),
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

    private static function layout(string $theme, array $stored): array
    {
        $defaults = self::defaults($theme);
        $saved = $stored['themes'][$theme] ?? [];
        $layout = array_replace($defaults, $saved);
        $layout['widgets'] = array_replace($defaults['widgets'], $saved['widgets'] ?? []);
        return $layout;
    }

    private static function defaults(string $theme): array
    {
        return [
            'header' => ['placement' => 'row', 'logo' => true, 'name' => true, 'menu' => true, 'search' => true],
            'columns' => 'none',
            'width' => 'standard',
            'footer' => 1,
            'widgets' => [
                'header_after' => [],
                'sidebar' => [],
                'footer_before' => [],
                'footer1' => [[
                    'type' => 'copyright',
                    'title' => '',
                    'text' => $theme === 'monoedge' ? 'NexaPress. Powered by MonoEdge.' : 'NexaPress. All rights reserved.',
                    'menu' => 'footer',
                ]],
                'footer2' => [],
                'footer3' => [],
            ],
        ];
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

    private static function widgets(mixed $widgets): array
    {
        if (!is_array($widgets) || count($widgets) > 12) {
            throw new InvalidArgumentException('ウィジェットは1か所に12個まで配置できます。');
        }
        $result = [];
        foreach ($widgets as $widget) {
            $type = $widget['type'] ?? null;
            $menu = $widget['menu'] ?? 'footer';
            if (!is_string($type) || !isset(self::WIDGETS[$type]) || !in_array($menu, ['header', 'footer'], true)) {
                throw new InvalidArgumentException('ウィジェットの種類を確認してください。');
            }
            $settings = [
                'type' => $type,
                'title' => self::text($widget['title'] ?? '', 80),
                'text' => self::text($widget['text'] ?? '', 1000),
                'menu' => $menu,
            ];
            if (isset(Navigation::TYPES[$type])) {
                if ($type === 'custom' && $settings['title'] === '') {
                    throw new InvalidArgumentException('任意リンクの表示名を入力してください。');
                }
                $settings += self::destination($widget);
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
