<?php

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Installer;
use app\Core\Paginator;
use app\Models\Post;

class HomeController extends Controller
{
    /*
     * 公開投稿一覧
     */
    public function index(): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect('/install');
        }

        $config = general_config();
        if (($config['homepage_mode'] ?? 'posts') === 'page') {
            $page = \app\Models\Page::find((int)($config['homepage_page_id'] ?? 0));
            if ($page && $page['status'] === 'published' && $page['deleted_at'] === null) {
                $this->view('page-detail', ['page' => $page]);
                return;
            }
        }

        $requestedPage = max(
            1,
            (int)(
                $_GET['page']
                ?? 1
            )
        );

        $perPage = 10;

        $pagination =
            Paginator::make(
                Post::countPublished(),
                $requestedPage,
                $perPage
            );

        $posts =
            Post::publishedPage(
                (int)$pagination[
                    'current_page'
                ],
                (int)$pagination[
                    'per_page'
                ]
            );

        $this->view('home', [
            'posts' => $posts,
            'pagination' => $pagination,
        ]);
    }
}
