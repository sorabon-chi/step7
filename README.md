# Step7 商品管理システム

「Cy Tech Step7.pdf」を正式な仕様書として作成した、Laravel 9の商品管理システムです。

リポジトリ: <https://github.com/sorabon-chi/step7>

## 実装機能

- ユーザー新規登録、ログイン、ログアウト
- 商品一覧表示、ページネーション
- 商品名の部分一致検索、メーカー検索、両条件を組み合わせた検索
- 商品詳細表示
- 商品新規登録、編集、削除
- 商品画像のアップロード、表示、差し替え

Step8のソート機能は実装していません。

## 動作確認環境

| 項目 | 確認環境 |
| --- | --- |
| OS・サーバー | Windows・MAMP |
| Laravel | 9.52.22 |
| PHP | 8.3.1 |
| MySQL | 5.7.24 |
| Apacheポート | 8888 |
| MySQLポート | 8889 |

PHP、Composer、Git、Node.js、npmを利用できる環境を使用します。
依存パッケージのバージョンは`composer.lock`と`package-lock.json`に記録しています。

## 構築済み環境での起動

1. MAMPのApacheとMySQLを起動します。
2. <http://localhost:8888/step7/public/login> を開きます。
3. 新規登録したアカウントでログインします。

確認したプロジェクトの配置先は`C:\MAMP\htdocs\step7`です。
以下の初回セットアップを、構築済みの現在の環境でやり直す必要はありません。

## 新しい環境への初回セットアップ

### 1. プロジェクトの取得

PowerShellでMAMPの`htdocs`に移動し、リポジトリを取得します。

```powershell
Set-Location C:\MAMP\htdocs
git clone https://github.com/sorabon-chi/step7.git step7
Set-Location .\step7
composer install
```

### 2. 環境設定

`.env`が存在しない場合だけ、ひな形から作成します。
`key:generate`は、この新規環境の初回セットアップで実行します。

```powershell
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
}
php artisan key:generate
```

MAMPのMySQLを起動し、phpMyAdminで空のデータベース`step7`を作成します。
`.env`の接続設定を使用環境に合わせます。上記のMAMP環境での設定例は次のとおりです。

```dotenv
APP_URL=http://localhost:8888/step7/public
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=step7
```

`DB_USERNAME`と`DB_PASSWORD`は、自分のMySQL接続情報を設定します。
パスワードや`APP_KEY`をREADMEに記載せず、`.env`もGitへ追加しません。

### 3. テーブル・初期データ・画像公開・画面資産の準備

```powershell
php artisan migrate --seed
php artisan storage:link
npm.cmd ci
npm.cmd run build
```

初期データはメーカーのCoca-Cola・サントリー・キリンの3件です。
商品やユーザーは画面から登録します。
画像の保存先は`storage/app/public`、公開先は`public/storage`です。

画像アップロードには、Apache側のPHPで`fileinfo`拡張が有効である必要があります。
CLIとApacheでは使用する`php.ini`が異なる場合があります。
今回のApacheは`C:\MAMP\conf\php8.3.1\php.ini`を使用しており、
`extension=php_fileinfo.dll`を有効にしたあと、MAMPのサーバーを再起動して動作確認しました。

セットアップ後、MAMPのApacheとMySQLを起動してログイン画面を開きます。

## 主なファイル

| 場所 | 内容 |
| --- | --- |
| `app/Models` | 商品・メーカー・売上・ユーザーのDB操作、トランザクション |
| `app/Http/Controllers` | 認証と商品管理の画面遷移・処理の呼び出し |
| `app/Http/Requests` | 検索・登録・編集時の入力チェック |
| `resources/views` | Bladeの画面 |
| `resources/sass`・`resources/js` | 外部CSS・JavaScriptのソース |
| `database/migrations`・`database/seeders` | テーブル定義・メーカー初期データ |
| `tests` | PHP・JavaScriptの自動テスト |

`products`・`companies`・`sales`は仕様書のカラム定義に合わせています。
`users`はLaravel標準のテーブルを使用します。

## 動作確認

次のコマンドは、上記のMAMP・PHP 8.3.1環境での実行例です。
PHPのテストでは、コマンド実行時だけSQLite拡張を読み込みます。
データベースを使う自動テストはメモリ上のSQLiteを使用します。

```powershell
php -d extension=C:\MAMP\bin\php\php8.3.1\ext\php_pdo_sqlite.dll vendor/bin/phpunit
node --test tests/JavaScript/product_delete.test.cjs
```

2026-10-09の自動テスト結果は、PHP 84件・672項目、JavaScript 4件すべて成功です。
その後、ユーザーのブラウザ操作による商品登録・検索・詳細・編集・画像表示・削除・ログアウトも確認しました。

検証結果、画面遷移、画像アップロード時の環境対応は、
[Step7の確認記録](docs/step7_verification.md)に記載しています。

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
