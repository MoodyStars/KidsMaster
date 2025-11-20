-- Migration: Add channel metadata to chat_messages, archived flag to channels, and supporting indexes
ALTER TABLE chat_messages
  ADD COLUMN channel_id INT NULL AFTER media_id,
  ADD COLUMN country_code VARCHAR(8) NULL AFTER user_name,
  ADD COLUMN user_avatar VARCHAR(255) NULL AFTER user_name,
  ADD INDEX idx_chat_channel (channel_id),
  ADD INDEX idx_chat_created_at (created_at);

ALTER TABLE channels
  ADD COLUMN archived TINYINT(1) DEFAULT 0 AFTER created_at,
  ADD INDEX idx_channels_archived (archived);

-- Optional: add FK if you want strong referential integrity
-- ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE SET NULL;