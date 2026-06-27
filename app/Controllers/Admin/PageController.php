<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Models\Page;

class PageController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $pages = Page::all();

        $this->view('admin/pages', [
            'pages' => $pages,
        ]);
    }

    public function create(): void
    {
        Auth::requireLogin();

        $this->view('admin/page-form', [
            'page' => null,
            'action' => url('admin/pages/store'),
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();

        $user = Auth::user();

        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $status = $_POST['status'] ?? 'draft';

        if ($title === '' || $slug === '' || $content === '') {
            $this->view('admin/page-form', [
                'page' => $_POST,
                'action' => url('admin/pages/store'),
                'error' => '未入力の項目があります。',
            ]);
            return;
        }

        Page::create([
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => $status,
            'user_id' => $user['id'],
        ]);

        redirect_to('admin/pages');
    }

    public function edit(int $id): void
    {
        Auth::requireLogin();

        $page = Page::find($id);

        if (!$page) {
            echo '固定ページが見つかりません。';
            return;
        }

        $this->view('admin/page-form', [
            'page' => $page,
            'action' => url('admin/pages/update/' . $id),
        ]);
    }

    public function update(int $id): void
    {
        Auth::requireLogin();

        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $status = $_POST['status'] ?? 'draft';

        if ($title === '' || $slug === '' || $content === '') {
            $this->view('admin/page-form', [
                'page' => array_merge($_POST, ['id' => $id]),
                'action' => url('admin/pages/update/' . $id),
                'error' => '未入力の項目があります。',
            ]);
            return;
        }

        Page::update($id, [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => $status,
        ]);

        redirect_to('admin/pages');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();

        Page::delete($id);

        redirect_to('admin/pages');
    }

    public function status(int $id): void
    {
        Auth::requireLogin();

        Page::toggleStatus($id);

        redirect_to('admin/pages');
    }
}