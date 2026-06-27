<?php

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Installer;
use app\Models\Post;
use app\Models\Page;

class PostController extends Controller
{
    public function show(string $slug): void
    {
        $this->showPost($slug);
    }

    public function showPlain(string $slug): void
    {
        $this->showPost($slug);
    }

    public function showByCategory(string $category, string $slug): void
    {
        $this->showPost($slug);
    }

    private function showPost(string $slug): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect('/install');
        }

        $post = Post::findBySlug($slug);

        if ($post) {
            $this->view('post-detail', [
                'post' => $post,
            ]);
            return;
        }

        $configPath = BASE_PATH . '/config/url.php';
        $pageUrlType = 'page_slug';

        if (file_exists($configPath)) {
            $config = require $configPath;
            $pageUrlType = $config['page_url_type'] ?? 'page_slug';
        }

        if ($pageUrlType === 'slug') {
            $page = Page::findBySlug($slug);

            if ($page) {
                $this->view('page-detail', [
                    'page' => $page,
                ]);
                return;
            }
        }

        http_response_code(404);
        echo '記事が見つかりません。';
        return;
    }
}