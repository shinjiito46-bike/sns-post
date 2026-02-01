<?php
/**
 * メイン投稿フォーム（スマホ対応）
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SNS同時投稿</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>SNS同時投稿</h1>
            <p class="subtitle">Instagram、X、Facebookへ同時に投稿できます</p>
        </header>

        <main>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                
                <div class="form-group">
                    <label for="image" class="file-label">
                        <span class="file-label-text">画像を選択</span>
                        <input type="file" id="image" name="image" accept="image/*" required>
                        <span class="file-name" id="fileName"></span>
                    </label>
                    <div class="image-preview" id="imagePreview"></div>
                </div>

                <div class="form-group">
                    <label for="caption">キャプション（任意）</label>
                    <textarea id="caption" name="caption" rows="4" placeholder="投稿に添えるメッセージを入力してください"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <span class="btn-text">投稿する</span>
                    <span class="btn-loading" style="display: none;">投稿中...</span>
                </button>
            </form>

            <div id="resultMessage" class="result-message" style="display: none;"></div>
        </main>

        <footer>
            <a href="admin.php" class="admin-link">管理画面へ</a>
        </footer>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>

