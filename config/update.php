<?php

return [
    // 更新元のGitHubリポジトリ
    'repository' => 'aki-sho/nexapress',

    // 更新情報を再取得するまでの秒数
    'cache_ttl' => 21600,

    // GitHub APIの接続タイムアウト
    'timeout' => 15,

    // 更新ZIPのダウンロード制限時間
    'download_timeout' => 300,

    // 更新ZIPの最大サイズ（50MB）
    'max_package_size' => 52428800,

    // ZIP展開後の最大サイズ（200MB）
    'max_extracted_size' => 209715200,

    // ZIPに含められる最大ファイル数
    'max_package_files' => 5000,

    // 更新時に上書きしない場所
    'protected_paths' => [
        'config/config.php',
        'config/general.php',
        'config/url.php',
        'config/theme.php',
        'config/debug.php',
        'extensions',
        'public/uploads',
        'storage',
    ],
    // 更新ZIPから上書きできる場所
    'allowed_update_paths' => [
        'app',
        'config/update.php',
        'config/extensions.php',
        'config/version.php',
        'database/migrations',
        'public/index.php',
        'public/.htaccess',
        'public/assets',
        'public/themes/default',
        'public/themes/monoedge',
        'index.php',
        '.htaccess',
        'extension-catalog.json',
        'README.md',
    ],
];