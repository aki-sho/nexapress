<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;
use app\Core\Extension;
use app\Core\ExtensionCatalog;
use app\Core\ExtensionInstaller;
use RuntimeException;
use Throwable;
use app\Models\ExtensionSetting;
use ZipArchive;

class ExtensionController extends Controller
{
    private int $maxUploadSize = 10485760;

    public function index(): void
    {
        Auth::requireLogin();

        $installedExtensions = Extension::all();
        $catalogExtensions = [];
        $catalogError = null;

        try {
            $catalogExtensions = ExtensionCatalog::all();

            foreach ($catalogExtensions as $extensionId => &$extension) {
                $extension['is_installed'] = isset(
                    $installedExtensions[$extensionId]
                );

                $extension['installed_version'] =
                    $installedExtensions[$extensionId]['version']
                    ?? null;
            }

            unset($extension);
        } catch (Throwable $exception) {
            $catalogError = $exception->getMessage();
        }

        $notice = $_SESSION['extension_notice'] ?? null;
        $error = $_SESSION['extension_error'] ?? null;

        unset(
            $_SESSION['extension_notice'],
            $_SESSION['extension_error']
        );

        $this->view('admin/extensions', [
            'extensions' => $installedExtensions,
            'catalogExtensions' => $catalogExtensions,
            'catalogError' => $catalogError,
            'notice' => $notice,
            'error' => $error,
            'maxUploadSize' => $this->maxUploadSize,
        ]);
    }

    public function install(string $extensionKey): void
    {
        Auth::requireLogin();

        try {
            $extension = ExtensionCatalog::find(
                $extensionKey,
                true
            );

            if ($extension === null) {
                throw new RuntimeException(
                    '拡張機能が見つかりません。'
                );
            }

            ExtensionInstaller::install($extension);

            $_SESSION['extension_notice'] =
                $extension['name']
                . 'をインストールしました。';
        } catch (Throwable $exception) {
            $_SESSION['extension_error'] =
                $exception->getMessage();
        }

        redirect_to('admin/extensions');
    }

