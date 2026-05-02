<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Models\Post;

class PostController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $posts = Post::all();

        $this->view('admin/posts', [
            'posts' => $posts,
        ]);
    }

    public function create(): void
    {
        Auth::requireLogin();

        $this->view('admin/post-form', [
            'post' => null,
            'action' => BASE_URL . '/admin/posts/store',
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
            $this->view('admin/post-form', [
                'post' => $_POST,
                'action' => BASE_URL . '/admin/posts/store',
                'error' => '未入力の項目があります。',
            ]);
            return;
        }

        Post::create([
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => $status,
            'user_id' => $user['id'],
        ]);

        $this->redirect('/admin/posts');
    }

    public function edit(int $id): void
    {
        Auth::requireLogin();

        $post = Post::find($id);

        if (!$post) {
            echo '記事が見つかりません。';
            return;
        }

        $this->view('admin/post-form', [
            'post' => $post,
            'action' => BASE_URL . '/admin/posts/update/' . $id,
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
            $this->view('admin/post-form', [
                'post' => array_merge($_POST, ['id' => $id]),
                'action' => BASE_URL . '/admin/posts/update/' . $id,
                'error' => '未入力の項目があります。',
            ]);
            return;
        }

        Post::update($id, [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => $status,
        ]);

        $this->redirect('/admin/posts');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();

        Post::delete($id);

        $this->redirect('/admin/posts');
    }

    public function status(int $id): void
    {
        Auth::requireLogin();

        Post::toggleStatus($id);

        $this->redirect('/admin/posts');
    }
}