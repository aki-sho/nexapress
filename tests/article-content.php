<?php

use app\Core\ArticleContent;

require __DIR__ . '/../app/Core/ArticleContent.php';
require __DIR__ . '/../app/Core/HtmlSanitizer.php';

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
echo "Article content checks passed.\n";
