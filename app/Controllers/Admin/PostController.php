<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Models\Post;
use app\Models\Category;

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

        $categories = Category::all();

        $this->view('admin/post-form', [
            'post' => null,
            'categories' => $categories,
            'action' => url('admin/posts/store'),
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
        $categoryId = $_POST['category_id'] ?? null;
        $categoryId = $categoryId !== '' ? (int)$categoryId : null;

        if ($title === '' || $slug === '' || $content === '') {
            $categories = Category::all();
            $this->view('admin/post-form', [
                'post' => $_POST,
                'categories' => $categories,
                'action' => url('admin/posts/store'),
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
            'category_id' => $categoryId,
        ]);

        redirect_to('admin/posts');
    }

    public function edit(int $id): void
    {
        Auth::requireLogin();

        $post = Post::find($id);

        if (!$post) {
            echo '記事が見つかりません。';
            return;
        }

        $categories = Category::all();

        $this->view('admin/post-form', [
            'post' => $post,
            'categories' => $categories,
            'action' => url('admin/posts/update/' . $id),
        ]);
    }

    public function update(int $id): void
    {
        Auth::requireLogin();

        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $categoryId = $_POST['category_id'] ?? null;
        $categoryId = $categoryId !== '' ? (int)$categoryId : null;

        if ($title === '' || $slug === '' || $content === '') {
            $categories = Category::all();

            $this->view('admin/post-form', [
                'post' => array_merge($_POST, ['id' => $id]),
                'categories' => $categories,
                'action' => url('admin/posts/update/' . $id),
                'error' => '未入力の項目があります。',
            ]);
            return;
        }

        Post::update($id, [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => $status,
            'category_id' => $categoryId,
        ]);

        redirect_to('admin/posts');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();

        Post::delete($id);

        redirect_to('admin/posts');
    }

    public function status(int $id): void
    {
        Auth::requireLogin();

        Post::toggleStatus($id);

        redirect_to('admin/posts');
    }
}