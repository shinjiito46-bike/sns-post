<?php
/**
 * 投稿履歴取得API
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 管理画面からのみアクセス可能（session_start()はconfig.phpで実行済み）
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sendErrorResponse('認証が必要です', 401);
}

try {
    $db = getDB();

    // ページネーション（入力値の検証）
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

    // 範囲チェック
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage)); // 1〜100の範囲に制限

    $offset = ($page - 1) * $perPage;

    // 総件数を取得
    $countStmt = $db->query("SELECT COUNT(*) FROM posts");
    $totalCount = $countStmt->fetchColumn();

    // 投稿一覧を取得
    $stmt = $db->prepare("
        SELECT 
            p.*,
            GROUP_CONCAT(
                CONCAT(ir.platform, ':', ir.resized_path) 
                SEPARATOR '|'
            ) as resized_images
        FROM posts p
        LEFT JOIN image_resizes ir ON p.id = ir.post_id
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll();

    // 画像URLを追加
    foreach ($posts as &$post) {
        $post['image_url'] = BASE_URL . '/uploads/' . $post['image_filename'];
        $post['resized_images'] = $post['resized_images'] ? explode('|', $post['resized_images']) : [];
    }

    sendSuccessResponse([
        'posts' => $posts,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $perPage)
        ]
    ]);

} catch (Exception $e) {
    writeLog("投稿履歴取得エラー: " . $e->getMessage(), 'ERROR');
    sendErrorResponse($e->getMessage(), 500);
}
?>


