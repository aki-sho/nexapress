<?php

namespace app\Core;

use app\Models\Page;

class PageValidator
{
    public static function validate(array $input, ?int $id = null): array
    {
        $errors = [];
        $text = static fn(string $key): string => is_scalar($input[$key] ?? '')
            ? trim((string)($input[$key] ?? '')) : '';
        $title = trim(strip_tags($text('title')));
        $slug = PostValidator::normalizeSlug($text('slug') ?: $title);
        $submittedContent = $input['content'] ?? '';
        $content = is_string($submittedContent) ? $submittedContent : '';
        try {
            $content = ArticleContent::sanitize($submittedContent);
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }
        $status = $text('status') ?: 'draft';
        if ($title === '' || nx_text_length($title) > 255) {
            $errors[] = 'タイトルは1〜255文字で入力してください。';
        }
        $reserved = in_array($slug, ['admin', 'install', 'public', 'post', 'page', 'category', 'search', 'posts'], true);
        if ($slug === '' || nx_text_length($slug) > 255 ||
            ($reserved && !($id !== null && (Page::find($id)['slug'] ?? null) === $slug))) {
            $errors[] = 'スラッグが空か、長すぎるか、システムで使用されています。';
        } elseif (Page::slugExists($slug, $id)) {
            $errors[] = '同じスラッグの固定ページが存在します（ゴミ箱を含みます）。';
        }
        if (!in_array($status, ['draft', 'published'], true)) {
            $errors[] = '公開状態が正しくありません。';
            $status = 'draft';
        }
        return ['data' => compact('title', 'slug', 'content', 'status'), 'errors' => $errors];
    }
}
