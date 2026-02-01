<?php
/**
 * 画像リサイズ機能
 * 各SNSプラットフォームに適切なサイズにリサイズ
 */

require_once __DIR__ . '/../config.php';

// 各プラットフォームの推奨画像サイズ
define('INSTAGRAM_SQUARE_SIZE', 1080); // 正方形
define('INSTAGRAM_STORY_SIZE', 1080); // ストーリー
define('TWITTER_MAX_SIZE', 1200); // 最大幅
define('FACEBOOK_POST_SIZE', 1200); // 投稿画像

class ImageResizer {
    // 画像処理に必要な最小メモリ（バイト）
    const MEMORY_SAFETY_FACTOR = 2.5; // 安全係数

    /**
     * 画像処理に必要なメモリを推定
     */
    private static function estimateMemoryUsage($width, $height, $channels = 4) {
        // 各ピクセルは通常4バイト（RGBA）を使用
        // ソース画像 + ターゲット画像 + オーバーヘッド
        return $width * $height * $channels * self::MEMORY_SAFETY_FACTOR * 2;
    }

    /**
     * 利用可能なメモリを確認
     */
    private static function checkMemoryLimit($requiredBytes) {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return true; // 無制限
        }

        // メモリ制限をバイトに変換
        $unit = strtolower(substr($memoryLimit, -1));
        $bytes = (int)$memoryLimit;

        switch ($unit) {
            case 'g':
                $bytes *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $bytes *= 1024 * 1024;
                break;
            case 'k':
                $bytes *= 1024;
                break;
        }

        $currentUsage = memory_get_usage(true);
        $availableMemory = $bytes - $currentUsage;

        return $availableMemory >= $requiredBytes;
    }

    /**
     * 画像をリサイズ
     */
    public static function resize($sourcePath, $targetPath, $maxWidth, $maxHeight, $quality = 85) {
        if (!file_exists($sourcePath)) {
            throw new Exception("元画像が見つかりません");
        }

        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new Exception("画像ファイルが無効です");
        }

        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        // メモリ使用量のチェック
        $requiredMemory = self::estimateMemoryUsage($sourceWidth, $sourceHeight);
        if (!self::checkMemoryLimit($requiredMemory)) {
            throw new Exception("画像サイズが大きすぎます。より小さな画像をアップロードしてください。");
        }

        // アスペクト比を維持してリサイズ
        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $newWidth = (int)($sourceWidth * $ratio);
        $newHeight = (int)($sourceHeight * $ratio);

        // 画像を読み込み
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                throw new Exception("サポートされていない画像形式です: {$mimeType}");
        }

        if ($sourceImage === false) {
            throw new Exception("画像の読み込みに失敗しました");
        }

        // 新しい画像を作成
        $targetImage = imagecreatetruecolor($newWidth, $newHeight);

        // PNGとGIFの透明度を保持
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefill($targetImage, 0, 0, $transparent);
        }

        // リサイズ
        imagecopyresampled(
            $targetImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $sourceWidth, $sourceHeight
        );

        // 画像を保存
        $result = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $result = imagejpeg($targetImage, $targetPath, $quality);
                break;
            case 'image/png':
                $result = imagepng($targetImage, $targetPath, 9);
                break;
            case 'image/gif':
                $result = imagegif($targetImage, $targetPath);
                break;
        }

        // メモリ解放
        imagedestroy($sourceImage);
        imagedestroy($targetImage);

        if (!$result) {
            throw new Exception("画像の保存に失敗しました");
        }

        return ['width' => $newWidth, 'height' => $newHeight];
    }

    /**
     * Instagram用にリサイズ（正方形）
     */
    public static function resizeForInstagram($sourcePath, $targetPath) {
        return self::resize($sourcePath, $targetPath, INSTAGRAM_SQUARE_SIZE, INSTAGRAM_SQUARE_SIZE);
    }

    /**
     * Twitter用にリサイズ
     */
    public static function resizeForTwitter($sourcePath, $targetPath) {
        return self::resize($sourcePath, $targetPath, TWITTER_MAX_SIZE, TWITTER_MAX_SIZE);
    }

    /**
     * Facebook用にリサイズ
     */
    public static function resizeForFacebook($sourcePath, $targetPath) {
        return self::resize($sourcePath, $targetPath, FACEBOOK_POST_SIZE, FACEBOOK_POST_SIZE);
    }

    /**
     * すべてのプラットフォーム用にリサイズ
     */
    public static function resizeForAllPlatforms($sourcePath, $baseFilename) {
        $results = [];
        $uploadDir = UPLOAD_DIR;

        // Instagram用
        $instagramPath = $uploadDir . 'instagram_' . $baseFilename;
        $results['instagram'] = self::resizeForInstagram($sourcePath, $instagramPath);

        // Twitter用
        $twitterPath = $uploadDir . 'twitter_' . $baseFilename;
        $results['twitter'] = self::resizeForTwitter($sourcePath, $twitterPath);

        // Facebook用
        $facebookPath = $uploadDir . 'facebook_' . $baseFilename;
        $results['facebook'] = self::resizeForFacebook($sourcePath, $facebookPath);

        return $results;
    }
}
?>

