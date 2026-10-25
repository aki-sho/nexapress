<?php

namespace app\Core;

use RuntimeException;

class ExtensionCompatibility
{
    public static function issues(array $manifest, ?string $cms = null, ?string $php = null): array
    {
        $requires = $manifest['requires'] ?? [];
        if (!is_array($requires)) {
            return ['対応環境の定義が正しくありません。'];
        }
        $cms = $cms ?? (string)((require BASE_PATH . '/config/version.php')['version'] ?? '0.0.0');
        $php = $php ?? PHP_VERSION;
        $issues = [];
        foreach (['nexapress' => [$cms, 'NexaPress'], 'php' => [$php, 'PHP']] as $key => [$current, $label]) {
            if (!isset($requires[$key])) {
                continue;
            }
            $constraint = $requires[$key];
            if (!is_string($constraint) || !preg_match('/^(>=|<=|>|<|==|=)?\s*v?(\d+\.\d+(?:\.\d+)?(?:-[0-9a-z.-]+)?)$/i', trim($constraint), $parts)) {
                $issues[] = $label . 'の対応バージョンの指定を確認できません。';
                continue;
            }
            $operator = ($parts[1] ?? '') ?: '>=';
            if ($operator === '=') {
                $operator = '==';
            }
            if (!version_compare($current, $parts[2], $operator)) {
                $issues[] = $label . ' ' . $constraint . ' が必要です（現在 ' . $current . '）。';
            }
        }
        return $issues;
    }

    public static function requireCompatible(array $manifest): void
    {
        $issues = self::issues($manifest);
        if ($issues) {
            throw new RuntimeException(implode(' ', $issues));
        }
    }
}
