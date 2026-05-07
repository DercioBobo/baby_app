-- bebélog database schema
-- Run this in phpMyAdmin on InfinityFree before deploying

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  phone         VARCHAR(20) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  settings      TEXT DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- If upgrading an existing install, run this once:
-- ALTER TABLE users ADD COLUMN settings TEXT DEFAULT NULL;

CREATE TABLE IF NOT EXISTS tokens (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  token      VARCHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS babies (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL UNIQUE,
  name        VARCHAR(100) NOT NULL,
  birth_date  DATE NOT NULL,
  mom_name    VARCHAR(100),
  photo       MEDIUMTEXT,
  theme_color VARCHAR(20),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- All tracking entries (sleep, feed, diaper, growth) in one table.
-- Type-specific fields stored as JSON in `data`.
CREATE TABLE IF NOT EXISTS logs (
  id       VARCHAR(36) PRIMARY KEY,
  user_id  INT NOT NULL,
  type     VARCHAR(20) NOT NULL,
  time_ts  BIGINT NOT NULL,
  date_str VARCHAR(50) NOT NULL,
  data     TEXT NOT NULL DEFAULT '{}',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_logs_user_date (user_id, date_str)
);

CREATE TABLE IF NOT EXISTS meds (
  id         VARCHAR(36) PRIMARY KEY,
  user_id    INT NOT NULL,
  name       VARCHAR(100) NOT NULL,
  dose       VARCHAR(100),
  interval_h FLOAT NOT NULL DEFAULT 8,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS med_logs (
  id       VARCHAR(36) PRIMARY KEY,
  user_id  INT NOT NULL,
  med_id   VARCHAR(36) NOT NULL,
  med_name VARCHAR(100),
  dose     VARCHAR(100),
  time_ts  BIGINT NOT NULL,
  date_str VARCHAR(50) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_medlogs_user_date (user_id, date_str)
);

CREATE TABLE IF NOT EXISTS milestones (
  id       VARCHAR(36) PRIMARY KEY,
  user_id  INT NOT NULL,
  label    VARCHAR(255),
  emoji    VARCHAR(10),
  photo    MEDIUMTEXT,
  saved_at BIGINT,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Key-value store for admin app settings (WA config, templates, etc.)
CREATE TABLE IF NOT EXISTS app_settings (
  key_name   VARCHAR(100) PRIMARY KEY,
  value      TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
