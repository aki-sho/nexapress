<?php

function url(string $path = ''): string
{
    $base = defined('BASE_URL') ? BASE_URL : '';

    $path = trim($path, '/');

    if ($path === '') {
        return $base . '/';
    }

    return $base . '/' . $path;
}

function redirect_to(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function nexapress_admin_header(): void
{
    // 未ログインなら、管理者用ヘッダーは出さない
    if (empty($_SESSION['user'])) {
        return;
    }

    ?>
    <header class="admin-preview-header">
        <div class="admin-preview-header-inner">
            <div class="admin-preview-brand">
                <a href="<?= url('') ?>">NexaPress</a>
                <span>管理者プレビュー中</span>
            </div>

            <nav class="admin-preview-nav">
                <a href="<?= url('') ?>">トップ</a>
                <a href="<?= url('admin') ?>">管理画面</a>
                <a href="<?= url('admin/logout') ?>">ログアウト</a>
            </nav>
        </div>
    </header>
    <?php
}