-- SNS同時投稿システム - データベーススキーマ

CREATE DATABASE IF NOT EXISTS sns_post_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sns_post_db;

-- 投稿履歴テーブル
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_filename VARCHAR(255) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    caption TEXT,
    instagram_status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    instagram_post_id VARCHAR(255) NULL,
    instagram_error TEXT NULL,
    twitter_status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    twitter_post_id VARCHAR(255) NULL,
    twitter_error TEXT NULL,
    facebook_status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    facebook_post_id VARCHAR(255) NULL,
    facebook_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_status (instagram_status, twitter_status, facebook_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 画像リサイズ情報テーブル
CREATE TABLE IF NOT EXISTS image_resizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    platform ENUM('instagram', 'twitter', 'facebook') NOT NULL,
    resized_path VARCHAR(500) NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    INDEX idx_post_id (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

