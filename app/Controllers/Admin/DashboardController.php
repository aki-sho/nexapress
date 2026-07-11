<?php
namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $version = 'unknown';

        $versionConfigPath = BASE_PATH . '/config/version.php';

        if (file_exists($versionConfigPath)) {
            $versionConfig = require $versionConfigPath;
            $version = $versionConfig['version'] ?? 'unknown';
        }

        $this->view('admin/dashboard', [
            'version' => $version,
        ]);
    }
}