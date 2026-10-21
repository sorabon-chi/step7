# Step7 商品管理システム

「Cy Tech Step7.pdf」を仕様書とする、Laravel 9の商品管理システムです。
構築手順は「システム構築手順 - システム構築手順.pdf」を参照しています。

リポジトリ: <https://github.com/sorabon-chi/step7>

## 機能

- ユーザー新規登録、ログイン、ログアウト
- 商品一覧、商品名の部分一致検索、メーカー検索、複合検索
- 商品の詳細表示、新規登録、編集、削除
- 商品画像の保存、表示、差し替え

一覧は検索条件がない場合に全商品を対象とし、10件ずつ表示します。
売上から参照されている商品は削除できません。
ソート機能はStep8の対象のため実装していません。
仕様の解釈は[確認記録](docs/step7_verification.md)にまとめています。

## 動作環境

| 項目 | 確認した環境 |
| --- | --- |
| OS・サーバー | Windows・MAMP |
| Laravel | 9.52.22 |
| PHP | 8.3.1 |
| MySQL | 5.7.24 |
| Apache / MySQLポート | 8888 / 8889 |

PHP、Composer、Git、Node.js、npmが必要です。
依存パッケージは`composer.lock`と`package-lock.json`に記録しています。

## 普段の起動

1. MAMPのApacheとMySQLを起動します。
2. <http://localhost:8888/step7/public/login> を開きます。
3. 登録済みのアカウントでログインします。

プロジェクトの配置先は`C:\MAMP\htdocs\step7`です。
構築済みの環境では、次の初回構築手順を繰り返す必要はありません。

## 初回構築

以下は、新しい環境にリポジトリを取得するときだけ行う手順です。

### 1. ファイルと依存パッケージを取得する

```powershell
Set-Location C:\MAMP\htdocs
git clone https://github.com/sorabon-chi/step7.git step7
Set-Location .\step7
composer install
npm.cmd ci
```

### 2. 接続情報を設定する

MAMPのMySQLを起動し、phpMyAdminで空のデータベース`step7`を作成します。
`.env`がない場合だけ、ひな形から作成します。
新しい環境の初回構築時にアプリの鍵を生成します。

```powershell
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
}
php artisan key:generate
```

`.env`の設定例です。ポートなどは使用する環境に合わせてください。

```dotenv
APP_URL=http://localhost:8888/step7/public
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=step7
```

`DB_USERNAME`・`DB_PASSWORD`には、使用環境のMySQL接続情報を設定します。
パスワードや`APP_KEY`は公開せず、`.env`をGitへ追加しないでください。

### 3. テーブル・画像公開先・画面資産を準備する

```powershell
php artisan migrate --seed
php artisan storage:link
npm.cmd run build
```

メーカーの初期データはCoca-Cola・サントリー・キリンの3件です。
商品とユーザーは画面から登録します。
画像は`storage/app/public`へ保存し、`public/storage`を通して表示します。

画像アップロードには、Apache側のPHPで`fileinfo`拡張が有効である必要があります。
CLIとApacheでは、使用する`php.ini`が異なる場合があります。
上記環境のApache設定ファイルは`C:\MAMP\conf\php8.3.1\php.ini`です。
設定を変更した場合はMAMPのサーバーを再起動します。

## 自動テスト

次のコマンドは、上記のMAMP・PHP 8.3.1環境での実行例です。

```powershell
php -d extension=C:\MAMP\bin\php\php8.3.1\ext\php_pdo_sqlite.dll vendor/bin/phpunit
node --test tests/JavaScript/product_delete.test.cjs
```

PHPUnitはメモリ上のSQLiteを使用し、画像を扱うテストは専用ストレージを使用します。
PHPのSQLite拡張は、このコマンドの実行時だけ読み込みます。

2026-10-20の自動テストは、PHP 85件・682項目、JavaScript 4件すべて成功しています。
2026-10-20～21の再確認では、実ブラウザでの操作は未実施です。
過去の結果・HTTP確認・画面操作の記録は、[確認記録](docs/step7_verification.md)に記載しています。

## License

Laravelは[MITライセンス](https://opensource.org/licenses/MIT)で公開されているフレームワークです。
