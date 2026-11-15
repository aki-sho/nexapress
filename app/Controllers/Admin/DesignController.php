<?php

namespace app\Controllers\Admin;

use app\Controllers\HomeController;
use app\Core\Controller;
use app\Core\Csrf;
use app\Core\Design;
use app\Core\Theme;
use app\Models\Category;
use app\Models\Page;
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
        $states = Design::snapshots(array_column($themes, 'id'));
        foreach ($themes as $theme) {
            $id = $theme['id'];
            $catalog[$id] = [
                'name' => $theme['name'], 'areas' => Theme::designAreas($id),
                'state' => $states[$id],
            ];
        }
        $pages = array_map(static fn(array $page): array => [
            'id' => (int)$page['id'],
            'label' => $page['title'] . ($page['status'] === 'published' ? '' : '（非公開）'),
        ], Page::all());
        $categories = array_map(static fn(array $category): array => [
            'id' => (int)$category['id'], 'label' => $category['name'],
        ], Category::all());

        $this->view('admin/design', ['designData' => [
            'active' => $active, 'themes' => $catalog, 'pages' => $pages, 'categories' => $categories,
            'widgets' => Design::WIDGETS, 'token' => Csrf::token(),
            'preview' => url('admin/design/preview'), 'publish' => url('admin/design/publish'),
        ]]);
    }

    public function preview(): void
    {
        header('Cache-Control: no-store, private');
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        $draft = ($_POST['mode'] ?? '') !== 'published';
        try {
            $state = $draft ? $this->readDesign() : Design::snapshot(Theme::active());
        } catch (InvalidArgumentException | JsonException $exception) {
            $this->respond(['error' => $exception->getMessage()], 422);
            return;
        }
        Theme::preview($state['theme']);
        Design::preview($state, $draft);
        header('Content-Type: text/html; charset=UTF-8');
        (new HomeController())->index();
    }

    public function publish(): void
    {
        header('Cache-Control: no-store, private');
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        try {
            $state = $this->readDesign();
            $revision = $_POST['revision'] ?? '';
            if (!is_string($revision) || !preg_match('/^[a-f0-9]{64}$/D', $revision)) {
                throw new InvalidArgumentException('編集画面を開き直してください。');
            }
            $saved = Design::publish($state, $revision);
        } catch (InvalidArgumentException | JsonException $exception) {
            $this->respond(['error' => $exception->getMessage()], 422);
            return;
        } catch (Throwable $exception) {
            $this->respond(['error' => 'デザインを公開できませんでした。DBの状態を確認してください。'], 500);
            return;
        }
        if ($saved === null) {
            $this->respond(['error' => '別の操作でデザインが更新されました。画面を開き直してください。'], 409);
            return;
        }
        $this->respond(['states' => Design::snapshots(array_column(Theme::all(), 'id'))]);
    }

    private function readDesign(): array
    {
        $json = $_POST['design'] ?? null;
        if (!is_string($json) || strlen($json) > 900000) {
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
