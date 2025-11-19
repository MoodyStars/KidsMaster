-- DB additions: subscriptions, playlists, playlist_items, reports, stats_views
ALTER TABLE users ADD COLUMN is_moderator TINYINT(1) DEFAULT 0;

CREATE TABLE IF NOT EXISTS subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  channel_id INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (user_id, channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS playlists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT,
  name VARCHAR(255),
  is_public TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS playlist_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  playlist_id INT,
  media_id BIGINT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (playlist_id, media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  reporter_id INT,
  media_id BIGINT,
  reason VARCHAR(255),
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stats_views (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  media_id BIGINT,
  user_id INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- storage_files table already exists in scaffold; ensure index
ALTER TABLE storage_files ADD INDEX (owner_id);