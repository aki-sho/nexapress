<?php

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Installer;
use app\Models\Post;

class HomeController extends Controller
{
    public function index(): void
    {
        if (!Installer::isInstalled()) {
            $this->redirect('/install');
        }

        $posts = Post::published();

        $this->view('home', [
            'posts' => $posts,
        ]);
    }
}