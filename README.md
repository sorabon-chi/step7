# Step7 商品管理システム

ユーザー登録・ログインと、商品の登録・検索・詳細表示・編集・削除、画像の保存に対応したLaravel 9の管理システムです。

## 使用環境

| 項目 | バージョン・設定 |
| --- | --- |
| OS・サーバー | Windows・MAMP |
| Laravel | 9.52.22 |
| PHP | 8.3.1 |
| MySQL | 5.7.24 |
| Apache / MySQLポート | 8888 / 8889 |

PHP、Composer、Git、Node.js、npmを使用します。

## 構築済み環境での起動

MAMPのApacheとMySQLを起動し、次のURLを開いてログインします。

<http://localhost:8888/step7/public/login>

プロジェクトの配置先は`C:\MAMP\htdocs\step7`です。

## 初回セットアップ

新しい環境で初回のみ実行します。構築済みの環境でやり直す必要はありません。

### 1. ファイルと依存パッケージを取得する

```powershell
Set-Location C:\MAMP\htdocs
git clone https://github.com/sorabon-chi/step7.git step7
Set-Location .\step7
composer install
npm.cmd ci
```

### 2. DBを作成し、接続情報を設定する

MAMPのMySQLを起動し、phpMyAdminで空のデータベース`step7`を作成します。
`.env`がない場合は、ひな形から作成します。

```powershell
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
}
```

`.env`を使用環境に合わせて設定します。上記MAMP環境の例は次のとおりです。

```dotenv
APP_URL=http://localhost:8888/step7/public
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=step7
```

`DB_USERNAME`と`DB_PASSWORD`にはMySQLの接続情報を設定します。
`.env`やパスワード、`APP_KEY`は公開せず、Gitに追加しないでください。

### 3. キー・テーブル・画像公開先・画面資産を準備する

```powershell
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm.cmd run build
```

セットアップ後は、上記の起動URLからユーザーを新規登録してログインします。

## 画像アップロード

Apache側のPHPで`fileinfo`拡張を有効にしてください。
CLIとApacheで使用する`php.ini`が異なる場合があります。設定変更後はMAMPを再起動します。

## License

Laravelは[MITライセンス](https://opensource.org/licenses/MIT)で公開されているフレームワークです。
