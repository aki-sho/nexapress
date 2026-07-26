<?php

namespace app\Core;

/*
 * 投稿本文に含まれるHTMLを安全化
 */
class HtmlSanitizer
{
    /*
     * 使用を許可するHTMLタグと属性
     */
    private const ALLOWED_ATTRIBUTES = [
        'p' => [
            'align',
        ],
        'br' => [],
        'hr' => [],

        'h1' => [
            'align',
        ],
        'h2' => [
            'align',
        ],
        'h3' => [
            'align',
        ],
        'h4' => [
            'align',
        ],
        'h5' => [
            'align',
        ],
        'h6' => [
            'align',
        ],

        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'strike' => [],
        'del' => [],
        'mark' => [],
        'small' => [],
        'sup' => [],
        'sub' => [],

        'blockquote' => [
            'cite',
        ],

        'pre' => [],
        'code' => [],

        'ul' => [],
        'ol' => [
            'start',
        ],
        'li' => [
            'value',
        ],

        'a' => [
            'href',
            'target',
            'rel',
        ],

        'img' => [
            'src',
            'alt',
            'width',
            'height',
            'loading',
        ],

        'figure' => [],
        'figcaption' => [],

        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tfoot' => [],
        'tr' => [],
        'th' => [
            'colspan',
            'rowspan',
            'align',
        ],
        'td' => [
            'colspan',
            'rowspan',
            'align',
        ],

        'div' => [
            'align',
        ],
        'span' => [],

        /*
         * 一部のビジュアルエディターが
         * 文字色に使用するタグ
         */
        'font' => [
            'color',
            'size',
        ],
    ];

    /*
     * すべての許可タグで使用できる属性
     */
    private const GLOBAL_ATTRIBUTES = [
        'class',
        'style',
        'title',
    ];

