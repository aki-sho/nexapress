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