<?php

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Installer;

class InstallController extends Controller
{
    public function index(): void
    {
        if (Installer::isInstalled()) {
            $this->redirect('/nexapress/public/admin/login');
        }

        $this->view('install');
    }

    public function store(): void
    {
        if (Installer::isInstalled()) {
            $this->redirect('/nexapress/public/admin/login');
        }

        $data = [
            'db_host' => $_POST['db_host'] ?? '',
            'db_name' => $_POST['db_name'] ?? '',
            'db_user' => $_POST['db_user'] ?? '',
            'db_pass' => $_POST['db_pass'] ?? '',
            'admin_name' => $_POST['admin_name'] ?? '',
            'admin_email' => $_POST['admin_email'] ?? '',
            'admin_password' => $_POST['admin_password'] ?? '',
        ];

        if (
            $data['db_host'] === '' ||
            $data['db_name'] === '' ||
            $data['db_user'] === '' ||
            $data['admin_name'] === '' ||
            $data['admin_email'] === '' ||
            $data['admin_password'] === ''
        ) {
            $this->view('install', [
                'error' => '未入力の項目があります。'
            ]);
            return;
        }

        if (Installer::install($data)) {
            $this->redirect('/nexapress/public/admin/login');
        }

        $this->view('install', [
            'error' => 'インストールに失敗しました。'
        ]);
    }
}