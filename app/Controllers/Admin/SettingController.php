<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;

class SettingController extends Controller
{
    public function url(): void
    {
        Auth::requireLogin();

        $config = $this->loadUrlConfig();

        $this->view('admin/settings-url', [
            'config' => $config,
        ]);
    }

    public function updateUrl(): void
    {
        Auth::requireLogin();

        $siteUrlMode = $_POST['site_url_mode'] ?? 'public';
        $postUrlType = $_POST['post_url_type'] ?? 'post_slug';
        $pageUrlType = $_POST['page_url_type'] ?? 'page_slug';

        if (!in_array($siteUrlMode, ['public', 'root'], true)) {
            $siteUrlMode = 'public';
        }

        if (!in_array($postUrlType, ['post_slug', 'slug', 'category_slug'], true)) {
            $postUrlType = 'post_slug';
        }

        if (!in_array($pageUrlType, ['page_slug', 'slug'], true)) {
            $pageUrlType = 'page_slug';
        }

        $configPath = BASE_PATH . '/config/url.php';

        $content = "<?php\n\nreturn [\n";
        $content .= "    'site_url_mode' => '" . $siteUrlMode . "',\n";
        $content .= "    'post_url_type' => '" . $postUrlType . "',\n";
        $content .= "    'page_url_type' => '" . $pageUrlType . "',\n";
        $content .= "];\n";

        file_put_contents($configPath, $content);

        redirect_to('admin/settings/url');
    }

    private function loadUrlConfig(): array
    {
        $configPath = BASE_PATH . '/config/url.php';

        if (!file_exists($configPath)) {
            return [
                'site_url_mode' => 'public',
                'post_url_type' => 'post_slug',
                'page_url_type' => 'page_slug',
            ];
        }

        return require $configPath;
    }

    public function debug(): void
    {
        Auth::requireLogin();

        $config = $this->loadDebugConfig();

        $this->view('admin/settings-debug', [
            'config' => $config,
        ]);
    }

    public function updateDebug(): void
    {
        Auth::requireLogin();

        $enabled = ($_POST['enabled'] ?? '0') === '1';

        $configPath = BASE_PATH . '/config/debug.php';

        $content = "<?php\n\nreturn [\n";
        $content .= "    'enabled' => " . ($enabled ? 'true' : 'false') . ",\n";
        $content .= "];\n";

        file_put_contents($configPath, $content);

        redirect_to('admin/settings/debug');
    }

    private function loadDebugConfig(): array
    {
        $configPath = BASE_PATH . '/config/debug.php';

        if (!file_exists($configPath)) {
            return [
                'enabled' => false,
            ];
        }

        return require $configPath;
    }
}