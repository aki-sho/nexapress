<?php
namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\UpdateChecker;
use Throwable;

class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $version = 'unknown';
        $updateInfo = null;
        $updateError = null;

        $versionConfigPath = BASE_PATH
            . '/config/version.php';

        if (file_exists($versionConfigPath)) {
            $versionConfig = require $versionConfigPath;
            $version = $versionConfig['version']
                ?? 'unknown';
        }

        try {
            // キャッシュがあればGitHubへ再接続しない
            $updateInfo = UpdateChecker::check(false);
        } catch (Throwable $exception) {
            $updateError = $exception->getMessage();
        }

        $this->view('admin/dashboard', [
            'version' => $version,
            'updateInfo' => $updateInfo,
            'updateError' => $updateError,
        ]);
    }
}