    /*
     * タグの中身ごと削除する危険な要素
     */
    private const REMOVE_WITH_CONTENT = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'applet',
        'form',
        'input',
        'button',
        'textarea',
        'select',
        'option',
        'meta',
        'link',
        'base',
        'svg',
        'math',
        'template',
    ];

    /*
     * 許可するCSSプロパティ
     */
    private const ALLOWED_STYLE_PROPERTIES = [
        'color',
        'background-color',
        'text-align',
        'font-size',
        'font-weight',
        'font-style',
        'text-decoration',
    ];

    /*
     * 投稿本文を安全化
     */
    public static function sanitize(
        ?string $html
    ): string {
        $html = trim((string)$html);

        if ($html === '') {
            return '';
        }

        /*
         * DOM拡張が利用できない環境では
         * 安全性を優先した簡易処理を行う
         */
        if (!class_exists(\DOMDocument::class)) {
            return self::fallbackSanitize($html);
        }

        $document = new \DOMDocument(
            '1.0',
            'UTF-8'
        );

        $previousSetting =
            libxml_use_internal_errors(true);

        /*
         * 外部アクセスを禁止した状態で
         * HTML断片をDOMへ読み込む
         */
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">'
            . '<!DOCTYPE html>'
            . '<html>'
            . '<body>'
            . '<div id="nx-sanitizer-root">'
            . $html
            . '</div>'
            . '</body>'
            . '</html>',
            LIBXML_NONET |
            LIBXML_COMPACT
        );

        libxml_clear_errors();

        libxml_use_internal_errors(
            $previousSetting
        );

        if (!$loaded) {
            return self::fallbackSanitize(
                $html
            );
        }

        $xpath = new \DOMXPath($document);

        $rootNodes = $xpath->query(
            '//*[@id="nx-sanitizer-root"]'
        );

        if (
            $rootNodes === false ||
            $rootNodes->length !== 1
        ) {
            return self::fallbackSanitize(
                $html
            );
        }

        $root = $rootNodes->item(0);

        if (!$root instanceof \DOMElement) {
            return self::fallbackSanitize(
                $html
            );
        }

        self::sanitizeChildren($root);

        /*
         * ラッパー要素の内側だけを返す
         */
        $output = '';

        foreach ($root->childNodes as $child) {
            $childHtml =
                $document->saveHTML($child);

            if ($childHtml !== false) {
                $output .= $childHtml;
            }
        }

        return trim($output);
    }

    /*
     * 子要素を再帰的に検査
     */
    private static function sanitizeChildren(
        \DOMNode $parent
    ): void {
        /*
         * DOMNodeListは変更中に内容が変わるため
         * 先に配列へコピーする
         */
        $children = [];

        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            /*
             * コメントと処理命令を削除
             */
            if (
                $child instanceof \DOMComment ||
                $child instanceof
                    \DOMProcessingInstruction
            ) {
                if ($child->parentNode !== null) {
                    $child->parentNode
                        ->removeChild($child);
                }

                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $tagName = strtolower(
                $child->tagName
            );

            /*
             * 危険な要素は中身ごと削除
             */
            if (
                in_array(
                    $tagName,
                    self::REMOVE_WITH_CONTENT,
                    true
                )
            ) {
                if ($child->parentNode !== null) {
                    $child->parentNode
                        ->removeChild($child);
                }

                continue;
            }

            /*
             * 未許可タグはタグだけ外して
             * 内側の文章を残す
             */
            if (
                !array_key_exists(
                    $tagName,
                    self::ALLOWED_ATTRIBUTES
                )
            ) {
                self::sanitizeChildren($child);
                self::unwrapElement($child);

                continue;
            }

            /*
             * 属性を安全化
             */
            if (
                !self::sanitizeAttributes(
                    $child,
                    $tagName
                )
            ) {
                if ($child->parentNode !== null) {
                    $child->parentNode
                        ->removeChild($child);
                }

                continue;
            }

            self::sanitizeChildren($child);
        }
    }

    /*
     * HTML要素の属性を安全化
     *
     * falseの場合は要素自体を削除する
     */
    private static function sanitizeAttributes(
        \DOMElement $element,
        string $tagName
    ): bool {
        $allowedAttributes = array_merge(
            self::GLOBAL_ATTRIBUTES,
            self::ALLOWED_ATTRIBUTES[
                $tagName
            ]
        );

        /*
         * 属性一覧を先にコピー
         */
        $attributeNames = [];

        foreach (
            $element->attributes
            as $attribute
        ) {
            $attributeNames[] =
                strtolower($attribute->name);
        }

        foreach ($attributeNames as $name) {
            /*
             * onload、onclickなどは
             * 許可リストに含まれないため削除
             */
            if (
                !in_array(
                    $name,
                    $allowedAttributes,
                    true
                )
            ) {
                $element->removeAttribute($name);

                continue;
            }

            $value = $element->getAttribute(
                $name
            );

            $sanitizedValue =
                self::sanitizeAttributeValue(
                    $name,
                    $value,
                    $tagName
                );

            if ($sanitizedValue === null) {
                $element->removeAttribute($name);

                continue;
            }

            $element->setAttribute(
                $name,
                $sanitizedValue
            );
        }

        /*
         * 画像URLが削除された画像は
         * 要素自体を残さない
         */
        if (
            $tagName === 'img' &&
            !$element->hasAttribute('src')
        ) {
            return false;
        }

        /*
         * 別タブリンクへ安全属性を付与
         */
        if (
            $tagName === 'a' &&
            $element->getAttribute('target')
                === '_blank'
        ) {
            $currentRel =
                $element->getAttribute('rel');

            $relValues = preg_split(
                '/\s+/',
                trim($currentRel)
            );

            if (!is_array($relValues)) {
                $relValues = [];
            }

            $relValues[] = 'noopener';
            $relValues[] = 'noreferrer';

            $relValues = array_values(
                array_unique(
                    array_filter($relValues)
                )
            );

            $element->setAttribute(
                'rel',
                implode(' ', $relValues)
            );
        }

        return true;
    }

    /*
     * 属性ごとの値を検査
     */
    private static function sanitizeAttributeValue(
        string $name,
        string $value,
        string $tagName
    ): ?string {
        $value = trim(
            html_entity_decode(
                $value,
                ENT_QUOTES |
                ENT_HTML5,
                'UTF-8'
            )
        );

        if ($value === '') {
            return null;
        }

        return match ($name) {
            'href',
            'cite' =>
                self::sanitizeUrl(
                    $value,
                    false
                ),

            'src' =>
                self::sanitizeUrl(
                    $value,
                    true
                ),

            'class' =>
                self::sanitizeClass($value),

            'style' =>
                self::sanitizeStyle($value),

            'target' =>
                self::sanitizeTarget($value),

            'rel' =>
                self::sanitizeRel($value),

            'width',
            'height' =>
                self::sanitizeInteger(
                    $value,
                    1,
                    5000
                ),

            'colspan',
            'rowspan' =>
                self::sanitizeInteger(
                    $value,
                    1,
                    100
                ),

            'start',
            'value' =>
                self::sanitizeInteger(
                    $value,
                    -999999,
                    999999
                ),

            'loading' =>
                in_array(
                    strtolower($value),
                    [
                        'lazy',
                        'eager',
                    ],
                    true
                )
                    ? strtolower($value)
                    : null,

            'align' =>
                in_array(
                    strtolower($value),
                    [
                        'left',
                        'center',
                        'right',
                        'justify',
                        'start',
                        'end',
                    ],
                    true
                )
                    ? strtolower($value)
                    : null,

            'color' =>
                self::sanitizeColor($value),

            'size' =>
                self::sanitizeInteger(
                    $value,
                    1,
                    7
                ),

            'alt',
            'title' =>
                self::sanitizeTextAttribute(
                    $value
                ),

            default => null,
        };
    }

    /*
     * URL属性を安全化
     */
    private static function sanitizeUrl(
        string $url,
        bool $isImage
    ): ?string {
        /*
         * 制御文字を削除
         */
        $url = preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            $url
        );

        if (!is_string($url)) {
            return null;
        }

        $url = trim($url);

        if (
            $url === '' ||
            str_contains($url, '\\')
        ) {
            return null;
        }

        /*
         * 空白や改行を混ぜた危険な
         * スキームも検出する
         */
        $schemeProbe = preg_replace(
            '/[\x00-\x20]+/u',
            '',
            $url
        );

        if (
            !is_string($schemeProbe) ||
            preg_match(
                '/^(javascript|vbscript|data):/i',
                $schemeProbe
            ) === 1
        ) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        $scheme = strtolower(
            (string)($parts['scheme'] ?? '')
        );

        /*
         * 相対URL・アンカー・クエリーは許可
         */
        if ($scheme === '') {
            return $url;
        }

        $allowedSchemes = $isImage
            ? [
                'http',
                'https',
            ]
            : [
                'http',
                'https',
                'mailto',
                'tel',
            ];

        if (
            !in_array(
                $scheme,
                $allowedSchemes,
                true
            )
        ) {
            return null;
        }

        return $url;
    }

    /*
     * class属性を安全化
     */
    private static function sanitizeClass(
        string $classValue
    ): ?string {
        $classValue = preg_replace(
            '/[^A-Za-z0-9_\-\s]/u',
            '',
            $classValue
        );

        if (!is_string($classValue)) {
            return null;
        }

        $classValue = preg_replace(
            '/\s+/',
            ' ',
            trim($classValue)
        );

        if (
            !is_string($classValue) ||
            $classValue === ''
        ) {
            return null;
        }

        return $classValue;
    }

    /*
     * style属性を安全化
     */
    private static function sanitizeStyle(
        string $style
    ): ?string {
        /*
         * URL読み込みやCSS式を拒否
         */
        if (
            preg_match(
                '/(?:url\s*\(|expression\s*\(|'
                . '@import|behavior\s*:|'
                . 'javascript\s*:|data\s*:|'
                . '-moz-binding|var\s*\()/i',
                $style
            ) === 1
        ) {
            return null;
        }

        $safeDeclarations = [];

        foreach (
            explode(';', $style)
            as $declaration
        ) {
            $parts = explode(
                ':',
                $declaration,
                2
            );

            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(
                trim($parts[0])
            );

            $value = trim($parts[1]);

            if (
                !in_array(
                    $property,
                    self::ALLOWED_STYLE_PROPERTIES,
                    true
                )
            ) {
                continue;
            }

            $safeValue =
                self::sanitizeStyleValue(
                    $property,
                    $value
                );

            if ($safeValue === null) {
                continue;
            }

            $safeDeclarations[] =
                $property
                . ': '
                . $safeValue;
        }

        if ($safeDeclarations === []) {
            return null;
        }

        return implode(
            '; ',
            $safeDeclarations
        );
    }

    /*
     * CSSプロパティごとの値を検査
     */
    private static function sanitizeStyleValue(
        string $property,
        string $value
    ): ?string {
        return match ($property) {
            'color',
            'background-color' =>
                self::sanitizeColor($value),

            'text-align' =>
                in_array(
                    strtolower($value),
                    [
                        'left',
                        'center',
                        'right',
                        'justify',
                        'start',
                        'end',
                    ],
                    true
                )
                    ? strtolower($value)
                    : null,

            'font-size' =>
                self::sanitizeFontSize($value),

            'font-weight' =>
                preg_match(
                    '/^(?:normal|bold|bolder|'
                    . 'lighter|[1-9]00)$/i',
                    $value
                ) === 1
                    ? strtolower($value)
                    : null,

            'font-style' =>
                in_array(
                    strtolower($value),
                    [
                        'normal',
                        'italic',
                        'oblique',
                    ],
                    true
                )
                    ? strtolower($value)
                    : null,

            'text-decoration' =>
                self::sanitizeTextDecoration(
                    $value
                ),

            default => null,
        };
    }

    /*
     * 文字色を安全化
     */
    private static function sanitizeColor(
        string $value
    ): ?string {
        $value = strtolower(
            trim($value)
        );

        /*
         * 16進数カラー
         */
        if (
            preg_match(
                '/^#[0-9a-f]{3,8}$/i',
                $value
            ) === 1
        ) {
            return $value;
        }

        /*
         * rgb・rgba・hsl・hsla
         */
        if (
            preg_match(
                '/^(?:rgb|rgba|hsl|hsla)'
                . '\([0-9.,%\s+-]+\)$/i',
                $value
            ) === 1
        ) {
            return $value;
        }

        /*
         * 基本的な色名
         */
        $allowedNames = [
            'black',
            'white',
            'red',
            'green',
            'blue',
            'yellow',
            'orange',
            'purple',
            'pink',
            'gray',
            'grey',
            'silver',
            'navy',
            'teal',
            'aqua',
            'lime',
            'maroon',
            'olive',
            'transparent',
            'currentcolor',
        ];

        return in_array(
            $value,
            $allowedNames,
            true
        )
            ? $value
            : null;
    }

    /*
     * 文字サイズを安全化
     */
    private static function sanitizeFontSize(
        string $value
    ): ?string {
        $value = strtolower(
            trim($value)
        );

        $keywords = [
            'xx-small',
            'x-small',
            'small',
            'medium',
            'large',
            'x-large',
            'xx-large',
            'smaller',
            'larger',
        ];

        if (
            in_array(
                $value,
                $keywords,
                true
            )
        ) {
            return $value;
        }

        if (
            preg_match(
                '/^([0-9]+(?:\.[0-9]+)?)'
                . '(px|pt|em|rem|%)$/',
                $value,
                $matches
            ) !== 1
        ) {
            return null;
        }

        $number = (float)$matches[1];

        if (
            $number <= 0 ||
            $number > 300
        ) {
            return null;
        }

        return $value;
    }

    /*
     * 文字装飾を安全化
     */
    private static function sanitizeTextDecoration(
        string $value
    ): ?string {
        $tokens = preg_split(
            '/\s+/',
            strtolower(trim($value))
        );

        if (!is_array($tokens)) {
            return null;
        }

        $allowedTokens = [
            'none',
            'underline',
            'line-through',
            'overline',
        ];

        foreach ($tokens as $token) {
            if (
                !in_array(
                    $token,
                    $allowedTokens,
                    true
                )
            ) {
                return null;
            }
        }

        $tokens = array_values(
            array_unique(
                array_filter($tokens)
            )
        );

        return $tokens !== []
            ? implode(' ', $tokens)
            : null;
    }

    /*
     * target属性を安全化
     */
    private static function sanitizeTarget(
        string $value
    ): ?string {
        $value = strtolower($value);

        return in_array(
            $value,
            [
                '_blank',
                '_self',
            ],
            true
        )
            ? $value
            : null;
    }

    /*
     * rel属性を安全化
     */
    private static function sanitizeRel(
        string $value
    ): ?string {
        $tokens = preg_split(
            '/\s+/',
            strtolower(trim($value))
        );

        if (!is_array($tokens)) {
            return null;
        }

        $allowedTokens = [
            'noopener',
            'noreferrer',
            'nofollow',
            'ugc',
            'sponsored',
        ];

        $safeTokens = [];

        foreach ($tokens as $token) {
            if (
                in_array(
                    $token,
                    $allowedTokens,
                    true
                )
            ) {
                $safeTokens[] = $token;
            }
        }

        $safeTokens = array_values(
            array_unique($safeTokens)
        );

        return $safeTokens !== []
            ? implode(' ', $safeTokens)
            : null;
    }

    /*
     * 数値属性を安全化
     */
    private static function sanitizeInteger(
        string $value,
        int $minimum,
        int $maximum
    ): ?string {
        if (
            preg_match(
                '/^-?[0-9]+$/',
                $value
            ) !== 1
        ) {
            return null;
        }

        $number = (int)$value;

        if (
            $number < $minimum ||
            $number > $maximum
        ) {
            return null;
        }

        return (string)$number;
    }

    /*
     * 文章属性を安全化
     */
    private static function sanitizeTextAttribute(
        string $value
    ): ?string {
        $value = preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            $value
        );

        if (!is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));

        if ($value === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr(
                $value,
                0,
                1000
            );
        }

        return substr(
            $value,
            0,
            1000
        );
    }

    /*
     * 未許可タグだけを取り除く
     */
    private static function unwrapElement(
        \DOMElement $element
    ): void {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while (
            $element->firstChild !== null
        ) {
            $parent->insertBefore(
                $element->firstChild,
                $element
            );
        }

        $parent->removeChild($element);
    }

    /*
     * DOM拡張がない場合の簡易安全化
     */
    private static function fallbackSanitize(
        string $html
    ): string {
        /*
         * 危険なタグは中身ごと削除
         */
        foreach (
            self::REMOVE_WITH_CONTENT
            as $tagName
        ) {
            $html = preg_replace(
                '#<'
                . preg_quote($tagName, '#')
                . '\b[^>]*>.*?</'
                . preg_quote($tagName, '#')
                . '\s*>#is',
                '',
                $html
            ) ?? '';
        }

        $allowedTags = '';

        foreach (
            array_keys(
                self::ALLOWED_ATTRIBUTES
            )
            as $tagName
        ) {
            $allowedTags .=
                '<' . $tagName . '>';
        }

        $html = strip_tags(
            $html,
            $allowedTags
        );

        /*
         * DOMがない場合は属性をすべて外し、
         * 安全性を優先する
         */
        $html = preg_replace_callback(
            '/<([a-z0-9]+)(?:\s[^>]*)?>/iu',
            static function (
                array $matches
            ): string {
                return '<'
                    . strtolower($matches[1])
                    . '>';
            },
            $html
        );

        return trim(
            is_string($html)
                ? $html
                : ''
        );
    }
}