    public function upload(): void
    {
        Auth::requireLogin();

        if (
            empty($_FILES['extension_file']) ||
            $_FILES['extension_file']['error'] !== UPLOAD_ERR_OK
        ) {
            redirect_to('admin/extensions');
        }

        $file = $_FILES['extension_file'];

        if ($file['size'] > $this->maxUploadSize) {
            redirect_to('admin/extensions');
        }

        $extension = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        if ($extension !== 'zip' || !class_exists(ZipArchive::class)) {
            redirect_to('admin/extensions');
        }

        $zip = new ZipArchive();

        if ($zip->open($file['tmp_name']) !== true) {
            redirect_to('admin/extensions');
        }

        $rootFolder = null;
        $hasManifestFile = false;
        $entries = [];
        $isValid = true;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $originalName = $zip->getNameIndex($i);
            $entryName = str_replace('\\', '/', $originalName);

            if (
                $entryName === '' ||
                str_contains($entryName, "\0") ||
                str_starts_with($entryName, '/') ||
                preg_match('/^[a-zA-Z]:\//', $entryName) ||
                preg_match('#(^|/)\.\.(/|$)#', $entryName)
            ) {
                $isValid = false;
                break;
            }

            $trimmedName = trim($entryName, '/');

            if ($trimmedName === '') {
                continue;
            }

            $parts = explode('/', $trimmedName);
            $currentRoot = $parts[0];

            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $currentRoot)) {
                $isValid = false;
                break;
            }

            if ($rootFolder === null) {
                $rootFolder = $currentRoot;
            }

            if ($rootFolder !== $currentRoot) {
                $isValid = false;
                break;
            }

            if ($trimmedName === $rootFolder . '/manifest.json') {
                $hasManifestFile = true;
            }

            $entries[] = [
                'original' => $originalName,
                'path' => $trimmedName,
                'directory' => str_ends_with($entryName, '/'),
            ];
        }

        if (!$isValid || !$rootFolder || !$hasManifestFile) {
            $zip->close();
            redirect_to('admin/extensions');
        }

        $extensionDirectory = BASE_PATH . '/extensions';
        $targetDirectory = $extensionDirectory . '/' . $rootFolder;

        if (is_dir($targetDirectory)) {
            $zip->close();
            redirect_to('admin/extensions');
        }

        $temporaryDirectory = BASE_PATH
            . '/storage/cache/extension-'
            . bin2hex(random_bytes(8));

        if (!is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0755, true);
        }

        foreach ($entries as $entry) {
            $destination = $temporaryDirectory . '/' . $entry['path'];

            if ($entry['directory']) {
                if (!is_dir($destination)) {
                    mkdir($destination, 0755, true);
                }

                continue;
            }

            $parentDirectory = dirname($destination);

            if (!is_dir($parentDirectory)) {
                mkdir($parentDirectory, 0755, true);
            }

            $input = $zip->getStream($entry['original']);

            if ($input === false) {
                $isValid = false;
                break;
            }

            $output = fopen($destination, 'wb');

            if ($output === false) {
                fclose($input);
                $isValid = false;
                break;
            }

            stream_copy_to_stream($input, $output);

            fclose($input);
            fclose($output);
        }

        $zip->close();

        if (!$isValid) {
            $this->removeDirectory($temporaryDirectory);
            redirect_to('admin/extensions');
        }

        if (!is_dir($extensionDirectory)) {
            mkdir($extensionDirectory, 0755, true);
        }

        $temporaryExtensionDirectory =
            $temporaryDirectory . '/' . $rootFolder;

        if (
            !is_dir($temporaryExtensionDirectory) ||
            !rename($temporaryExtensionDirectory, $targetDirectory)
        ) {
            $this->removeDirectory($temporaryDirectory);
            redirect_to('admin/extensions');
        }

        $this->removeDirectory($temporaryDirectory);

        redirect_to('admin/extensions');
    }

    public function enable(string $extensionKey): void
    {
        Auth::requireLogin();

        if (Extension::find($extensionKey)) {
            ExtensionSetting::enable($extensionKey);
        }

        redirect_to('admin/extensions');
    }

    public function disable(string $extensionKey): void
    {
        Auth::requireLogin();

        if (Extension::find($extensionKey)) {
            ExtensionSetting::disable($extensionKey);
        }

        redirect_to('admin/extensions');
    }

    public function delete(string $extensionKey): void
    {
        Auth::requireLogin();

        $extension = Extension::find($extensionKey);

        if (!$extension || $extension['is_enabled']) {
            redirect_to('admin/extensions');
        }

        $extensionsDirectory = realpath(BASE_PATH . '/extensions');
        $targetDirectory = realpath(
            BASE_PATH . '/extensions/' . $extension['folder']
        );

        if (
            $extensionsDirectory === false ||
            $targetDirectory === false ||
            !str_starts_with(
                $targetDirectory,
                $extensionsDirectory . DIRECTORY_SEPARATOR
            )
        ) {
            redirect_to('admin/extensions');
        }

        $this->removeDirectory($targetDirectory);
        ExtensionSetting::delete($extensionKey);

        redirect_to('admin/extensions');
    }

    public function dashboard(string $extensionKey): void
    {
        Auth::requireLogin();

        $extension = Extension::find($extensionKey);

        if (!$extension) {
            http_response_code(404);
            echo '拡張機能が見つかりません。';
            return;
        }

        if (!$extension['is_enabled']) {
            http_response_code(403);
            echo 'この拡張機能は無効です。';
            return;
        }

        if (
            !$extension['has_dashboard'] ||
            !is_file($extension['dashboard_file'])
        ) {
            http_response_code(404);
            echo '拡張機能のダッシュボードがありません。';
            return;
        }

        $extensionInfo = $extension;

        ob_start();
        require $extension['dashboard_file'];
        $content = ob_get_clean();

        $title = $extension['name'];

        require BASE_PATH . '/app/Views/admin/layout.php';
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}