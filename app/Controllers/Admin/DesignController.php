<?php

namespace app\Controllers\Admin;

use app\Controllers\HomeController;
use app\Core\Controller;
use app\Core\Csrf;
use app\Core\Design;
use app\Core\DesignSpacing;
use app\Core\DesignFrame;
use app\Core\HeaderWidgetSize;
use app\Core\PageLayout;
use app\Core\Theme;
use app\Models\Category;
use app\Models\Media;
use app\Models\Navigation;
use app\Models\Page;
use app\Models\Post;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class DesignController extends Controller
{
    public function index(): void
    {
        header('Cache-Control: no-store, private');
        $active = Theme::active();
        $catalog = [];
        $themes = Theme::all();
        $snapshot = Design::editorSnapshot(array_column($themes, 'id'));
        foreach ($themes as $theme) {
            $id = $theme['id'];
            $catalog[$id] = [
                'name' => $theme['name'], 'areas' => Theme::designAreas($id),
                'state' => $snapshot['states'][$id],
            ];
        }
        $contentChoices = static fn(array $item): array => [
            'id' => (int)$item['id'],
            'name' => $item['title'],
            'published' => $item['status'] === 'published',
            'label' => $item['title'] . ($item['status'] === 'published' ? '' : '（非公開）'),
        ];

        $general = general_config();
        $pages = array_map($contentChoices, Page::all());
        $posts = array_map($contentChoices, Post::navigationChoices());
        $legacyTarget = null;
        $requested = $_GET['layout'] ?? null;
        if ($requested !== null) {
            if (is_string($requested) && preg_match('/\A(page|post):([1-9][0-9]*)\z/', $requested, $match)) {
                [, $kind, $id] = $match;
                $choices = $kind === 'page' ? $pages : $posts;
                if (in_array('page_layout', $catalog[$active]['areas'], true) &&
                    in_array((int)$id, array_column($choices, 'id'), true) &&
                    Design::pageLayout((int)$id, $kind)['sections']) {
                    $legacyTarget = ['kind' => $kind, 'id' => $id];
                }
            }
            if ($legacyTarget === null) {
                http_response_code(404);
                echo '対象のセクションが見つかりません。';
                return;
            }
        }
        $this->view('admin/design', ['embedded' => $legacyTarget !== null, 'designData' => [
            'legacyTarget' => $legacyTarget,
            'active' => $active, 'themes' => $catalog,
            'targets' => [
                'page' => $pages,
                'category' => array_map(static fn(array $category): array => [
                    'id' => (int)$category['id'], 'label' => $category['name'],
                ], Category::all()),
                'post' => $posts,
            ],
            'linkTypes' => Navigation::TYPES, 'widgets' => Design::WIDGETS,
            'regions' => Design::REGIONS, 'token' => Csrf::token(),
            'resetRevision' => $snapshot['resetRevision'],
            'widgetAreas' => Design::widgetAreas(), 'alignments' => Design::ALIGNMENTS,
            'regionDirections' => Design::REGION_DIRECTIONS, 'regionWidths' => Design::REGION_WIDTHS,
            'regionCrossAlignments' => Design::REGION_CROSS_ALIGNMENTS,
            'regionRows' => Design::REGION_ROWS,
            'regionWidgetAreas' => array_combine(array_keys(Design::REGIONS),
                array_map([Design::class, 'regionWidgetAreas'], array_keys(Design::REGIONS))),
            'headerWidgetAreas' => Design::headerWidgetAreas(),
            'headerWidgetTypes' => array_keys(Design::HEADER_WIDGETS),
            'headerWidgetSizes' => HeaderWidgetSize::OPTIONS,
            'widgetLimit' => Design::MAX_WIDGETS, 'headerWidgetLimit' => Design::MAX_HEADER_WIDGETS,
            'chromeWidths' => Design::CHROME_WIDTHS,
            'spacingLimit' => DesignSpacing::MAX_PIXELS,
            'frameOptions' => [
                'modes' => DesignFrame::MODES, 'styles' => DesignFrame::STYLES,
                'lineDefault' => DesignFrame::LINE_DEFAULT, 'limit' => DesignFrame::MAX_PIXELS,
                'shadowDefault' => DesignFrame::SHADOW_DEFAULT, 'cardSelector' => DesignFrame::CARD_SELECTOR,
            ],
            'categoryLimit' => Design::MAX_CATEGORY_LINKS,
            'chromeDefaults' => [
                'header' => Design::headerRowDefaults(),
                'stackedHeader' => Design::headerRowDefaults(true),
                'footer' => Design::footerRowDefaults(),
            ],
            'preview' => url('admin/design/preview'), 'publish' => url('admin/design/publish'), 'media' => url('admin/media'),
            'imageUpload' => url('admin/media/upload-image'),
            'pageLayout' => [
                'url' => url('admin/design/page-layout'),
                'postUrl' => url('admin/design/post-layout'),
                'home' => ($general['homepage_mode'] ?? '') === 'page' ? (int)($general['homepage_page_id'] ?? 0) : 0,
                'widths' => PageLayout::WIDTHS, 'elements' => PageLayout::ELEMENTS,
                'scopes' => PageLayout::SCOPES, 'sectionDefaults' => PageLayout::sectionDefaults(),
                'images' => array_map(static fn(array $image): array => [
                    'id' => (int)$image['id'], 'label' => $image['title'],
                ], Media::images()),
                'sectionLimit' => PageLayout::MAX_SECTIONS, 'elementLimit' => PageLayout::MAX_ELEMENTS,
            ],
        ]]);
    }

    public function pageLayout(string $id): void
    {
        $this->layoutSnapshot($id, 'page');
    }

    public function postLayout(string $id): void
    {
        $this->layoutSnapshot($id, 'post');
    }

    private function layoutSnapshot(string $id, string $kind): void
    {
        header('Cache-Control: no-store, private');
        $page = ctype_digit($id) ? PageLayout::snapshot((int)$id, false, $kind) : null;
        if ($page === null) {
            $this->respond(['error' => '固定ページまたは投稿が見つかりません。'], 404);
            return;
        }
        $page['images'] = array_map(static fn(array $image): array => [
            'id' => (int)$image['id'], 'label' => $image['title'],
        ], Media::images());
        $this->respond($page);
    }

    public function preview(): void
    {
        header('Cache-Control: no-store, private');
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        $draft = ($_POST['mode'] ?? '') !== 'published';
        $reset = $draft && ($_POST['reset'] ?? null) === '1';
        try {
            if ($reset) {
                $state = Design::resetPreview($_POST['theme'] ?? null);
            } else {
                $state = $draft ? $this->readDesign() : Design::snapshot(Theme::active());
            }
        } catch (InvalidArgumentException | JsonException $exception) {
            $this->respond(['error' => $exception->getMessage()], 422);
            return;
        }
        Theme::preview($state['theme']);
        Design::preview($state, $draft && !$reset);
        header('Content-Type: text/html; charset=UTF-8');
        $kind = $_POST['content_kind'] ?? 'page';
        if (!in_array($kind, ['page', 'post'], true)) {
            $this->respond(['error' => '表示するページを選択してください。'], 422);
            return;
        }
        $pageId = filter_var($_POST['content_id'] ?? $state['page']['id'] ?? $_POST['page_id'] ?? '',
            FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($pageId) {
            $page = $kind === 'post' ? Post::find($pageId) : Page::find($pageId);
            if (!$page || $page['deleted_at'] !== null || (!$draft && $page['status'] !== 'published')) {
                $this->respond(['error' => '表示できる公開ページがありません。編集中の表示で確認できます。'], 404);
                return;
            }
            if ($kind === 'post') {
                $this->view('post-detail', ['post' => $page, 'isPreview' => $page['status'] !== 'published']);
                return;
            }
            $settings = general_config();
            $this->view('page-detail', [
                'page' => $page,
                'isHomepage' => ($settings['homepage_mode'] ?? '') === 'page'
                    && (int)($settings['homepage_page_id'] ?? 0) === $pageId,
            ]);
            return;
        }
        (new HomeController())->index();
    }

    public function publish(): void
    {
        header('Cache-Control: no-store, private');
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        $reset = ($_POST['reset'] ?? null) === '1';
        try {
            $revision = $_POST['revision'] ?? '';
            if (!is_string($revision) || !preg_match('/^[a-f0-9]{64}$/D', $revision)) {
                throw new InvalidArgumentException('編集画面を開き直してください。');
            }
            if ($reset) {
                $saved = Design::reset($revision);
            } else {
                $state = $this->readDesign();
                $saved = Design::publish($state, $revision) !== null;
            }
        } catch (InvalidArgumentException | JsonException $exception) {
            $this->respond(['error' => $exception->getMessage()], 422);
            return;
        } catch (Throwable $exception) {
            $this->respond(['error' => 'デザインを公開できませんでした。DBの状態を確認してください。'], 500);
            return;
        }
        if (!$saved) {
            $this->respond(['error' => '別の操作でデザインが更新されました。画面を開き直してください。'], 409);
            return;
        }
        if ($reset) {
            $this->respond(['reset' => true]);
            return;
        }
        $this->respond(Design::editorSnapshot(array_column(Theme::all(), 'id')) + [
            'page' => $state['page'] === null ? null : PageLayout::snapshot($state['page']['id']),
            'content' => array_map(static fn(array $entry): ?array =>
                PageLayout::snapshot($entry['id'], false, $entry['kind']), $state['content']),
        ]);
    }

    private function readDesign(): array
    {
        $json = $_POST['design'] ?? null;
        if (!is_string($json) || strlen($json) > 2097152) {
            throw new InvalidArgumentException('デザインの入力内容を確認してください。');
        }
        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new InvalidArgumentException('デザインの入力内容を確認してください。');
        }
        return Design::validate($data);
    }

    private function respond(array $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
