<?php
/**
 * 共通関数
 */

/**
 * セキュリティ: XSS対策
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * セキュリティ: CSRFトークン生成
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * セキュリティ: CSRFトークン検証
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * ファイル名の安全な生成
 */
function generateSafeFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $basename = pathinfo($originalName, PATHINFO_FILENAME);
    $safeBasename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
    return $safeBasename . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
}

/**
 * JSONレスポンス送信
 */
function sendJSONResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * エラーレスポンス送信
 */
function sendErrorResponse($message, $statusCode = 400) {
    sendJSONResponse(['success' => false, 'error' => $message], $statusCode);
}

/**
 * 成功レスポンス送信
 */
function sendSuccessResponse($data = []) {
    sendJSONResponse(['success' => true] + $data);
}

/**
 * ログ記録
 */
function writeLog($message, $level = 'INFO') {
    $logFile = __DIR__ . '/../logs/app.log';
    $logDir = dirname($logFile);

    // ログディレクトリが存在しない場合は作成
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true)) {
            error_log("Failed to create log directory: {$logDir}");
            return false;
        }
    }

    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";

    $result = file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    if ($result === false) {
        error_log("Failed to write to log file: {$logFile}");
        return false;
    }
    return true;
}
?>

