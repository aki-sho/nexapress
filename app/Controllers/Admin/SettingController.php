<?php

namespace app\Controllers\Admin;

use app\Core\Auth;
use app\Core\Controller;

class SettingController extends Controller
{
    /*
     * 一般設定画面
     */
    public function general(): void
    {
        Auth::requireLogin();

        $config = $this->loadGeneralConfig();

        $this->view('admin/settings-general', [
            'config' => $config,
        ]);
    }

    /*
     * 一般設定を保存
     */
    public function updateGeneral(): void
    {
        Auth::requireLogin();

        $siteTitle = trim(
            $_POST['site_title'] ?? 'My CMS'
        );

        $timezone = trim(
            $_POST['timezone'] ?? 'Asia/Tokyo'
        );

        $siteIcon = trim(
            $_POST['site_icon'] ?? ''
        );

        $discourageSearchEngines = isset(
            $_POST['discourage_search_engines']
        );

        if ($siteTitle === '') {
            $siteTitle = 'My CMS';
        }

        if ($timezone === '') {
            $timezone = 'Asia/Tokyo';
        }

        $config = [
            'site_title' => $siteTitle,
            'timezone' => $timezone,
            'site_icon' => $siteIcon,
            'discourage_search_engines' =>
                $discourageSearchEngines,
        ];

        $configPath = BASE_PATH
            . '/config/general.php';

        $content = "<?php\n\nreturn "
            . var_export($config, true)
            . ";\n";

        file_put_contents(
            $configPath,
            $content,
            LOCK_EX
        );

        redirect_to('admin/settings/general');
    }

    /*
     * 一般設定を取得
     */
    private function loadGeneralConfig(): array
    {
        $configPath = BASE_PATH
            . '/config/general.php';

        if (!file_exists($configPath)) {
            return [
                'site_title' => 'My CMS',
                'timezone' => 'Asia/Tokyo',
                'site_icon' => '',
                'discourage_search_engines' => false,
            ];
        }

        $config = require $configPath;

        if (!is_array($config)) {
            return [
                'site_title' => 'My CMS',
                'timezone' => 'Asia/Tokyo',
                'site_icon' => '',
                'discourage_search_engines' => false,
            ];
        }

        return array_merge(
            [
                'site_title' => 'My CMS',
                'timezone' => 'Asia/Tokyo',
                'site_icon' => '',
                'discourage_search_engines' => false,
            ],
            $config
        );
    }

    /*
     * URL設定画面
     */
    public function url(): void
    {
        Auth::requireLogin();

        $config = $this->loadUrlConfig();

        $this->view('admin/settings-url', [
            'config' => $config,
        ]);
    }

    /*
     * URL設定を保存
     */
    public function updateUrl(): void
    {
        Auth::requireLogin();

        $siteUrlMode =
            $_POST['site_url_mode'] ?? 'public';

        $postUrlType =
            $_POST['post_url_type'] ?? 'post_slug';

        $pageUrlType =
            $_POST['page_url_type'] ?? 'page_slug';

        if (
            !in_array(
                $siteUrlMode,
                ['public', 'root'],
                true
            )
        ) {
            $siteUrlMode = 'public';
        }

        if (
            !in_array(
                $postUrlType,
                [
                    'post_slug',
                    'slug',
                    'category_slug',
                ],
                true
            )
        ) {
            $postUrlType = 'post_slug';
        }

        if (
            !in_array(
                $pageUrlType,
                ['page_slug', 'slug'],
                true
            )
        ) {
            $pageUrlType = 'page_slug';
        }

        $configPath = BASE_PATH
            . '/config/url.php';

        $config = [
            'site_url_mode' => $siteUrlMode,
            'post_url_type' => $postUrlType,
            'page_url_type' => $pageUrlType,
        ];

        $content = "<?php\n\nreturn "
            . var_export($config, true)
            . ";\n";

        file_put_contents(
            $configPath,
            $content,
            LOCK_EX
        );

        redirect_to('admin/settings/url');
    }

    /*
     * URL設定を取得
     */
    private function loadUrlConfig(): array
    {
        $configPath = BASE_PATH
            . '/config/url.php';

        if (!file_exists($configPath)) {
            return [
                'site_url_mode' => 'public',
                'post_url_type' => 'post_slug',
                'page_url_type' => 'page_slug',
            ];
        }

        return require $configPath;
    }

    /*
     * デバッグ設定画面
     */
    public function debug(): void
    {
        Auth::requireLogin();

        $config = $this->loadDebugConfig();

        $this->view('admin/settings-debug', [
            'config' => $config,
        ]);
    }

    /*
     * デバッグ設定を保存
     */
    public function updateDebug(): void
    {
        Auth::requireLogin();

        $enabled =
            ($_POST['enabled'] ?? '0') === '1';

        $configPath = BASE_PATH
            . '/config/debug.php';

        $config = [
            'enabled' => $enabled,
        ];

        $content = "<?php\n\nreturn "
            . var_export($config, true)
            . ";\n";

        file_put_contents(
            $configPath,
            $content,
            LOCK_EX
        );

        redirect_to('admin/settings/debug');
    }

    /*
     * デバッグ設定を取得
     */
    private function loadDebugConfig(): array
    {
        $configPath = BASE_PATH
            . '/config/debug.php';

        if (!file_exists($configPath)) {
            return [
                'enabled' => false,
            ];
        }

        return require $configPath;
    }
}