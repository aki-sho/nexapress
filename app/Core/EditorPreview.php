<?php

namespace app\Core;

use app\Models\Category;
use app\Models\Media;

final class EditorPreview
{
    private static bool $active = false;

    public static function active(): bool
    {
        return self::$active;
    }

    public static function attribute(string $field): void
    {
        if (self::$active && in_array($field, ['title', 'body'], true)) {
            echo ' data-nx-editor-field="' . $field . '"';
        }
    }

    public static function render(string $kind, array $document, array $input): void
    {
        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex, nofollow');
        header('Content-Type: text/html; charset=UTF-8');
        $title = $input['title'] ?? '';
        $content = $input['content'] ?? '';
        if (!is_string($title) || nx_text_length($title) > 255 || !is_string($content)) {
            http_response_code(422);
            return;
        }
        $document['title'] = $title;
        try {
            $document['content'] = ArticleContent::sanitize($content);
        } catch (\InvalidArgumentException $exception) {
            http_response_code(422);
            echo e($exception->getMessage());
            return;
        }
        if ($kind === 'post') {
            $category = filter_var($input['category_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $image = filter_var($input['featured_media_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (array_key_exists('category_id', $input)) {
                $selected = $category === false ? null : Category::find($category);
                $document['category_name'] = $selected['name'] ?? '';
                $document['category_slug'] = $selected['slug'] ?? '';
            }
            if (array_key_exists('featured_media_id', $input)) {
                $selected = $image === false ? null : Media::find($image);
                $selected = ($selected['file_type'] ?? '') === 'image' ? $selected : [];
                $document['featured_media_path'] = $selected['file_path'] ?? '';
                $document['featured_media_title'] = $selected['title'] ?? '';
                $document['featured_media_description'] = $selected['description'] ?? '';
            }
        }
        if ($kind === 'page') {
            $settings = general_config();
            $homepage = ($settings['homepage_mode'] ?? '') === 'page'
                && (int)($settings['homepage_page_id'] ?? 0) === (int)$document['id'];
        }

        self::$active = true;
        try {
            View::render($kind . '-detail', [$kind => $document, 'isHomepage' => $homepage ?? false]);
        } finally {
            self::$active = false;
        }
    }

    public static function addMissingFields(string $content, array $document): string
    {
        $title = str_contains($content, 'data-nx-editor-field="title"');
        $body = str_contains($content, 'data-nx-editor-field="body"');
        if ($title && $body) {
            return $content;
        }
        return $content . '<article class="post-detail nx-editor-preview-extra">'
            . '<p class="nx-editor-preview-note">レイアウト外の編集欄</p>'
            . ($title ? '' : '<h1 data-nx-editor-field="title">' . e($document['title']) . '</h1>')
            . ($body ? '' : '<div class="post-content" data-nx-editor-field="body">' . nx_post_content($document) . '</div>')
            . '</article>';
    }
}
