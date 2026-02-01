<?php
/**
 * 画像削除処理
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 管理画面からのみアクセス可能（session_start()はconfig.phpで実行済み）
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sendErrorResponse('認証が必要です', 401);
}

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('POSTリクエストのみ許可されています', 405);
}

// CSRFトークン検証
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    sendErrorResponse('CSRFトークンが無効です', 403);
}

$postId = $_POST['post_id'] ?? null;
if (!$postId || !is_numeric($postId)) {
    sendErrorResponse('投稿IDが無効です');
}

try {
    $db = getDB();

    // 投稿情報を取得
    $stmt = $db->prepare("SELECT image_path FROM posts WHERE id = :id");
    $stmt->execute([':id' => $postId]);
    $post = $stmt->fetch();

    if (!$post) {
        sendErrorResponse('投稿が見つかりません', 404);
    }

    // リサイズ画像のパスを取得
    $stmt = $db->prepare("SELECT resized_path FROM image_resizes WHERE post_id = :id");
    $stmt->execute([':id' => $postId]);
    $resizedImages = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // ファイルを削除
    $deletedFiles = [];
    $failedFiles = [];

    // 元画像を削除
    if (file_exists($post['image_path'])) {
        if (unlink($post['image_path'])) {
            $deletedFiles[] = $post['image_path'];
        } else {
            $failedFiles[] = $post['image_path'];
        }
    }

    // リサイズ画像を削除
    foreach ($resizedImages as $resizedPath) {
        if (file_exists($resizedPath)) {
            if (unlink($resizedPath)) {
                $deletedFiles[] = $resizedPath;
            } else {
                $failedFiles[] = $resizedPath;
            }
        }
    }

    // データベースから削除
    $stmt = $db->prepare("DELETE FROM posts WHERE id = :id");
    $stmt->execute([':id' => $postId]);

    writeLog("画像削除: Post ID {$postId}", 'INFO');

    if (count($failedFiles) > 0) {
        sendSuccessResponse([
            'message' => '削除が完了しました（一部ファイルの削除に失敗しました）',
            'deleted_files' => count($deletedFiles),
            'failed_files' => count($failedFiles)
        ]);
    } else {
        sendSuccessResponse([
            'message' => '削除が完了しました',
            'deleted_files' => count($deletedFiles)
        ]);
    }

} catch (Exception $e) {
    writeLog("削除エラー: " . $e->getMessage(), 'ERROR');
    sendErrorResponse($e->getMessage(), 500);
}
?>


