<?php

namespace app\Core;

/** Allows installed extensions to enhance sanitized page and post detail content. */
final class ContentExtension
{
    private static array $handlers = [];
    private static ?array $document = null;
    private static ?array $context = null;

    public static function register(string $action, callable $render, callable $submit): void
    {
        self::$handlers[$action] = ['render' => $render, 'submit' => $submit];
    }

    public static function handlesPost(mixed $action): bool
    {
        return is_string($action) && isset(self::$handlers[$action]);
    }

    public static function begin(string $view, array $data): bool
    {
        if (EditorPreview::active()) {
            return true;
        }
        $type = match ($view) {
            'page-detail' => 'page',
            'post-detail' => 'post',
            default => null,
        };
        if ($type === null || self::$handlers === [] || !isset($data[$type]['id'])) {
            return true;
        }
        $document = $data[$type];
        $action = $_POST['_nx_content_action'] ?? null;
        // The authenticated design editor uses POST to carry its draft, not to submit a public form.
        $designPreview = Design::isPreview();
        $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$designPreview;
        $preview = $designPreview || !empty($data['isPreview']);
        $path = $type === 'post' ? post_url($document) : (!empty($data['isHomepage']) ? url() : page_url($document));
        $path = self::relativeUrl($path);
        if ($path === null || ($post && ($preview || !self::handlesPost($action) || !self::matchesPath($path)))) {
            return !$post;
        }

        self::$document = $document;
        self::$context = ['type' => $type, 'id' => (int)$document['id'], 'url' => $path, 'preview' => $preview];
        if ($post) {
            self::$handlers[$action]['submit'](nx_post_content($document, false), self::$context);
        }
        return true;
    }

    public static function render(string $html, array $document): string
    {
        if (self::$context === null || self::$document !== $document) {
            return $html;
        }
        foreach (self::$handlers as $handler) {
            $html = $handler['render']($html, self::$context);
        }
        return $html;
    }

    public static function end(): void
    {
        self::$document = null;
        self::$context = null;
    }

    private static function matchesPath(string $path): bool
    {
        $request = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $request = is_string($request) ? self::relativeUrl($request) : null;
        return $request !== null && rtrim($request, '/') === rtrim($path, '/');
    }

    private static function relativeUrl(string $path): ?string
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }
        $parts = explode('/', $path);
        foreach ($parts as &$part) {
            $segment = rawurldecode($part);
            if ($segment === '.' || $segment === '..' || preg_match('~[\x00-\x1f\x7f/\\\\?#]~', $segment)) {
                return null;
            }
            $part = rawurlencode($segment);
        }
        return implode('/', $parts);
    }
}
