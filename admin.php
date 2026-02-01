<?php
/**
 * 管理画面
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// セッションタイムアウト設定（30分）
define('SESSION_TIMEOUT', 30 * 60); // 30分

// ログイン試行回数の制限
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['login_attempt_time'])) {
    $_SESSION['login_attempt_time'] = 0;
}

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // CSRF検証
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $loginError = 'セキュリティトークンが無効です。ページを再読み込みしてください。';
    }

    // ログイン試行回数のチェック（5回失敗で15分ロック）
    if (!isset($loginError) && $_SESSION['login_attempts'] >= 5) {
        $lockTime = 15 * 60; // 15分
        if (time() - $_SESSION['login_attempt_time'] < $lockTime) {
            $remainingTime = ceil(($lockTime - (time() - $_SESSION['login_attempt_time'])) / 60);
            $loginError = "ログイン試行回数が上限に達しました。{$remainingTime}分後に再試行してください。";
        } else {
            // ロック時間が過ぎたのでリセット
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_attempt_time'] = 0;
        }
    }
    
    if (!isset($loginError)) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // パスワードハッシュが設定されているか確認
        if (ADMIN_PASSWORD_HASH === '$2y$10$YourGeneratedPasswordHashHere' || empty(ADMIN_PASSWORD_HASH)) {
            $loginError = 'パスワードが設定されていません。.envファイルでADMIN_PASSWORD_HASHを設定してください。';
        } elseif ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
            // ログイン成功 - セッション固定攻撃対策
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_attempt_time'] = 0;
            header('Location: admin.php');
            exit;
        } else {
            // ログイン失敗
            $_SESSION['login_attempts']++;
            $_SESSION['login_attempt_time'] = time();
            $loginError = 'ユーザー名またはパスワードが正しくありません';
        }
    }
}

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ログインチェック
$isLoggedIn = false;
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // セッションタイムアウトチェック
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) < SESSION_TIMEOUT) {
        $isLoggedIn = true;
        // アクティビティがある場合はログイン時間を更新
        $_SESSION['login_time'] = time();
    } else {
        // セッションタイムアウト
        session_destroy();
        session_start();
        $loginError = 'セッションがタイムアウトしました。再度ログインしてください。';
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - SNS同時投稿</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="container">
        <?php if (!$isLoggedIn): ?>
            <!-- ログイン画面 -->
            <div class="login-container">
                <h1>管理画面ログイン</h1>
                <?php if (isset($loginError)): ?>
                    <div class="alert alert-error"><?php echo h($loginError); ?></div>
                <?php endif; ?>
                <form method="POST" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <div class="form-group">
                        <label for="username">ユーザー名</label>
                        <input type="text" id="username" name="username" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password">パスワード</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary">ログイン</button>
                    <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 0 && $_SESSION['login_attempts'] < 5): ?>
                        <div style="margin-top: 10px; font-size: 12px; color: #856404;">
                            ログイン試行回数: <?php echo $_SESSION['login_attempts']; ?>/5
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        <?php else: ?>
            <!-- 管理画面 -->
            <header class="admin-header">
                <h1>投稿管理</h1>
                <a href="?logout" class="btn btn-secondary">ログアウト</a>
            </header>

            <main class="admin-main">
                <div class="admin-controls">
                    <button id="refreshBtn" class="btn btn-primary">更新</button>
                </div>

                <div id="loadingIndicator" class="loading" style="display: none;">読み込み中...</div>

                <div id="postsContainer" class="posts-container">
                    <!-- 投稿一覧がここに表示されます -->
                </div>

                <div id="pagination" class="pagination"></div>
            </main>
        <?php endif; ?>
    </div>

    <script>
        const csrfToken = '<?php echo h($csrfToken); ?>';
    </script>
    <script src="assets/js/admin.js"></script>
</body>
</html>

