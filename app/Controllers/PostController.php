<?php

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Installer;
use app\Models\Post;

class PostController extends Controller
{
    public function show(string $slug): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect('/install');
        }

        $post = Post::findBySlug($slug);

        if (!$post) {
            http_response_code(404);
            echo '記事が見つかりません。';
            return;
        }

        $this->view('post-detail', [
            'post' => $post,
        ]);
    }
}