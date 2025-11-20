-- schema.sql (add / modify fields and tables for broad feature set)
-- Run carefully; this file contains ALTERs that assume previous scaffold exists.

-- channels: enable_chat, enable_live, theme_choice, channel_version, profile_pic, gif_banner, background
ALTER TABLE channels
  ADD COLUMN profile_pic VARCHAR(255) NULL,
  ADD COLUMN gif_banner VARCHAR(255) NULL,
  ADD COLUMN background VARCHAR(255) NULL,
  ADD COLUMN enable_chat TINYINT(1) DEFAULT 1,
  ADD COLUMN enable_live TINYINT(1) DEFAULT 0,
  ADD COLUMN theme_choice VARCHAR(64) DEFAULT 'deluxe',
  ADD COLUMN channel_version VARCHAR(32) DEFAULT '1.5.0';

-- chat_messages: add channel_id, country_code, user_avatar
ALTER TABLE chat_messages
  ADD COLUMN channel_id INT NULL,
  ADD COLUMN country_code VARCHAR(8) NULL,
  ADD COLUMN user_avatar VARCHAR(255) NULL,
  ADD INDEX (channel_id);

-- reddit_integrations table
CREATE TABLE IF NOT EXISTS reddit_integrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  channel_id INT NOT NULL,
  reddit_subreddit VARCHAR(255),
  reddit_thread_url VARCHAR(1024),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (channel_id)
);

-- live_streams table
CREATE TABLE IF NOT EXISTS live_streams (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  channel_id INT NOT NULL,
  title VARCHAR(255),
  description TEXT,
  rtmp_key VARCHAR(255),
  hls_url VARCHAR(1024),
  status ENUM('created','live','ended') DEFAULT 'created',
  started_at DATETIME NULL,
  ended_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (channel_id)
);

-- analytics events
CREATE TABLE IF NOT EXISTS analytics_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(100),
  meta JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Ensure subscriptions table exists
CREATE TABLE IF NOT EXISTS subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  channel_id INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (user_id, channel_id)
);

-- comments: parent_id and is_deleted columns (threaded comments)
ALTER TABLE comments
  ADD COLUMN parent_id BIGINT NULL,
  ADD COLUMN is_deleted TINYINT(1) DEFAULT 0,
  ADD INDEX (parent_id);
