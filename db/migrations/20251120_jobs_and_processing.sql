-- db/migrations/20251120_jobs_and_processing.sql
-- Add encoding_jobs table and media columns needed for worker processing

CREATE TABLE IF NOT EXISTS encoding_jobs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  job_type VARCHAR(64) NOT NULL,
  payload JSON NOT NULL,
  status ENUM('queued','processing','done','failed') DEFAULT 'queued',
  attempts INT DEFAULT 0,
  last_error TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (job_type),
  INDEX (status),
  INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- media fields for HLS and duration if not already present
ALTER TABLE media
  ADD COLUMN IF NOT EXISTS hls_url VARCHAR(1024) NULL,
  ADD COLUMN IF NOT EXISTS duration FLOAT NULL,
  ADD COLUMN IF NOT EXISTS processed TINYINT(1) DEFAULT 0;

-- ensure storage_files table exists (used to track uploaded file sizes)
CREATE TABLE IF NOT EXISTS storage_files (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT,
  file_name VARCHAR(255),
  path VARCHAR(1024),
  size BIGINT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;