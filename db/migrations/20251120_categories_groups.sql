-- DB migration: categories, category_media, groups, group_members, contact_messages, members index
-- Run: mysql -u user -p kidsmaster < db/migrations/20251120_categories_groups.sql

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(150) UNIQUE,
  title VARCHAR(150),
  description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS category_media (
  category_id INT NOT NULL,
  media_id BIGINT NOT NULL,
  PRIMARY KEY (category_id, media_id),
  INDEX (media_id),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `groups` (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  description TEXT,
  is_public TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (owner_id),
  UNIQUE KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS group_members (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  group_id BIGINT NOT NULL,
  user_id INT NOT NULL,
  role ENUM('member','moderator','owner') DEFAULT 'member',
  joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (group_id, user_id),
  INDEX (user_id),
  FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  name VARCHAR(255),
  email VARCHAR(255),
  subject VARCHAR(255),
  body TEXT,
  handled TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  INDEX (handled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional helper: pre-populate categories using the long list of categories the user provided
INSERT IGNORE INTO categories (slug, title, description) VALUES
('business','Business','Business related videos and media'),
('cars-and-vehicles','Cars and Vehicles','Cars, vehicles and transport'),
('cartoon','Cartoon','Animation and cartoons'),
('comedy','Comedy','Funny and comedic content'),
('event-and-party','Event and Party','Events & party videos'),
('family','Family','Family oriented content'),
('fashion-and-lifestyle','Fashion and Lifestyle','Fashion, lifestyle and beauty'),
('funny','Funny','Short funny clips and memes'),
('games','Games','Gaming content'),
('howto-and-diy','Howto and DIY','How-to and DIY tutorials'),
('miscellaneous','Miscellaneous','Miscellaneous content'),
('music','Music','Music videos and audio'),
('news-and-politics','News and Politics','News and politics content'),
('people-and-blog','People and Blog','Vlogs and personal blogs'),
('pets-and-animals','Pets and Animals','Animals and pets'),
('science-and-technology','Science and Technology','Science & tech'),
('potty-porn','Potty Porn','NSFW — requires moderation gating'),
('archive-2000s','2000s Nostalgic','2000s nostalgic content and TV'),
('klasky-csupo','Klasky Csupo','Klasky Csupo mixes and related content'),
('mixes','Mixes','Video mixes and mashups'),
('logos','Logos','Logos and idents'),
('nintendo','Nintendo','Nintendo commercials & games'),
('commercials','Commercials','TV & commercial spots'),
('songs','Songs','Songs and recordings'),
('record-sport','Record Sport','Sports and records'),
('classic-video','Classic video','Classic footage'),
('arab-content','Arab content','Arab-language content'),
('kids-songs','Kids songs','Songs for children'),
('ytp','YTP','YouTube Poops and remixes'),
('animate','Animate','Animated shorts'),
('2000s-street','2000s Street','2000s street and culture'),
('travel-and-holiday','Travel and Holiday','Travel videos and holiday clips'),
('webcam','Webcam','Webcam feeds and recordings')
ON DUPLICATE KEY UPDATE title = VALUES(title);