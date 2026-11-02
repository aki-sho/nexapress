# 本体機能の統合テスト

PHP 8.0以上、PDO MySQL、cURL、Fileinfo、ZipArchive、Python 3、OpenSSLコマンドを使用します。
実行先には専用のMySQL/MariaDB検証インスタンスを指定してください。テストごとにランダム名のDBを作成します。
サイトファイルはOSの一時フォルダにコピーし、実際のサイト設定・データを変更しません。

PowerShellでの実行例：

```powershell
$env:NEXAPRESS_TEST_HOST = '127.0.0.1;port=13316'
$env:NEXAPRESS_TEST_USER = 'root'
$env:NEXAPRESS_TEST_PASSWORD = '検証インスタンスのパスワード'
python tests/run.py
python tests/run.py --from-2.2
```

ブラウザ検証にはNode.js、Playwright、Chromeが必要です。

```powershell
$env:NEXAPRESS_TEST_CHROME = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
python tests/run.py --browser --output "$env:TEMP\nexapress-screenshots"
```

Playwrightが別の場所にある場合は `NODE_PATH` を設定します。`NEXAPRESS_TEST_CLASSIC_EDITOR` に展開済みClassic Editorのパスを指定すると、有効化した状態でもブラウザ検証します。

`NEXAPRESS_TEST_NITRO_CACHE` に展開済みNitro Cacheを指定すると、HTMLキャッシュを有効にした状態でメニュー・固定ページなどの反映を確認します。検証サーバーは `session.cache_limiter` を空にして、セッションの自動ヘッダーがキャッシュを妨げない構成にします。

テスト対象は、DB移行と再実行、新規インストール、ページ履歴とゴミ箱、公開範囲、検索・ページ送り、メニュー、権限とCSRF、プロフィール、拡張機能のHTTPS更新・設定維持・バックアップ・不正パッケージ拒否です。ブラウザでは自動保存・復元・保存エラー後の入力保護、未保存警告、メニュー編集、両テーマのPC・モバイル表示を確認します。

テスト終了時は検証DBと一時ファイルを削除します。`--keep` を指定すると調査用に残します。その場合は表示された `site` のパスを使い、同じ検証DB環境変数で次を実行するとDBを削除できます。

```powershell
php tests/fixture.php '表示されたsiteのパス' cleanup
```

HTTPSパッケージの検証は一時的なローカルCA証明書を明示的に信頼して行います。TLS検証は無効化しません。


## 公開旧版からの本体更新

DB移行テストとは別に、Gitタグから取得した無改変の旧版を起動し、実際の管理画面の更新POSTを確認します。事前に `git fetch --tags` を実行してください。

```powershell
python tests/updater-release.py --package update-package/nexapress-update-2.4.2.zip
```

既定では2.2.0・2.3.0・2.4.0・2.4.1を対象に、TLS検証を有効にしたローカル配信先へ通信だけを接続します。製品の更新処理、ZIP検証、ハッシュ確認、バックアップ、ファイル配置、DB移行は差し替えません。設定・投稿・利用者・拡張状態・アップロードの維持、失敗時の版維持、バックアップからの実復元も確認します。

公開後は `--public` を指定すると、接続差し替えなしで実際のGitHub最新版APIと公開ZIPを使用します。最新版APIが指定ZIPと同じ版を案内している状態で実行してください。`--ca-file` で検証環境が信頼するCA証明書を指定できます。TLS検証は無効化しません。

```powershell
python tests/updater-release.py --package update-package/nexapress-update-2.4.2.zip --public --from-ref v2.4.0
```

`--reproduce-package` に2.4.1の更新ZIPを指定すると、旧版での拒否と未変更を確認した後、修正版へ更新する経路も検証します。ランダム名の専用検証DBは終了時に削除し、一時サイトとHTTP結果は調査用に残します。
