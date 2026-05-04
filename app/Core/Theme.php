<?php

namespace app\Core;

class Theme
{
    public static function active(): string
    {
        // 現在有効なテーマ設定ファイルの場所
        $configPath = BASE_PATH . '/config/theme.php';

        // 設定ファイルがない場合は default テーマを使う
        if (!file_exists($configPath)) {
            return 'default';
        }

        // config/theme.php から現在のテーマ名を取得する
        $config = require $configPath;
        $theme = $config['active_theme'] ?? 'default';

        // 設定されているテーマが存在しない場合は default に戻す
        if (!self::exists($theme)) {
            return 'default';
        }

        return $theme;
    }

    public static function exists(string $theme): bool
    {
        // 不正な文字が入らないようにテーマ名を整形する
        $theme = self::sanitize($theme);

        // 空文字の場合は存在しない扱い
        if ($theme === '') {
            return false;
        }

        // テーマフォルダが存在し、style.css がある場合だけテーマとして認識する
        return is_dir(BASE_PATH . '/public/themes/' . $theme)
            && file_exists(BASE_PATH . '/public/themes/' . $theme . '/style.css');
    }

    public static function all(): array
    {
        // テーマ一覧を探す場所
        $themesPath = BASE_PATH . '/public/themes';

        // themes フォルダがない場合は空配列を返す
        if (!is_dir($themesPath)) {
            return [];
        }

        $items = scandir($themesPath);
        $themes = [];

        foreach ($items as $item) {
            // . と .. は除外する
            if ($item === '.' || $item === '..') {
                continue;
            }

            $themePath = $themesPath . '/' . $item;

            // フォルダ以外はテーマとして扱わない
            if (!is_dir($themePath)) {
                continue;
            }

            // style.css がないものはテーマとして扱わない
            if (!file_exists($themePath . '/style.css')) {
                continue;
            }

            // 初期値としてフォルダ名をテーマ名にする
            $name = $item;
            $description = '';

            // theme.php がある場合は、表示名と説明文を読み込む
            if (file_exists($themePath . '/theme.php')) {
                $info = require $themePath . '/theme.php';
                $name = $info['name'] ?? $item;
                $description = $info['description'] ?? '';
            }

            $themes[] = [
                'id' => $item,
                'name' => $name,
                'description' => $description,
            ];
        }

        return $themes;
    }

    public static function set(string $theme): bool
    {
        // 保存前にテーマ名を整形する
        $theme = self::sanitize($theme);

        // 存在しないテーマは設定しない
        if (!self::exists($theme)) {
            return false;
        }

        // テーマ設定ファイルの保存先
        $configPath = BASE_PATH . '/config/theme.php';

        // 現在のテーマ設定を書き換える
        $content = "<?php\n\nreturn [\n    'active_theme' => '" . $theme . "',\n];\n";

        return file_put_contents($configPath, $content) !== false;
    }

    public static function template(string $name): ?string
    {
        // 現在有効なテーマを取得する
        $theme = self::active();

        // 余計な / を削る
        $name = trim($name, '/');

        // テーマ内のテンプレートファイルを探す
        $path = BASE_PATH . '/public/themes/' . $theme . '/templates/' . $name . '.php';

        // テンプレートが存在すれば、そのパスを返す
        if (file_exists($path)) {
            return $path;
        }

        // 見つからない場合は null を返す
        return null;
    }

    public static function part(string $name): ?string
    {
        // 余計な / を削る
        $name = trim($name, '/');

        // header パーツは、NexaPress 本体側で出力可否を制御する
        // テーマ側にログイン判定を書かせないため、ここで header の読み込みを止める
        if ($name === 'header' && !self::isLoggedIn()) {
            return null;
        }

        // 現在有効なテーマを取得する
        $theme = self::active();

        // テーマ内の parts ファイルを探す
        $path = BASE_PATH . '/public/themes/' . $theme . '/templates/parts/' . $name . '.php';

        // パーツが存在すれば、そのパスを返す
        if (file_exists($path)) {
            return $path;
        }

        // 見つからない場合は null を返す
        return null;
    }

    private static function isLoggedIn(): bool
    {
        // Auth クラスに check() がある場合は、それを使ってログイン状態を判定する
        if (class_exists(Auth::class) && method_exists(Auth::class, 'check')) {
            return Auth::check();
        }

        // Auth::check() がない場合の保険
        // セッションに user_id があればログイン中と判断する
        return !empty($_SESSION['user_id']);
    }

    private static function sanitize(string $theme): string
    {
        // テーマ名に使える文字だけ残す
        // ディレクトリ移動などの不正な指定を防ぐ
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $theme);
    }
}