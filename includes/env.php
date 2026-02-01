<?php
/**
 * .envファイル読み込み機能
 * ロリポップでも動作するシンプルな実装
 */

class EnvLoader {
    private static $loaded = false;
    private static $env = [];

    /**
     * .envファイルを読み込む
     */
    public static function load($envFile = null) {
        if (self::$loaded) {
            return;
        }

        if ($envFile === null) {
            $envFile = __DIR__ . '/../.env';
        }

        if (!file_exists($envFile)) {
            // .envファイルが存在しない場合は警告を出すが、処理は続行
            error_log("Warning: .env file not found at {$envFile}");
            self::$loaded = true;
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // コメント行をスキップ
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // KEY=VALUE形式をパース
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // クォートを削除
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }

                // 環境変数として設定（既に存在する場合は上書きしない）
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                    putenv("{$key}={$value}");
                }
                
                self::$env[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * 環境変数を取得
     */
    public static function get($key, $default = null) {
        self::load();
        
        // 優先順位: $_ENV > getenv() > デフォルト値
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        return $default;
    }

    /**
     * すべての環境変数を取得
     */
    public static function all() {
        self::load();
        return self::$env;
    }
}

/**
 * ヘルパー関数: 環境変数を取得
 */
function env($key, $default = null) {
    return EnvLoader::get($key, $default);
}
?>

