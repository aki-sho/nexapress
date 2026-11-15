<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Csrf;
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

        Csrf::requireValid($_POST['_csrf_token'] ?? null);
        if (is_string($theme) && Theme::set($theme)) {
            do_action('design_published', $theme);
        }

        redirect_to('admin/themes');
    }
}
