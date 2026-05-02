<?php

session_start();

define('BASE_PATH', dirname(__DIR__));

$baseUrl = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
define('BASE_URL', rtrim($baseUrl, '/'));

spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    $file = BASE_PATH . '/' . $class . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use app\Core\Router;

$router = new Router();

$router->get('/', 'app\Controllers\HomeController@index');
$router->get('/post/{slug}', 'app\Controllers\PostController@show');

$router->get('/install', 'app\Controllers\InstallController@index');
$router->post('/install', 'app\Controllers\InstallController@store');

$router->get('/admin/login', 'app\Controllers\Admin\AuthController@login');
$router->post('/admin/login', 'app\Controllers\Admin\AuthController@authenticate');
$router->get('/admin/logout', 'app\Controllers\Admin\AuthController@logout');

$router->get('/admin', 'app\Controllers\Admin\DashboardController@index');

$router->get('/admin/posts', 'app\Controllers\Admin\PostController@index');
$router->get('/admin/posts/create', 'app\Controllers\Admin\PostController@create');
$router->post('/admin/posts/store', 'app\Controllers\Admin\PostController@store');
$router->get('/admin/posts/edit/{id}', 'app\Controllers\Admin\PostController@edit');
$router->post('/admin/posts/update/{id}', 'app\Controllers\Admin\PostController@update');
$router->post('/admin/posts/delete/{id}', 'app\Controllers\Admin\PostController@delete');
$router->post('/admin/posts/status/{id}', 'app\Controllers\Admin\PostController@status');

$router->dispatch();