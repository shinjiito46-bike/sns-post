<?php
/**
 * SNS API連携
 * Instagram、X (Twitter)、Facebookへの投稿処理
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

class SNSAPI {
    /**
     * Instagram Graph APIへの投稿
     */
    public static function postToInstagram($imagePath, $caption = '') {
        try {
            // Instagram Graph APIでは、まず画像をアップロードしてコンテナIDを取得
            // その後、コンテナIDを使って投稿を作成する必要があります

            $uploadUrl = "https://graph.instagram.com/" . INSTAGRAM_USER_ID . "/media";
            $params = [
                'image_url' => BASE_URL . '/uploads/' . basename($imagePath),
                'caption' => $caption,
                'access_token' => INSTAGRAM_ACCESS_TOKEN
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $uploadUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception("cURL error: {$error}");
            }

            $result = json_decode($response, true);

            if ($httpCode !== 200 || isset($result['error'])) {
                $errorMsg = $result['error']['message'] ?? 'Unknown error';
                throw new Exception("Instagram API error: {$errorMsg}");
            }

            $containerId = $result['id'] ?? null;
            if (!$containerId) {
                throw new Exception("コンテナIDの取得に失敗しました");
            }

            // コンテナのステータスをポーリング（最大30秒）
            $maxAttempts = 10;
            $attempt = 0;
            $isReady = false;

            while ($attempt < $maxAttempts && !$isReady) {
                sleep(3); // 3秒待機
                $attempt++;

                $statusUrl = "https://graph.instagram.com/{$containerId}?fields=status_code&access_token=" . INSTAGRAM_ACCESS_TOKEN;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $statusUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

                $statusResponse = curl_exec($ch);
                curl_close($ch);

                $statusResult = json_decode($statusResponse, true);
                $statusCode = $statusResult['status_code'] ?? '';

                if ($statusCode === 'FINISHED') {
                    $isReady = true;
                } elseif ($statusCode === 'ERROR') {
                    throw new Exception("Instagramメディア処理エラー");
                }
                // IN_PROGRESS の場合は継続してポーリング
            }

            if (!$isReady) {
                throw new Exception("Instagramメディア処理がタイムアウトしました");
            }

            // 投稿を公開
            $publishUrl = "https://graph.instagram.com/" . INSTAGRAM_USER_ID . "/media_publish";
            $publishParams = [
                'creation_id' => $containerId,
                'access_token' => INSTAGRAM_ACCESS_TOKEN
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $publishUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($publishParams));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $publishResponse = curl_exec($ch);
            $publishHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $publishResult = json_decode($publishResponse, true);

            if ($publishHttpCode !== 200 || isset($publishResult['error'])) {
                $errorMsg = $publishResult['error']['message'] ?? 'Unknown error';
                throw new Exception("Instagram投稿エラー: {$errorMsg}");
            }

            return [
                'success' => true,
                'post_id' => $publishResult['id'] ?? null
            ];

        } catch (Exception $e) {
            writeLog("Instagram投稿エラー: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * X (Twitter) API v2への投稿
     */
    public static function postToTwitter($imagePath, $caption = '') {
        try {
            // Twitter API v2では、まず画像をアップロードしてメディアIDを取得
            // その後、メディアIDを含めてツイートを作成

            // 画像アップロード（v1.1のメディアアップロードエンドポイントを使用）
            $mediaUrl = 'https://upload.twitter.com/1.1/media/upload.json';
            $imageData = file_get_contents($imagePath);
            if ($imageData === false) {
                throw new Exception("画像ファイルの読み込みに失敗しました");
            }
            $imageBase64 = base64_encode($imageData);

            // OAuth 1.0a認証（暗号学的に安全なnonce生成）
            $oauth = [
                'oauth_consumer_key' => TWITTER_API_KEY,
                'oauth_nonce' => bin2hex(random_bytes(16)),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => (string)time(),
                'oauth_token' => TWITTER_ACCESS_TOKEN,
                'oauth_version' => '1.0'
            ];

            $baseString = self::buildBaseString($mediaUrl, 'POST', $oauth);
            $signingKey = rawurlencode(TWITTER_API_SECRET) . '&' . rawurlencode(TWITTER_ACCESS_TOKEN_SECRET);
            $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

            $authHeader = 'Authorization: OAuth ' . self::buildAuthorizationHeader($oauth);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $mediaUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['media_data' => $imageBase64]);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [$authHeader]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new Exception("cURL error: {$curlError}");
            }

            if ($httpCode !== 200) {
                $errorResult = json_decode($response, true);
                $errorMsg = $errorResult['errors'][0]['message'] ?? "HTTP {$httpCode}";
                throw new Exception("Twitter画像アップロードエラー: {$errorMsg}");
            }

            $mediaResult = json_decode($response, true);
            $mediaId = $mediaResult['media_id_string'] ?? null;

            if (!$mediaId) {
                throw new Exception("メディアIDの取得に失敗しました");
            }

            // ツイート作成（v2 API with OAuth 1.0a）
            // Bearer Tokenではメディア付きツイートは作成できないため、OAuth 1.0aを使用
            $tweetUrl = 'https://api.twitter.com/2/tweets';
            $tweetData = json_encode([
                'text' => $caption,
                'media' => ['media_ids' => [$mediaId]]
            ]);

            // OAuth 1.0a認証をツイート用に再生成
            $oauthTweet = [
                'oauth_consumer_key' => TWITTER_API_KEY,
                'oauth_nonce' => bin2hex(random_bytes(16)),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => (string)time(),
                'oauth_token' => TWITTER_ACCESS_TOKEN,
                'oauth_version' => '1.0'
            ];

            $baseStringTweet = self::buildBaseString($tweetUrl, 'POST', $oauthTweet);
            $oauthTweet['oauth_signature'] = base64_encode(hash_hmac('sha1', $baseStringTweet, $signingKey, true));

            $authHeaderTweet = 'Authorization: OAuth ' . self::buildAuthorizationHeader($oauthTweet);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tweetUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $tweetData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                $authHeaderTweet,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $tweetResponse = curl_exec($ch);
            $tweetHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new Exception("cURL error: {$curlError}");
            }

            $tweetResult = json_decode($tweetResponse, true);

            if ($tweetHttpCode !== 201 || isset($tweetResult['errors'])) {
                $errorMsg = $tweetResult['errors'][0]['message'] ?? $tweetResult['detail'] ?? 'Unknown error';
                throw new Exception("Twitter投稿エラー: {$errorMsg}");
            }

            return [
                'success' => true,
                'post_id' => $tweetResult['data']['id'] ?? null
            ];

        } catch (Exception $e) {
            writeLog("Twitter投稿エラー: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Facebook Graph APIへの投稿
     */
    public static function postToFacebook($imagePath, $caption = '') {
        try {
            // Facebook Graph APIでは、画像を直接アップロードして投稿
            $url = "https://graph.facebook.com/v18.0/" . FACEBOOK_PAGE_ID . "/photos";

            $imageData = new CURLFile($imagePath);
            $params = [
                'message' => $caption,
                'source' => $imageData,
                'access_token' => FACEBOOK_ACCESS_TOKEN
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception("cURL error: {$error}");
            }

            $result = json_decode($response, true);

            if ($httpCode !== 200 || isset($result['error'])) {
                $errorMsg = $result['error']['message'] ?? 'Unknown error';
                throw new Exception("Facebook API error: {$errorMsg}");
            }

            return [
                'success' => true,
                'post_id' => $result['id'] ?? null
            ];

        } catch (Exception $e) {
            writeLog("Facebook投稿エラー: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * OAuth署名用のベース文字列構築
     */
    private static function buildBaseString($url, $method, $params) {
        ksort($params);
        $query = http_build_query($params);
        return strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($query);
    }

    /**
     * OAuth認証ヘッダー構築
     */
    private static function buildAuthorizationHeader($oauth) {
        $values = [];
        foreach ($oauth as $key => $value) {
            $values[] = $key . '="' . rawurlencode($value) . '"';
        }
        return implode(', ', $values);
    }
}
?>

