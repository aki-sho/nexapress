<?php

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Installer;

class InstallController extends Controller
{
    /*
     * インストール開始画面
     */
    public function index(): void
    {
        $this->redirectIfInstalled();

        $this->view('install');
    }

    /*
     * データベース設定画面
     */
    public function database(): void
    {
        $this->redirectIfInstalled();

        $config = Installer::databaseConfig();

        $this->view('install-database', [
            'form' => [
                'db_host' => $config['db_host']
                    ?? 'localhost',
                'db_name' => $config['db_name']
                    ?? '',
                'db_user' => $config['db_user']
                    ?? 'root',
                'db_pass' => '',
                'table_prefix' => $config['table_prefix']
                    ?? 'nx_',
            ],
        ]);
    }

    /*
     * データベース設定を保存
     */
    public function storeDatabase(): void
    {
        $this->redirectIfInstalled();

        $data = [
            'db_host' => trim(
                $_POST['db_host'] ?? ''
            ),
            'db_name' => trim(
                $_POST['db_name'] ?? ''
            ),
            'db_user' => trim(
                $_POST['db_user'] ?? ''
            ),
            'db_pass' => $_POST['db_pass'] ?? '',
            'table_prefix' => trim(
                $_POST['table_prefix'] ?? ''
            ),
        ];

        if (
            $data['db_host'] === '' ||
            $data['db_name'] === '' ||
            $data['db_user'] === '' ||
            $data['table_prefix'] === ''
        ) {
            $this->showDatabaseError(
                '未入力の項目があります。',
                $data
            );

            return;
        }

        if (
            !preg_match(
                '/^[A-Za-z0-9_]+$/',
                $data['table_prefix']
            )
        ) {
            $this->showDatabaseError(
                'テーブル接頭辞には、半角英数字とアンダースコアのみ使用できます。',
                $data
            );

            return;
        }

        if (!Installer::configureDatabase($data)) {
            $this->showDatabaseError(
                'データベースへ接続できませんでした。入力内容を確認してください。',
                $data
            );

            return;
        }

        $this->redirect('install/site');
    }

    /*
     * サイト設定画面
     */
    public function site(): void
    {
        $this->redirectIfInstalled();

        if (!Installer::isDatabaseConfigured()) {
            $this->redirect('install/database');
        }

        $this->view('install-site', [
            'form' => [
                'site_title' => '',
                'admin_username' => '',
                'admin_email' => '',
                'discourage_search_engines' => false,
            ],
        ]);
    }

    /*
     * サイトと管理者を作成
     */
    public function storeSite(): void
    {
        $this->redirectIfInstalled();

        if (!Installer::isDatabaseConfigured()) {
            $this->redirect('install/database');
        }

        $data = [
            'site_title' => trim(
                $_POST['site_title'] ?? ''
            ),
            'admin_username' => trim(
                $_POST['admin_username'] ?? ''
            ),
            'admin_email' => trim(
                $_POST['admin_email'] ?? ''
            ),
            'admin_password' =>
                $_POST['admin_password'] ?? '',
            'discourage_search_engines' => isset(
                $_POST['discourage_search_engines']
            ),
        ];

        if (
            $data['site_title'] === '' ||
            $data['admin_username'] === '' ||
            $data['admin_email'] === '' ||
            $data['admin_password'] === ''
        ) {
            $this->showSiteError(
                '未入力の項目があります。',
                $data
            );

            return;
        }

        if (
            !preg_match(
                '/^[^\s\x00-\x1F\x7F]{3,100}$/u',
                $data['admin_username']
            )
        ) {
            $this->showSiteError(
                'ユーザー名は空白を含めず、3文字以上100文字以内で入力してください。',
                $data
            );

            return;
        }

        if (
            !filter_var(
                $data['admin_email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $this->showSiteError(
                'メールアドレスの形式が正しくありません。',
                $data
            );

            return;
        }

        if (strlen($data['admin_password']) < 8) {
            $this->showSiteError(
                'パスワードは8文字以上で入力してください。',
                $data
            );

            return;
        }

        if (!Installer::installSite($data)) {
            $this->showSiteError(
                'インストールに失敗しました。',
                $data
            );

            return;
        }

        $this->redirect('admin/login');
    }

    /*
     * インストール済みの場合はログイン画面へ移動
     */
    private function redirectIfInstalled(): void
    {
        if (Installer::isInstalled()) {
            $this->redirect('admin/login');
        }
    }

    /*
     * データベース設定エラー
     */
    private function showDatabaseError(
        string $error,
        array $data
    ): void {
        $data['db_pass'] = '';

        $this->view('install-database', [
            'error' => $error,
            'form' => $data,
        ]);
    }

    /*
     * サイト設定エラー
     */
    private function showSiteError(
        string $error,
        array $data
    ): void {
        unset($data['admin_password']);

        $this->view('install-site', [
            'error' => $error,
            'form' => $data,
        ]);
    }
}