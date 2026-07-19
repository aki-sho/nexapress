<?php

return [
    'catalog_url' =>
        'https://raw.githubusercontent.com/'
        . 'aki-sho/nexapress/main/extension-catalog.json',

    'cache_ttl' => 21600,
    'timeout' => 15,
    'download_timeout' => 300,

    // JSON最大1MB
    'max_json_size' => 1048576,

    // ZIP最大50MB
    'max_package_size' => 52428800,

    // ZIP展開後最大100MB
    'max_extracted_size' => 104857600,

    // ZIP内の最大ファイル数
    'max_package_files' => 2000,

    'allowed_manifest_hosts' => [
        'raw.githubusercontent.com',
    ],

    'allowed_image_hosts' => [
        'raw.githubusercontent.com',
    ],

    'allowed_download_hosts' => [
        'github.com',
    ],
];