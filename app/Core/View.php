<?php

namespace app\Core;

class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data);

        $appViewPath = BASE_PATH . '/app/Views/' . $view . '.php';

        if ($view === 'admin/login') {
            if (!file_exists($appViewPath)) {
                echo 'View not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
                return;
            }

            ob_start();
            require $appViewPath;
            $content = ob_get_clean();

            require BASE_PATH . '/app/Views/admin/login-layout.php';
            return;
        }

        if (str_starts_with($view, 'admin/')) {
            if (!file_exists($appViewPath)) {
                echo 'View not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
                return;
            }

            ob_start();
            require $appViewPath;
            $content = ob_get_clean();

            require BASE_PATH . '/app/Views/admin/layout.php';
            return;
        }

        if (
            $view === 'install' ||
            str_starts_with($view, 'install-')
        ) {
            if (!file_exists($appViewPath)) {
                echo 'View not found: '
                    . htmlspecialchars(
                        $view,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                return;
            }

            ob_start();

            require $appViewPath;

            $content = ob_get_clean();

            require BASE_PATH
                . '/app/Views/install-layout.php';

            return;
        }

        $themeViewPath = Theme::template($view);
        $viewPath = $themeViewPath ?: $appViewPath;
        $pageDesign = null;
        $layoutKind = match ($view) {
            'page-detail' => 'page',
            'post-detail' => 'post',
            default => null,
        };
        $layoutDocument = $layoutKind === 'page' ? ($page ?? null) : ($post ?? null);
        if ($layoutKind !== null && isset($layoutDocument['id']) &&
            in_array('page_layout', Theme::designAreas(Theme::active()), true)) {
            $pageDesign = Design::pageLayout((int)$layoutDocument['id'], $layoutKind, $editorDisplay ?? []);
            if ($pageDesign['enabled'] && $pageDesign['sections']) {
                $viewPath = BASE_PATH . '/app/Views/design/page-layout.php';
            }
        }

        if (!file_exists($viewPath) && !EditorPreview::active()) {
            echo 'View not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }

        try {
            if (!ContentExtension::begin($view, $data)) {
                http_response_code(404);
                echo '固定ページまたは投稿が見つかりません。';
                return;
            }
            ob_start();
            if ($pageDesign !== null) {
                $stylesheet = PageLayout::stylesheetUrl($pageDesign, nx_design_settings()['width']);
                if ($stylesheet !== null) {
                    echo '<link rel="stylesheet" href="' . e($stylesheet) . '">' . "\n";
                }
            }
            if (file_exists($viewPath)) {
                require $viewPath;
            }
            $content = ob_get_clean();
            if ($layoutKind === 'page' && $viewPath === $themeViewPath && ($pageDesign['showHomeLink'] ?? true) === false) {
                $content = self::withoutPageHomeLink($content);
            }
            if (EditorPreview::active()) {
                $content = EditorPreview::addMissingFields(
                    $content, $layoutDocument, ($editorDisplay ?? null) === null
                );
            }
            $themeLayoutPath = Theme::template('layout');
            require $themeLayoutPath ?: BASE_PATH . '/app/Views/layout.php';
        } finally {
            ContentExtension::end();
        }
    }

    private static function withoutPageHomeLink(string $content): string
    {
        // Match the theme's navigation paragraph outside the editable body, including saved older theme files.
        $link = preg_quote('<a href="' . url('') . '">トップへ戻る</a>', '~');
        return preg_replace('~(?<=</div>)\s*<p(?: data-nx-page-home-link)?>\s*' . $link .
            '\s*</p>(?=\s*</article>\s*\z)~u', '', $content) ?? $content;
    }
}
