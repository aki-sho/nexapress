<?php

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Installer;
use app\Models\Page;

class PageController extends Controller
{
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