# NexaPress

PHP / MySQLで構築した、自作のシンプルなCMSです。

![Version](https://img.shields.io/badge/version-v2.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.x-777bb4)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-supported-orange)

## 概要

NexaPressは、サーバーへ配置してブラウザからインストールできるCMSです。

管理画面から投稿・固定ページ・メディア・テーマ・各種設定を管理できます。

## 主な機能

- ブラウザからの初期インストール
- 2段階の初期インストール
- データベース接続確認
- テーブル接頭辞設定
- サイトタイトルの初期設定
- 管理者ユーザー名の設定
- ユーザー名またはメールアドレスでのログイン
- 検索エンジンのインデックス抑制設定
- 管理者ログイン
- ダッシュボードとバージョン表示
- 投稿の追加・編集・削除
- 下書き・公開状態の切り替え
- カテゴリ管理
- 固定ページ管理
- 投稿・固定ページのURL形式設定
- メディア管理
- 画像・音声・動画・文書のアップロード
- メディアのタイトル・説明編集
- サイトタイトル・タイムゾーン・サイトアイコン設定
- デバッグ設定
- テーマ切り替え
- default / MonoEdgeテーマを同梱
- 管理者プレビューヘッダー
- 拡張機能のZIPアップロード
- 拡張機能の有効化・無効化・削除
- 拡張機能ごとの管理ダッシュボード
- 有効な拡張機能のサイドメニュー表示

## 必要環境

- PHP 8.x
- MySQL または MariaDB
- Apache
- mod_rewrite
- PDO MySQL
- Fileinfo
- ZipArchive

## インストール

1. Releasesから最新版をダウンロードします。
2. ZIPを展開して、Webサーバーの公開フォルダへ配置します。
3. NexaPressで使用するMySQLまたはMariaDBのデータベースを作成します。
4. ブラウザからNexaPressのURLを開きます。
5. データベース情報とテーブル接頭辞を入力します。
6. サイトタイトル、管理者アカウント、検索エンジン表示設定を入力します。
7. インストール完了後、管理画面へログインします。

データベース設定で入力する項目：

- データベース名
- データベースのユーザー名
- データベースのパスワード
- データベースホスト
- テーブル接頭辞

サイト設定で入力する項目：

- サイトのタイトル
- 管理者ユーザー名
- 管理者パスワード
- 管理者メールアドレス
- 検索エンジンでの表示設定

例：

```text
サイト：
http://localhost/nexapress-2.0.0/

ログイン画面：
http://localhost/nexapress-2.0.0/admin/login

管理画面：
http://localhost/nexapress-2.0.0/admin

## 拡張機能

拡張機能は、管理画面の「拡張機能」からZIP形式で追加します。

ZIPの基本構成：

```text
sample-extension/
├─ manifest.json
├─ bootstrap.php
└─ admin/
   └─ dashboard.php
```

`manifest.json` の例：

```json
{
  "id": "sample-extension",
  "name": "サンプル拡張機能",
  "description": "拡張機能の説明です。",
  "version": "1.0.0",
  "bootstrap": "bootstrap.php",
  "admin": {
    "menu_label": "サンプル",
    "dashboard": "admin/dashboard.php"
  }
}
```

`bootstrap` と管理ダッシュボードは、必要な拡張機能だけ指定します。

## 主なディレクトリ

```text
NexaPress/
├─ app/
├─ config/
├─ extensions/
├─ public/
│  ├─ assets/
│  ├─ themes/
│  └─ uploads/
└─ storage/
```

## 注意

NexaPressは現在開発中です。本番環境で使用する場合は、事前に十分な動作確認を行ってください。