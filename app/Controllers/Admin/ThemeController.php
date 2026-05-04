<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Theme;

class ThemeController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $this->view('admin/themes', [
            'themes' => Theme::all(),
            'activeTheme' => Theme::active(),
        ]);
    }

    public function update(): void
    {
        Auth::requireLogin();

        $theme = $_POST['theme'] ?? '';

        Theme::set($theme);

        redirect_to('admin/themes');
    }
}