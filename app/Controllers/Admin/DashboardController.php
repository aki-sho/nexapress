<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $this->view('admin/dashboard');
    }
}