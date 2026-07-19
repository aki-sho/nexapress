<?php

$title = 'NexaPress インストール';

?>

<div class="container">
    <div class="card">
        <h1>NexaPressへようこそ</h1>

        <p>
            インストールを開始する前に、
            使用するデータベースを準備してください。
        </p>

        <h2>インストールで設定する内容</h2>

        <ul>
            <li>データベース接続情報</li>
            <li>テーブル接頭辞</li>
            <li>サイトタイトル</li>
            <li>管理者アカウント</li>
            <li>検索エンジン表示設定</li>
        </ul>

        <p>
            データベースは事前に作成しておく必要があります。
        </p>

        <p>
            <a
                href="<?= url('install/database') ?>"
                class="button"
            >
                インストールを開始する
            </a>
        </p>
    </div>
</div>