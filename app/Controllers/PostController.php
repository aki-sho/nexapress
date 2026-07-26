<?php

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Installer;
use app\Models\Page;
use app\Models\Post;

class PostController extends Controller
{
    /*
     * /post/{slug}
     */
    public function show(
        string $slug
    ): void {
        $this->showPost(
            $slug,
            false
        );
    }

    /*
     * /{slug}
     */
    public function showPlain(
        string $slug
    ): void {
        $this->showPost(
            $slug,
            true
        );
    }

    /*
     * /{category}/{slug}
     */
    public function showByCategory(
        string $category,
        string $slug
    ): void {
        /*
         * カテゴリ部分は正規URL確認時に
         * 現在のパスと比較する。
         */
        $this->showPost(
            $slug,
            false
        );
    }

    /*
     * 投稿詳細を表示
     */
    private function showPost(
        string $slug,
        bool $allowPageFallback
    ): void {
        if (!Installer::isInstalled()) {
            $this->redirect('/install');
        }

        $post =
            Post::findBySlug($slug);

        if ($post) {
            /*
             * 設定されたURL形式と
             * 現在のURLが違う場合は
             * 正規URLへ301転送する。
             *
             * カテゴリ名が違う場合も
             * ここで正規URLへ転送される。
             */
            $this->redirectToCanonical(
                $post
            );

            $this->view(
                'post-detail',
                [
                    'post' => $post,
                    'isPreview' => false,
                ]
            );

            return;
        }

        /*
         * /{slug}形式では
         * 固定ページも確認する。
         */
        if (
            $allowPageFallback &&
            $this->pageUsesPlainSlug()
        ) {
            $page =
                Page::findBySlug($slug);

            if ($page) {
                $this->view(
                    'page-detail',
                    [
                        'page' => $page,
                    ]
                );

                return;
            }
        }

        http_response_code(404);

        echo '記事が見つかりません。';
    }

    /*
     * 投稿の正規URLへ転送
     */
    private function redirectToCanonical(
        array $post
    ): void {
        $canonicalUrl =
            post_url($post);

        $canonicalPath = parse_url(
            $canonicalUrl,
            PHP_URL_PATH
        );

        $currentPath = parse_url(
            $_SERVER['REQUEST_URI']
            ?? '/',
            PHP_URL_PATH
        );

        if (
            !is_string($canonicalPath) ||
            !is_string($currentPath)
        ) {
            return;
        }

        $canonicalPath =
            $this->normalizePath(
                $canonicalPath
            );

        $currentPath =
            $this->normalizePath(
                $currentPath
            );

        if (
            $canonicalPath
            === $currentPath
        ) {
            return;
        }

        header(
            'Location: ' . $canonicalUrl,
            true,
            301
        );

        exit;
    }

    /*
     * URL比較用にパスを整理
     */
    private function normalizePath(
        string $path
    ): string {
        $path = rawurldecode($path);

        $path = preg_replace(
            '#/+#',
            '/',
            $path
        ) ?? $path;

        $path = '/'
            . trim($path, '/');

        return $path === ''
            ? '/'
            : $path;
    }

    /*
     * 固定ページが/{slug}形式か確認
     */
    private function pageUsesPlainSlug(): bool
    {
        $configPath =
            BASE_PATH
            . '/config/url.php';

        $pageUrlType =
            'page_slug';

        if (file_exists($configPath)) {
            $config =
                require $configPath;

            if (is_array($config)) {
                $pageUrlType =
                    $config[
                        'page_url_type'
                    ] ?? 'page_slug';
            }
        }

        return $pageUrlType === 'slug';
    }
}