<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Csrf;
use app\Core\UpdateChecker;
use app\Core\Updater;
use Throwable;

class UpdateController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $this->showUpdatePage(false);
    }

    public function check(): void
    {
        Auth::requireLogin();

        Csrf::requireValid(
            $_POST['_token'] ?? null
        );

        $this->showUpdatePage(true);
    }

    public function install(): void
    {
        Auth::requireLogin();

        Csrf::requireValid(
            $_POST['_token'] ?? null
        );

        $updateInfo = null;
        $updateResult = null;
        $error = null;

        try {
            // 実行直前に最新情報を再取得する
            $updateInfo = UpdateChecker::check(true);

            $updateResult = Updater::install(
                $updateInfo
            );

            Csrf::regenerate();

            // 更新後のバージョンで再確認する
            $updateInfo = UpdateChecker::check(true);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();

            try {
                $updateInfo = UpdateChecker::check(false);
            } catch (Throwable $checkException) {
                $updateInfo = null;
            }
        }

        $this->view('admin/updates', [
            'updateInfo' => $updateInfo,
            'updateResult' => $updateResult,
            'error' => $error,
            'checkedAt' => date('Y-m-d H:i:s'),
        ]);
    }

    private function showUpdatePage(
        bool $force
    ): void {
        $updateInfo = null;
        $error = null;

        try {
            $updateInfo = UpdateChecker::check($force);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $this->view('admin/updates', [
            'updateInfo' => $updateInfo,
            'updateResult' => null,
            'error' => $error,
            'checkedAt' => date('Y-m-d H:i:s'),
        ]);
    }
}