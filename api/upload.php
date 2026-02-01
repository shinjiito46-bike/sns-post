<?php
/**
 * 画像アップロードとSNS投稿処理
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/image_resize.php';
require_once __DIR__ . '/../includes/sns_api.php';

header('Content-Type: application/json; charset=utf-8');

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('POSTリクエストのみ許可されています', 405);
}

// CSRFトークン検証
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    sendErrorResponse('CSRFトークンが無効です', 403);
}

// ファイルアップロード確認
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = '画像のアップロードに失敗しました';
    if (isset($_FILES['image']['error'])) {
        switch ($_FILES['image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = 'ファイルサイズが大きすぎます';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg = 'ファイルのアップロードが完了していません';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = 'ファイルが選択されていません';
                break;
        }
    }
    sendErrorResponse($errorMsg);
}

$file = $_FILES['image'];
$caption = $_POST['caption'] ?? '';

// ファイルサイズチェック
if ($file['size'] > MAX_FILE_SIZE) {
    sendErrorResponse('ファイルサイズが大きすぎます（最大' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB）');
}

// ファイル拡張子チェック
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ALLOWED_EXTENSIONS)) {
    sendErrorResponse('許可されていないファイル形式です（' . implode(', ', ALLOWED_EXTENSIONS) . 'のみ）');
}

// MIMEタイプ検証（実際のファイル内容を確認）
$imageInfo = getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    sendErrorResponse('有効な画像ファイルではありません');
}

$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($imageInfo['mime'], $allowedMimeTypes)) {
    sendErrorResponse('許可されていない画像形式です');
}

try {
    $db = getDB();

    // 安全なファイル名を生成
    $safeFilename = generateSafeFilename($file['name']);
    $uploadPath = UPLOAD_DIR . $safeFilename;

    // ファイルをアップロード
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('ファイルの保存に失敗しました');
    }

    // 画像を各プラットフォーム用にリサイズ
    $resizeResults = ImageResizer::resizeForAllPlatforms($uploadPath, $safeFilename);

    // トランザクション開始
    $db->beginTransaction();

    try {
        // データベースに投稿情報を保存
        $stmt = $db->prepare("
            INSERT INTO posts (image_filename, image_path, caption, instagram_status, twitter_status, facebook_status)
            VALUES (:filename, :path, :caption, 'pending', 'pending', 'pending')
        ");
        $stmt->execute([
            ':filename' => $safeFilename,
            ':path' => $uploadPath,
            ':caption' => $caption
        ]);
        $postId = $db->lastInsertId();

        // リサイズ情報をデータベースに保存（resizeResultsから正確なパスを取得）
        $platformPaths = [
            'instagram' => UPLOAD_DIR . 'instagram_' . $safeFilename,
            'twitter' => UPLOAD_DIR . 'twitter_' . $safeFilename,
            'facebook' => UPLOAD_DIR . 'facebook_' . $safeFilename
        ];

        foreach ($resizeResults as $platform => $info) {
            $stmt = $db->prepare("
                INSERT INTO image_resizes (post_id, platform, resized_path, width, height)
                VALUES (:post_id, :platform, :path, :width, :height)
            ");
            $stmt->execute([
                ':post_id' => $postId,
                ':platform' => $platform,
                ':path' => $platformPaths[$platform],
                ':width' => $info['width'],
                ':height' => $info['height']
            ]);
        }

        // トランザクションコミット
        $db->commit();

    } catch (Exception $e) {
        // トランザクションロールバック
        $db->rollBack();

        // アップロードしたファイルを削除
        if (file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        foreach ($platformPaths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        throw $e;
    }

    // 各SNSに投稿（非同期処理のため、バックグラウンドで実行）
    // ここでは同期的に実行しますが、実際の運用ではキューベースの処理を推奨
    $instagramResult = SNSAPI::postToInstagram(
        $platformPaths['instagram'],
        $caption
    );
    $twitterResult = SNSAPI::postToTwitter(
        $platformPaths['twitter'],
        $caption
    );
    $facebookResult = SNSAPI::postToFacebook(
        $platformPaths['facebook'],
        $caption
    );

    // 投稿結果をデータベースに更新
    $stmt = $db->prepare("
        UPDATE posts SET
            instagram_status = :ig_status,
            instagram_post_id = :ig_post_id,
            instagram_error = :ig_error,
            twitter_status = :tw_status,
            twitter_post_id = :tw_post_id,
            twitter_error = :tw_error,
            facebook_status = :fb_status,
            facebook_post_id = :fb_post_id,
            facebook_error = :fb_error
        WHERE id = :id
    ");
    $stmt->execute([
        ':id' => $postId,
        ':ig_status' => $instagramResult['success'] ? 'success' : 'failed',
        ':ig_post_id' => $instagramResult['post_id'] ?? null,
        ':ig_error' => $instagramResult['error'] ?? null,
        ':tw_status' => $twitterResult['success'] ? 'success' : 'failed',
        ':tw_post_id' => $twitterResult['post_id'] ?? null,
        ':tw_error' => $twitterResult['error'] ?? null,
        ':fb_status' => $facebookResult['success'] ? 'success' : 'failed',
        ':fb_post_id' => $facebookResult['post_id'] ?? null,
        ':fb_error' => $facebookResult['error'] ?? null
    ]);

    writeLog("投稿完了: Post ID {$postId}", 'INFO');

    // クライアントに返すエラー情報は簡略化（詳細はログに記録済み）
    $clientResults = [
        'instagram' => [
            'success' => $instagramResult['success'],
            'post_id' => $instagramResult['post_id'] ?? null
        ],
        'twitter' => [
            'success' => $twitterResult['success'],
            'post_id' => $twitterResult['post_id'] ?? null
        ],
        'facebook' => [
            'success' => $facebookResult['success'],
            'post_id' => $facebookResult['post_id'] ?? null
        ]
    ];

    sendSuccessResponse([
        'post_id' => $postId,
        'results' => $clientResults
    ]);

} catch (Exception $e) {
    writeLog("アップロードエラー: " . $e->getMessage(), 'ERROR');
    // クライアントには一般的なエラーメッセージを返す（詳細はログに記録済み）
    sendErrorResponse('投稿処理中にエラーが発生しました。しばらく経ってから再度お試しください。', 500);
}
?>


