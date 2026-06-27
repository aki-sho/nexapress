<?php

session_start();

// プロジェクト直下のパスを定義
define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/app/Core/helpers.php';

// 現在の入口URLを取得
// /public ありの場合は /nexapress-1.1.0/public
// /public なしの場合は /nexapress-1.1.0
$baseUrl = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
define('BASE_URL', rtrim($baseUrl, '/'));

// URL設定が「/public なし」の場合、/public 側へ来たアクセスを直下URLへ戻す
$urlConfigPath = BASE_PATH . '/config/url.php';

if (file_exists($urlConfigPath)) {
    $urlConfig = require $urlConfigPath;
    $siteUrlMode = $urlConfig['site_url_mode'] ?? 'public';

    if ($siteUrlMode === 'root' && str_ends_with(BASE_URL, '/public')) {
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $queryString = $_SERVER['QUERY_STRING'] ?? '';

        $redirectPath = preg_replace('#/public#', '', $requestUri, 1);

        if ($redirectPath === '') {
            $redirectPath = '/';
        }

        if ($queryString !== '') {
            $redirectPath .= '?' . $queryString;
        }

        header('Location: ' . $redirectPath, true, 301);
        exit;
    }
}

// クラスを自動読み込みする
spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    $file = BASE_PATH . '/' . $class . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use app\Core\Router;
use app\Core\Debug;

$router = new Router();

Debug::log('Request start', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'base_url' => defined('BASE_URL') ? BASE_URL : '',
]);

// 公開サイト
$router->get('/', 'app\Controllers\HomeController@index');
$router->get('/post/{slug}', 'app\Controllers\PostController@show');

// インストール
$router->get('/install', 'app\Controllers\InstallController@index');
$router->post('/install', 'app\Controllers\InstallController@store');

// 管理画面ログイン
$router->get('/admin/login', 'app\Controllers\Admin\AuthController@login');
$router->post('/admin/login', 'app\Controllers\Admin\AuthController@authenticate');
$router->get('/admin/logout', 'app\Controllers\Admin\AuthController@logout');

// 管理画面トップ
$router->get('/admin', 'app\Controllers\Admin\DashboardController@index');

// 投稿管理
$router->get('/admin/posts', 'app\Controllers\Admin\PostController@index');
$router->get('/admin/posts/create', 'app\Controllers\Admin\PostController@create');
$router->post('/admin/posts/store', 'app\Controllers\Admin\PostController@store');
$router->get('/admin/posts/edit/{id}', 'app\Controllers\Admin\PostController@edit');
$router->post('/admin/posts/update/{id}', 'app\Controllers\Admin\PostController@update');
$router->post('/admin/posts/delete/{id}', 'app\Controllers\Admin\PostController@delete');
$router->post('/admin/posts/status/{id}', 'app\Controllers\Admin\PostController@status');

// カテゴリ管理
$router->get('/admin/categories', 'app\Controllers\Admin\CategoryController@index');
$router->post('/admin/categories/store', 'app\Controllers\Admin\CategoryController@store');
$router->get('/admin/categories/edit/{id}', 'app\Controllers\Admin\CategoryController@edit');
$router->post('/admin/categories/update/{id}', 'app\Controllers\Admin\CategoryController@update');
$router->post('/admin/categories/delete/{id}', 'app\Controllers\Admin\CategoryController@delete');

// 固定ページ管理
$router->get('/admin/pages', 'app\Controllers\Admin\PageController@index');
$router->get('/admin/pages/create', 'app\Controllers\Admin\PageController@create');
$router->post('/admin/pages/store', 'app\Controllers\Admin\PageController@store');
$router->get('/admin/pages/edit/{id}', 'app\Controllers\Admin\PageController@edit');
$router->post('/admin/pages/update/{id}', 'app\Controllers\Admin\PageController@update');
$router->post('/admin/pages/delete/{id}', 'app\Controllers\Admin\PageController@delete');
$router->post('/admin/pages/status/{id}', 'app\Controllers\Admin\PageController@status');

// テーマ管理
$router->get('/admin/themes', 'app\Controllers\Admin\ThemeController@index');
$router->post('/admin/themes/update', 'app\Controllers\Admin\ThemeController@update');

// 設定
$router->get('/admin/settings/url', 'app\Controllers\Admin\SettingController@url');
$router->post('/admin/settings/url/update', 'app\Controllers\Admin\SettingController@updateUrl');
$router->get('/admin/settings/debug', 'app\Controllers\Admin\SettingController@debug');
$router->post('/admin/settings/debug/update', 'app\Controllers\Admin\SettingController@updateDebug');

// 固定ページ表示
$router->get('/page/{slug}', 'app\Controllers\PageController@show');

// 投稿URL設定用
// 固定URLとぶつからないように、必ず最後に置く
$router->get('/{slug}', 'app\Controllers\PostController@showPlain');
$router->get('/{category}/{slug}', 'app\Controllers\PostController@showByCategory');

$router->dispatch();