<?php

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Design;
use app\Core\Installer;
use app\Core\PageLayout;
use app\Models\Page;
use InvalidArgumentException;

class PageController extends Controller
{
    public function layoutStylesheet(): void
    {
        header('Content-Type: text/css; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        try {
            echo PageLayout::stylesheet($_GET);
        } catch (InvalidArgumentException) {
            http_response_code(400);
        }
    }

    public function backgroundStylesheet(): void
    {
        header('Content-Type: text/css; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        try {
            echo Design::backgroundStylesheet($_GET);
        } catch (InvalidArgumentException) {
            http_response_code(400);
        }
    }

    public function show(string $slug): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect('/install');
        }

        $page = Page::findBySlug($slug);

        if (!$page) {
            http_response_code(404);
            echo '固定ページが見つかりません。';
            return;
        }

        $this->view('page-detail', [
            'page' => $page,
        ]);
    }
}
