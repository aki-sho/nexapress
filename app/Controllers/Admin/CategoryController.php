<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Models\Category;

class CategoryController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $categories = Category::all();

        $this->view('admin/categories', [
            'categories' => $categories,
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if ($name === '' || $slug === '') {
            redirect_to('admin/categories');
        }

        Category::create([
            'name' => $name,
            'slug' => $slug,
        ]);

        redirect_to('admin/categories');
    }

    public function edit(int $id): void
    {
        Auth::requireLogin();

        $category = Category::find($id);

        if (!$category) {
            echo 'カテゴリが見つかりません。';
            return;
        }

        $categories = Category::all();

        $this->view('admin/categories', [
            'category' => $category,
            'categories' => $categories,
        ]);
    }

    public function update(int $id): void
    {
        Auth::requireLogin();

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if ($name === '' || $slug === '') {
            redirect_to('admin/categories/edit/' . $id);
        }

        Category::update($id, [
            'name' => $name,
            'slug' => $slug,
        ]);

        redirect_to('admin/categories');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();

        Category::delete($id);

        redirect_to('admin/categories');
    }
}