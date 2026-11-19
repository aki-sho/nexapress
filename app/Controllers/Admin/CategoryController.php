<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Csrf;
use app\Core\Permission;
use app\Core\PostValidator;
use app\Models\Category;
use PDOException;

class CategoryController extends Controller
{
    public function inlineStore(): void
    {
        Permission::require('categories.manage');
        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        $name = $_POST['name'] ?? null;
        if (!is_string($name) || preg_match('//u', $name) !== 1 ||
            trim($name) === '' || nx_text_length(trim($name)) > 100) {
            $this->categoryResponse(['error' => 'カテゴリ名は1〜100文字で入力してください。'], 422);
            return;
        }
        $name = trim($name);
        $slugInput = $_POST['slug'] ?? '';
        if (!is_string($slugInput) || preg_match('//u', $slugInput) !== 1 || nx_text_length($slugInput) > 255) {
            $this->categoryResponse(['error' => 'スラッグは255文字以内で入力してください。'], 422);
            return;
        }
        $requestedSlug = trim($slugInput) === '' ? null : PostValidator::normalizeSlug($slugInput);
        if ($requestedSlug === '' || ($requestedSlug !== null && nx_text_length($requestedSlug) > 255)) {
            $this->categoryResponse(['error' => 'スラッグには文字・数字を含む255文字以内の値を入力してください。'], 422);
            return;
        }
        try {
            $existing = Category::findByName($name);
            if ($existing !== null) {
                $this->existingCategoryResponse($existing, $requestedSlug);
                return;
            }
            preg_match('/^.{0,240}/us', PostValidator::normalizeSlug($name), $match);
            $base = trim($match[0] ?? '', '-_') ?: 'category';
            for ($index = 0; $index < ($requestedSlug === null ? 50 : 1); $index++) {
                $slug = $requestedSlug ?? $base . ($index ? '-' . ($index + 1) : '');
                if (Category::findBySlug($slug) !== null) {
                    $existing = Category::findByName($name);
                    if ($existing !== null) {
                        $this->existingCategoryResponse($existing, $requestedSlug);
                        return;
                    }
                    continue;
                }
                try {
                    $id = Category::createWithId(['name' => $name, 'slug' => $slug]);
                } catch (PDOException $exception) {
                    if ((string)$exception->getCode() !== '23000') {
                        throw $exception;
                    }
                    $existing = Category::findByName($name);
                    if ($existing !== null) {
                        $this->existingCategoryResponse($existing, $requestedSlug);
                        return;
                    }
                    continue;
                }
                if (function_exists('do_action')) {
                    do_action('category_saved', $id);
                }
                $this->categoryResponse(['category' => ['id' => $id, 'name' => $name, 'slug' => $slug], 'created' => true], 201);
                return;
            }
            $this->categoryResponse(['error' => $requestedSlug === null
                ? '同じURLのカテゴリが多いため、別のカテゴリ名でお試しください。'
                : 'このスラッグは使われています。別のスラッグを入力してください。'], 409);
        } catch (PDOException $exception) {
            $this->categoryResponse(['error' => 'カテゴリを追加できませんでした。もう一度お試しください。'], 500);
        }
    }

    private function existingCategoryResponse(array $category, ?string $slug): void
    {
        if ($slug !== null && $category['slug'] !== $slug) {
            $this->categoryResponse(['error' => '同名のカテゴリがあります。スラッグの変更はカテゴリ管理から行ってください。'], 409);
            return;
        }
        $this->categoryResponse(['category' => $this->categoryData($category), 'created' => false]);
    }

    private function categoryData(array $category): array
    {
        return ['id' => (int)$category['id'], 'name' => $category['name'], 'slug' => $category['slug']];
    }

    private function categoryResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Cache-Control: no-store, private');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

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
