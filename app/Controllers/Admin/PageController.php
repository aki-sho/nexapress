<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Csrf;
use app\Core\Database;
use app\Core\PageValidator;
use app\Models\Page;
use app\Models\PageRevision;
use Throwable;

class PageController extends Controller
{
    public function index(): void
    {
        $trash = ($_GET['trash'] ?? '') === '1';
        $notice = $_SESSION['page_notice'] ?? null;
        unset($_SESSION['page_notice']);
        $this->view('admin/pages', ['pages' => Page::all($trash), 'trash' => $trash, 'notice' => $notice]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(int $id): void
    {
        if ($page = $this->page($id)) {
            $this->form($page);
        }
    }

    public function store(): void
    {
        $this->save(null);
    }

    public function update(int $id): void
    {
        $this->save($id);
    }

    private function save(?int $id): void
    {
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        $page = $id === null ? null : $this->page($id);
        if ($id !== null && !$page) {
            return;
        }
        $validation = PageValidator::validate($_POST, $id);
        $data = $validation['data'];
        $formPage = array_merge($page ?? [], $data);
        if ($validation['errors']) {
            $this->form($formPage, implode(' ', $validation['errors']));
            return;
        }
        $pdo = Database::connect();
        try {
            $pdo->beginTransaction();
            if ($page) {
                PageRevision::create($page, (int)Auth::user()['id']);
                Page::update($id, $data);
            } else {
                $id = Page::create($data + ['user_id' => (int)Auth::user()['id']]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->form($formPage, '保存できませんでした。スラッグの重複などを確認してください。');
            return;
        }
        do_action('page_saved', $id);
        nx_editor_saved();
        $this->notice('固定ページを保存しました。');
        redirect_to('admin/pages/edit/' . $id);
    }

    public function preview(int $id): void
    {
        if ($page = $this->page($id)) {
            header('X-Robots-Tag: noindex, nofollow');
            header('Cache-Control: no-store, private');
            $this->view('page-detail', ['page' => $page, 'isPreview' => true]);
        }
    }

    public function revisions(int $id): void
    {
        if ($page = $this->page($id)) {
            $notice = $_SESSION['page_notice'] ?? null;
            unset($_SESSION['page_notice']);
            $this->view('admin/page-revisions', [
                'page' => $page, 'revisions' => PageRevision::allForPage($id), 'notice' => $notice,
            ]);
        }
    }

    public function restoreRevision(int $id, int $revisionId): void
    {
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        $page = $this->page($id);
        if (!$page) {
            return;
        }
        $revision = PageRevision::find($revisionId, $id);
        if (!$revision) {
            http_response_code(404);
            echo '編集履歴が見つかりません。';
            return;
        }
        $validation = PageValidator::validate($revision, $id);
        if ($validation['errors']) {
            $this->notice(implode(' ', $validation['errors']));
            redirect_to('admin/pages/revisions/' . $id);
        }
        $pdo = Database::connect();
        try {
            $pdo->beginTransaction();
            PageRevision::create($page, (int)Auth::user()['id']);
            Page::update($id, $validation['data']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->notice('編集履歴を復元できませんでした。');
            redirect_to('admin/pages/revisions/' . $id);
        }
        do_action('page_saved', $id);
        $this->notice('編集履歴を復元しました。復元前の内容も履歴に保存しました。');
        redirect_to('admin/pages/edit/' . $id);
    }

    public function delete(int $id): void
    {
        $this->change($id, 'delete', 'ゴミ箱へ移動しました。');
    }

    public function restore(int $id): void
    {
        $this->change($id, 'restore', '下書きとして復元しました。', true);
    }

    public function destroy(int $id): void
    {
        $this->change($id, 'destroy', '固定ページを完全に削除しました。', true);
    }

    public function status(int $id): void
    {
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        if ($page = $this->page($id)) {
            $page['status'] = $page['status'] === 'published' ? 'draft' : 'published';
            $_POST = array_merge($_POST, $page);
            $this->save($id);
        }
    }

    private function change(int $id, string $method, string $message, bool $trash = false): void
    {
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        if (!$this->page($id, $trash)) {
            return;
        }
        Page::$method($id);
        $this->notice($message);
        redirect_to('admin/pages' . ($trash ? '?trash=1' : ''));
    }

    private function page(int $id, bool $trash = false): ?array
    {
        $page = Page::find($id);
        if (!$page || ($page['deleted_at'] !== null) !== $trash) {
            http_response_code(404);
            echo '固定ページが見つかりません。';
            return null;
        }
        return $page;
    }

    private function form(?array $page, ?string $error = null): void
    {
        $notice = $_SESSION['page_notice'] ?? null;
        unset($_SESSION['page_notice']);
        $this->view('admin/page-form', [
            'page' => $page, 'error' => $error, 'notice' => $notice,
            'action' => url(empty($page['id']) ? 'admin/pages/store' : 'admin/pages/update/' . $page['id']),
        ]);
    }

    private function notice(string $message): void
    {
        $_SESSION['page_notice'] = $message;
    }
}
