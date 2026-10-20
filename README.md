# Step7 商品管理システム

「Cy Tech Step7.pdf」の仕様に沿って、Laravel 9で商品管理システムを作成しました。
商品の登録や検索、編集、削除などができます。

リポジトリ：https://github.com/sorabon-chi/step7

## できること

* ユーザー登録、ログイン、ログアウト
* 商品一覧の表示、ページ切り替え
* 商品名やメーカーでの検索
* 商品の詳細表示
* 商品の登録、編集、削除
* 商品画像のアップロード、表示、差し替え

商品名は一部の文字でも検索できます。メーカーと組み合わせた検索にも対応しています。
Step8のソート機能は含めていません。

## 使用環境

以下の環境で動作を確認しました。

| 項目        | バージョン・設定 |
| --------- | -------- |
| OS        | Windows  |
| 開発環境      | MAMP     |
| Laravel   | 9.52.22  |
| PHP       | 8.3.1    |
| MySQL     | 5.7.24   |
| Apacheポート | 8888     |
| MySQLポート  | 8889     |

セットアップには、PHP・Composer・Git・Node.js・npmを使用します。

## 起動方法

セットアップ済みの場合は、MAMPのApacheとMySQLを起動して、以下のURLを開いてください。

http://localhost:8888/step7/public/login

新規登録したアカウントでログインできます。
プロジェクトは `C:\MAMP\htdocs\step7` に配置しています。

## 初めてセットアップする場合

現在の環境でセットアップが済んでいる場合は、以下の作業は不要です。

### 1. プロジェクトを取得する

PowerShellで以下を実行します。

```powershell
Set-Location C:\MAMP\htdocs
git clone https://github.com/sorabon-chi/step7.git step7
Set-Location .\step7
composer install
```

### 2. 接続設定をする

`.env` がない場合は、`.env.example` からコピーします。
アプリケーションキーの作成は、新しい環境での初回セットアップ時に行います。

```powershell
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
}
php artisan key:generate
```

MAMPのMySQLを起動し、phpMyAdminで `step7` という空のデータベースを作成してください。

`.env` の設定例は以下のとおりです。ポート番号などは使用する環境に合わせて変更してください。

```dotenv
APP_URL=http://localhost:8888/step7/public
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=step7
```

`DB_USERNAME` と `DB_PASSWORD` には、自分のMySQLの接続情報を設定します。
`.env` にはパスワードなどが含まれるため、Gitには追加しないようにしてください。

### 3. データベースや画面の準備をする

```powershell
php artisan migrate --seed
php artisan storage:link
npm.cmd ci
npm.cmd run build
```

メーカーの初期データとして、Coca-Cola・サントリー・キリンの3件が登録されます。
ユーザーと商品は、画面から登録してください。

準備ができたら、MAMPのApacheとMySQLを起動してログイン画面を開きます。

### 画像アップロードについて

画像は `storage/app/public` に保存し、`public/storage` を通して表示します。

画像アップロードには、Apache側のPHPで `fileinfo` 拡張を有効にする必要があります。
今回の環境では、以下のファイルで `extension=php_fileinfo.dll` を有効にし、MAMPを再起動しました。

```text
C:\MAMP\conf\php8.3.1\php.ini
```

コマンド実行時とApacheで使う `php.ini` が異なる場合があるので、設定するファイルに注意してください。

## ファイル構成

| 場所                                       | 内容             |
| ---------------------------------------- | -------------- |
| `app/Models`                             | データベースに関する処理   |
| `app/Http/Controllers`                   | 画面表示や各処理の呼び出し  |
| `app/Http/Requests`                      | 入力内容のチェック      |
| `resources/views`                        | 画面の表示          |
| `resources/sass`・`resources/js`          | CSS・JavaScript |
| `database/migrations`・`database/seeders` | テーブルの作成や初期データ  |
| `tests`                                  | 自動テスト          |

商品・メーカー・売上のテーブルは仕様書に合わせて作成し、ユーザーはLaravel標準のテーブルを使用しています。

## 動作確認

自動テストに加えて、ブラウザでも商品の登録・検索・詳細表示・編集・画像表示・削除・ログアウトを確認しました。

2026年10月9日時点の自動テスト結果は以下のとおりです。

* PHP：84件のテスト、672項目の確認が成功
* JavaScript：4件のテストがすべて成功

確認時に使用したコマンドです。PHPのテストでは、実行時だけSQLite拡張を読み込み、メモリ上のデータベースを使用しています。

```powershell
php -d extension=C:\MAMP\bin\php\php8.3.1\ext\php_pdo_sqlite.dll vendor/bin/phpunit
node --test tests/JavaScript/product_delete.test.cjs
```

詳しい確認内容は、[Step7の確認記録](docs/step7_verification.md)にまとめています。

## License

Laravelは[MITライセンス](https://opensource.org/licenses/MIT)で公開されているフレームワークです。
