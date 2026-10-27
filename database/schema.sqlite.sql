-- Isolated tests only; deployment uses MySQL/MariaDB.
CREATE TABLE IF NOT EXISTS users (
 id VARCHAR(64) PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 initials VARCHAR(8) NOT NULL,
 color CHAR(7) NOT NULL,
 role VARCHAR(16) NOT NULL DEFAULT 'member',
 CONSTRAINT valid_role CHECK (role IN ('member','admin'))
);
CREATE TABLE IF NOT EXISTS threads (
 id VARCHAR(64) PRIMARY KEY,
 user_id VARCHAR(64) NOT NULL,
 category VARCHAR(50) NOT NULL,
 title VARCHAR(150) NOT NULL,
 body TEXT NOT NULL,
 upload_dir VARCHAR(160) NOT NULL UNIQUE,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS replies (
 id VARCHAR(64) PRIMARY KEY,
 thread_id VARCHAR(64) NOT NULL,
 user_id VARCHAR(64) NOT NULL,
 body TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 FOREIGN KEY(thread_id) REFERENCES threads(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS attachments (
 id VARCHAR(64) PRIMARY KEY,
 thread_id VARCHAR(64) NOT NULL,
 user_id VARCHAR(64) NOT NULL,
 original_name VARCHAR(200) NOT NULL,
 mime_type VARCHAR(50) NOT NULL,
 size_bytes INTEGER NOT NULL,
 relative_path VARCHAR(255) NOT NULL UNIQUE,
 created_at DATETIME NOT NULL,
 FOREIGN KEY(thread_id) REFERENCES threads(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS bookmarks (
 user_id VARCHAR(64) NOT NULL,
 thread_id VARCHAR(64) NOT NULL,
 PRIMARY KEY(user_id,thread_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(thread_id) REFERENCES threads(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS moderation_logs (
 id VARCHAR(64) PRIMARY KEY,
 actor_id VARCHAR(64) NOT NULL,
 actor_name VARCHAR(100) NOT NULL,
 action VARCHAR(50) NOT NULL,
 target_type VARCHAR(20) NOT NULL,
 target_id VARCHAR(64) NOT NULL,
 thread_id VARCHAR(64) NOT NULL,
 target_owner_id VARCHAR(64) NOT NULL,
 title_snapshot VARCHAR(500) NOT NULL,
 reason VARCHAR(500) NOT NULL,
 created_at DATETIME NOT NULL
);
CREATE TABLE IF NOT EXISTS file_cleanup (
 id VARCHAR(64) PRIMARY KEY,
 relative_path VARCHAR(255) NOT NULL,
 kind VARCHAR(10) NOT NULL,
 created_at DATETIME NOT NULL
);
CREATE TABLE IF NOT EXISTS write_limits (
 user_id VARCHAR(64) PRIMARY KEY,
 window_started INTEGER NOT NULL,
 request_count INTEGER NOT NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
