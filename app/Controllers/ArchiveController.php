<?php

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Installer;
use app\Core\Paginator;
use app\Models\Category;
use app\Models\Post;

class ArchiveController extends Controller
{
    public function index(): void
    {
        if (!$this->legacy('posts')) {
            $this->listing(null, '', '記事一覧', nx_public_archive_path('posts'));
        }
    }

    public function search(): void
    {
        if ($this->legacy('search')) {
            return;
        }
        $query = is_string($_GET['q'] ?? '') ? trim($_GET['q'] ?? '') : '';
        $query = function_exists('mb_substr') ? mb_substr($query, 0, 200, 'UTF-8') : substr($query, 0, 200);
        $this->listing(null, $query, $query !== '' ? '「' . $query . '」の検索結果' : '記事検索', nx_public_archive_path('search'));
    }

    public function category(string $slug): void
    {
        if (!Installer::isInstalled()) {
            redirect_to('install');
        }
        if ($this->legacy($slug)) {
            return;
        }
        $category = Category::findBySlug($slug);
        if (!$category) {
            http_response_code(404);
            echo 'カテゴリが見つかりません。';
            return;
        }
        $this->listing((int)$category['id'], '', $category['name'], nx_public_archive_path('category', $slug));
    }

    private function legacy(string $slug): bool
    {
        if (!Installer::isInstalled()) {
            redirect_to('install');
        }
        $path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $post = Post::findBySlug($slug);
        if ($post && rawurldecode((string)parse_url(post_url($post), PHP_URL_PATH)) === $path) {
            $this->view('post-detail', ['post' => $post, 'isPreview' => false]);
            return true;
        }
        $page = \app\Models\Page::findBySlug($slug);
        if ($page && rawurldecode((string)parse_url(page_url($page), PHP_URL_PATH)) === $path) {
            $this->view('page-detail', ['page' => $page]);
            return true;
        }
        return false;
    }

    public function listing(?int $category, string $query, string $title, string $path): void
    {
        if (!Installer::isInstalled()) {
            redirect_to('install');
        }
        $requested = is_scalar($_GET['page'] ?? 1) ? (int)($_GET['page'] ?? 1) : 1;
        $pagination = Paginator::make(Post::countPublicArchive($category, $query), $requested, 10);
        $posts = Post::publicArchive($category, $query, $pagination['per_page'], $pagination['offset']);
        $this->view('home', ['posts' => $posts, 'pagination' => $pagination,
            'archiveTitle' => $title, 'archivePath' => $path, 'searchQuery' => $query]);
    }
}
