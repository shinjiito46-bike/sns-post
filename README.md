# SNS同時投稿システム

Instagram、X（Twitter）、Facebookへの同時投稿を可能にするWebアプリケーションです。

## 機能

- 📸 画像のアップロードと自動リサイズ
- 📱 スマホ対応の投稿フォーム
- 🔄 3つのSNSプラットフォームへの同時投稿
- 📊 投稿履歴の管理
- ✅ 投稿結果（成功/失敗）の記録
- 🗑️ 画像削除機能
- 🔐 管理画面の認証機能

## 要件

- PHP 7.4以上
- MySQL 5.7以上（またはMariaDB 10.2以上）
- GDライブラリ（画像リサイズ用）
- cURL拡張（SNS API連携用）
- Apache（mod_rewrite推奨）

## インストール

### 1. ファイルのアップロード

ロリポップサーバーにすべてのファイルをアップロードしてください。

### 2. データベースの作成

phpMyAdminまたはコマンドラインから `database.sql` を実行してデータベースとテーブルを作成します。

### 3. 環境変数の設定

機密情報は`.env`ファイルで管理します。

1. `.env.example`をコピーして`.env`を作成：
   ```bash
   cp .env.example .env
   ```

2. `.env`ファイルを開いて、以下の設定を行ってください：

   - **データベース接続情報**
     - `DB_HOST`: データベースホスト（通常は `localhost`）
     - `DB_NAME`: データベース名
     - `DB_USER`: データベースユーザー名
     - `DB_PASS`: データベースパスワード

   - **アプリケーション設定**
     - `BASE_URL`: あなたのドメイン（例: `https://example.com`）

   - **SNS API設定**
     - Instagram Graph APIの認証情報
     - X (Twitter) APIの認証情報
     - Facebook Graph APIの認証情報

   - **管理画面認証**
     - `ADMIN_USERNAME`: 管理画面のユーザー名（デフォルト: `admin`）
     - `ADMIN_PASSWORD_HASH`: 管理画面のパスワードハッシュ
       
       **パスワードハッシュの生成方法（2つの方法）:**
       
       1. **Webインターフェースを使用（推奨）:**
          - `setup_password.php`にブラウザでアクセス
          - ユーザー名とパスワードを入力
          - 生成されたハッシュを`.env`ファイルに設定
          - **重要**: 使用後は`setup_password.php`を削除してください
       
       2. **コマンドラインで生成:**
          ```bash
          php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
          ```
          生成されたハッシュを`.env`ファイルの`ADMIN_PASSWORD_HASH`に設定

**重要**: `.env`ファイルは機密情報を含むため、Gitにコミットしないでください（`.gitignore`に含まれています）。

### 4. ディレクトリの権限設定

以下のディレクトリに書き込み権限を付与してください：

```bash
chmod 755 uploads/
chmod 755 logs/
```

### 5. .htaccessの確認

ロリポップサーバーで`.htaccess`が有効になっているか確認してください。

## SNS APIの設定方法

### Instagram Graph API

1. [Facebook Developers](https://developers.facebook.com/)でアプリを作成
2. Instagram Graph APIを有効化
3. アクセストークンを取得
4. `.env`ファイルに認証情報を設定

### X (Twitter) API

1. [Twitter Developer Portal](https://developer.twitter.com/)でアプリを作成
2. APIキーとアクセストークンを取得
3. `.env`ファイルに認証情報を設定

### Facebook Graph API

1. [Facebook Developers](https://developers.facebook.com/)でアプリを作成
2. Facebook Graph APIを有効化
3. ページアクセストークンを取得
4. `.env`ファイルに認証情報を設定

## 使用方法

### 投稿フォーム

1. `index.php`にアクセス
2. 画像を選択
3. キャプションを入力（任意）
4. 「投稿する」ボタンをクリック

### 管理画面

1. `admin.php`にアクセス
2. ログイン情報を入力（`.env`で設定したユーザー名とパスワード）
3. 投稿履歴を確認
4. 必要に応じて画像を削除

**セキュリティ機能:**
- セッションタイムアウト: 30分間無操作で自動ログアウト
- ログイン試行制限: 5回失敗で15分間ロック
- CSRFトークン保護

## セキュリティ

- CSRFトークンによる保護
- SQLインジェクション対策（PDO使用）
- XSS対策
- ファイルアップロードの検証
- 管理画面の認証

## トラブルシューティング

### 画像がアップロードできない

- `uploads/`ディレクトリの権限を確認
- PHPの`upload_max_filesize`と`post_max_size`を確認
- `.htaccess`の設定を確認

### SNS投稿が失敗する

- API認証情報が正しいか確認
- APIのレート制限に達していないか確認
- エラーログ（`logs/error.log`）を確認

### データベース接続エラー

- `.env`ファイルのデータベース設定を確認
- `.env`ファイルが正しく読み込まれているか確認（`includes/env.php`のエラーログを確認）
- データベースサーバーが起動しているか確認

## ライセンス

このプロジェクトはMITライセンスの下で公開されています。

## サポート

問題が発生した場合は、エラーログ（`logs/error.log`）を確認してください。

