<?php
/**
 * SNS同時投稿システム - 設定ファイル
 */

// .envファイルを読み込む
require_once __DIR__ . '/includes/env.php';
EnvLoader::load();

// データベース設定
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'sns_post_db'));
define('DB_USER', env('DB_USER', 'your_db_user'));
define('DB_PASS', env('DB_PASS', 'your_db_password'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// アプリケーション設定
define('BASE_URL', env('BASE_URL', 'https://your-domain.com'));
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// SNS API設定
// Instagram Graph API
define('INSTAGRAM_APP_ID', env('INSTAGRAM_APP_ID', 'your_instagram_app_id'));
define('INSTAGRAM_APP_SECRET', env('INSTAGRAM_APP_SECRET', 'your_instagram_app_secret'));
define('INSTAGRAM_ACCESS_TOKEN', env('INSTAGRAM_ACCESS_TOKEN', 'your_instagram_access_token'));
define('INSTAGRAM_USER_ID', env('INSTAGRAM_USER_ID', 'your_instagram_user_id'));

// X (Twitter) API
define('TWITTER_API_KEY', env('TWITTER_API_KEY', 'your_twitter_api_key'));
define('TWITTER_API_SECRET', env('TWITTER_API_SECRET', 'your_twitter_api_secret'));
define('TWITTER_ACCESS_TOKEN', env('TWITTER_ACCESS_TOKEN', 'your_twitter_access_token'));
define('TWITTER_ACCESS_TOKEN_SECRET', env('TWITTER_ACCESS_TOKEN_SECRET', 'your_twitter_access_token_secret'));
define('TWITTER_BEARER_TOKEN', env('TWITTER_BEARER_TOKEN', 'your_twitter_bearer_token'));

// Facebook Graph API
define('FACEBOOK_APP_ID', env('FACEBOOK_APP_ID', 'your_facebook_app_id'));
define('FACEBOOK_APP_SECRET', env('FACEBOOK_APP_SECRET', 'your_facebook_app_secret'));
define('FACEBOOK_ACCESS_TOKEN', env('FACEBOOK_ACCESS_TOKEN', 'your_facebook_access_token'));
define('FACEBOOK_PAGE_ID', env('FACEBOOK_PAGE_ID', 'your_facebook_page_id'));

// 管理画面認証
// 注意: パスワードは以下のコマンドで生成してください:
// php -r "echo password_hash('your_admin_password', PASSWORD_DEFAULT);"
define('ADMIN_USERNAME', env('ADMIN_USERNAME', 'admin'));
define('ADMIN_PASSWORD_HASH', env('ADMIN_PASSWORD_HASH', '$2y$10$YourGeneratedPasswordHashHere'));

// セッション設定
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // HTTPS時のみCookie送信
ini_set('session.cookie_samesite', 'Strict'); // CSRF対策強化
session_start();

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// エラーレポート（本番環境ではOFFに）
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// アップロードディレクトリの作成
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}
?>

