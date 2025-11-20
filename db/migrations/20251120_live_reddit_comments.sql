-- Migration: live_streams, reddit_integrations, threaded comments and moderation flags
-- Run: mysql -u user -p kidsmaster < db/migrations/20251120_live_reddit_comments.sql

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
  INDEX (channel_id),
  FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reddit_integrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  channel_id INT NOT NULL,
  reddit_subreddit VARCHAR(255),
  reddit_thread_url VARCHAR(1024),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (channel_id),
  FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Threaded comments: parent_id NULL for top-level
ALTER TABLE comments
  ADD COLUMN parent_id BIGINT NULL AFTER id,
  ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER created_at,
  ADD INDEX (parent_id),
  ADD INDEX (is_deleted);

-- Add background field to channels and users (for change background)
ALTER TABLE channels
  ADD COLUMN background VARCHAR(255) NULL AFTER banner;

ALTER TABLE users
  ADD COLUMN background VARCHAR(255) NULL AFTER avatar;

-- Simple moderation table for comment reports
CREATE TABLE IF NOT EXISTS comment_reports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  comment_id BIGINT NOT NULL,
  reporter_id INT NULL,
  reason VARCHAR(255),
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (comment_id),
  FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;