<?php

/*
 * 管理画面の権限定義
 */
return [
    'administrator' => [
        'label' => '管理者',
        'permissions' => [
            '*',
        ],
    ],

    'editor' => [
        'label' => '編集者',
        'permissions' => [
            'dashboard.view',
            'posts.view',
            'posts.create',
            'posts.edit_any',
            'posts.delete_any',
            'posts.publish',
            'categories.manage',
            'media.manage',
            'pages.manage',
        ],
    ],

    'author' => [
        'label' => '投稿者',
        'permissions' => [
            'dashboard.view',
            'posts.view',
            'posts.create',
            'posts.edit_own',
            'posts.delete_own',
            'posts.publish_own',
            'media.manage',
        ],
    ],

    'contributor' => [
        'label' => '寄稿者',
        'permissions' => [
            'dashboard.view',
            'posts.view',
            'posts.create',
            'posts.edit_own',
        ],
    ],

    'viewer' => [
        'label' => '閲覧者',
        'permissions' => [
            'dashboard.view',
        ],
    ],
];