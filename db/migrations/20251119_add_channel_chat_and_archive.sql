-- Add channel_id, country_code and user_avatar to chat_messages
-- Add archived flag to channels
ALTER TABLE chat_messages
  ADD COLUMN channel_id INT NULL AFTER media_id,
  ADD COLUMN country_code VARCHAR(8) NULL AFTER user_name,
  ADD COLUMN user_avatar VARCHAR(255) NULL AFTER user_name,
  ADD INDEX (channel_id),
  ADD INDEX (created_at);

ALTER TABLE channels
  ADD COLUMN archived TINYINT(1) DEFAULT 0 AFTER created_at,
  ADD INDEX (archived);

-- Optional: ensure channel_id constraint (if you want FK)
-- ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE SET NULL;