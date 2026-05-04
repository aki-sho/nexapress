# NexaPress

PHP / MySQL で構築したシンプルな自作CMSです。

![Version](https://img.shields.io/badge/version-v1.1.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.x-777bb4)
![MySQL](https://img.shields.io/badge/MySQL-supported-orange)

## 概要

NexaPress は、PHP と MySQL で作成したCMSです。

ブラウザから初期設定を行い、管理画面から記事を作成・編集・削除して、公開サイトに表示できます。

WordPress のように、サーバーへ配置してブラウザからインストールできるCMSを目指しています。

## 特徴

- ブラウザから初期設定が可能
- 管理者ログイン機能
- ダッシュボード表示
- 投稿の作成・編集・削除
- 下書き・公開状態の切り替え
- 公開サイトの記事一覧表示
- 公開サイトの記事詳細表示
- テーマ切り替え機能
- テーマごとのHTMLテンプレート対応
- ログイン中の管理者向けプレビューヘッダー表示
- default / MonoEdge テーマを同梱

## 主な機能

- インストール機能
- ログイン機能
- ダッシュボード
- 投稿一覧
- 投稿作成
- 投稿編集
- 投稿削除
- 公開状態の切り替え
- 記事一覧表示
- 記事詳細表示
- テーマ管理
- 管理者プレビュー用ヘッダー

## 必要環境

- PHP 8.x
- MySQL または MariaDB
- Apache

## 使い方

1. ファイル一式をサーバーに配置します。
2. ブラウザからインストール画面を開きます。
3. データベース情報と管理者情報を入力します。
4. 管理画面にログインします。
5. 投稿を作成し、公開します。
6. 必要に応じてテーマを切り替えます。

## ディレクトリ構成

```text
NexaPress/
├─ README.md
├─ app/
├─ config/
├─ public/
│  ├─ assets/
│  └─ themes/
│     ├─ default/
│     └─ monoedge/
└─ storage/