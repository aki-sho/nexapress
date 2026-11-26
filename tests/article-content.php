<?php

use app\Core\ArticleContent;

require __DIR__ . '/../app/Core/ArticleContent.php';
require __DIR__ . '/../app/Core/HtmlSanitizer.php';
require __DIR__ . '/../app/Core/LayoutPlacement.php';

$body = str_repeat('あ', intdiv(ArticleContent::MAX_BYTES, 3));
$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$reject = static function (string $content, string $expected) use ($check): void {
    try {
        ArticleContent::sanitize($content);
        throw new RuntimeException('Invalid article content was accepted.');
    } catch (InvalidArgumentException $exception) {
        $check(str_contains($exception->getMessage(), $expected), 'Incorrect validation error.');
    }
};
$check(strlen($body) === ArticleContent::MAX_BYTES, 'Boundary fixture is the wrong size.');
$check(ArticleContent::sanitize($body) === $body, 'The article body changed at the byte limit.');
$check(ArticleContent::sanitize(substr($body, 3)) === substr($body, 3), 'The article was rejected below the limit.');
$reject($body . 'x', '6,553,500');
$reject(str_repeat('<em></em>', 25001), '複雑');
$reject("text\0body", 'UTF-8');
$check(ArticleContent::sanitize('<p title="文章">日本語😀</p>') === '<p title="文章">日本語😀</p>', 'Ordinary HTML changed.');

$heading = '<h2 data-nx-layout-width="60" data-nx-placement="center">案内</h2>';
$check(ArticleContent::sanitize($heading) === $heading, 'Heading width and placement were lost.');
$invalid = '<h3 data-nx-layout-width="101" data-nx-placement="invalid">見出し</h3>'
    . '<div data-nx-column-area="above">記事本文</div>';
$check(ArticleContent::sanitize($invalid) === '<h3>見出し</h3><div>記事本文</div>', 'Invalid layout settings were retained.');
$sharedColumns = '<div class="nx-article-columns nx-article-columns--2">'
    . '<div data-nx-column-area="above"><h2>製品ラインアップ</h2></div>'
    . '<div class="nx-article-column" data-nx-column-width="45">A</div>'
    . '<div class="nx-article-column" data-nx-column-width="40">B</div>'
    . '<div data-nx-column-area="below"><p>詳細</p></div></div>';
$check(ArticleContent::sanitize($sharedColumns) === $sharedColumns, 'Shared column content was lost or rewritten on save.');
$display = \app\Core\HtmlSanitizer::sanitize($sharedColumns, true);
$check(str_contains($display, 'data-nx-column-track-1="0.45000000"')
    && str_contains($display, 'data-nx-column-track-2="0.40000000"'), 'Public rendering used the wrong column weights.');

$check(ArticleContent::characterCount('<p>こんにちは</p>') === 5, 'Japanese body count includes markup or bytes.');
$check(ArticleContent::characterCount('<p title="対象外">日本語<strong>ABC</strong>😀&amp;</p><img src="/a.webp" alt="対象外">') === 8,
    'Nested text, entities, emoji or image attributes were counted incorrectly.');
$check(ArticleContent::characterCount('<p>&lt;p&gt;</p>') === 3, 'Escaped text was mistaken for markup.');
$check(ArticleContent::characterCount('<p>A B</p>') === 3, 'An authored text space was discarded.');
$check(ArticleContent::characterCount("<p>A\nB</p>") === 3, 'An authored text newline was discarded.');
$check(ArticleContent::characterCount('<p>本文</p><!-- comment --><script>alert(1)</script><style>body{}</style>') === 2,
    'Comments or executable content were counted as article text.');
$check(ArticleContent::characterCount('<p><br></p><img src="/a.webp" alt="対象外">') === 0, 'An image-only or empty body has a nonzero text count.');

echo "Article content checks passed (including character counts).\n";
