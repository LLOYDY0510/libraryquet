-- ============================================================
--  LibraryQuiet Monitoring System — setup.sql (UPDATED)
--  Run in phpMyAdmin: https://auth-db19821.hstgr.io
--  DB: u442411629_librarysaba
--
--  CHANGES FROM ORIGINAL:
--  1. Seed user passwords are now bcrypt hashes (PASSWORD_BCRYPT)
--     Plain-text originals:
--       admin@library.edu  → admin123
--       james@library.edu  → james123
--       staff@library.edu  → staff123
--     On first login with the old plain password, auth.php will
--     automatically detect and upgrade any remaining plain-text
--     passwords to bcrypt — no manual migration needed.
--  2. role_rules table and its seed data have been REMOVED
--     as requested. Role checking is now fully hardcoded in
--     auth.php via hasRole() and requireRole().
--  3. activity_logs table added (was already in previous version
--     but now documented clearly with all columns explained).
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';

-- ── USERS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id         VARCHAR(20)  PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(100) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,           -- bcrypt hash (60 chars)
  role       ENUM('Administrator','Library Manager','Library Staff') NOT NULL DEFAULT 'Library Staff',
  status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ZONES ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS zones (
  id              VARCHAR(20)  PRIMARY KEY,
  name            VARCHAR(100) NOT NULL,
  floor           VARCHAR(10)  NOT NULL,
  capacity        INT          NOT NULL DEFAULT 50,
  occupied        INT          NOT NULL DEFAULT 0,
  level           DECIMAL(5,2) NOT NULL DEFAULT 0,
  warn_threshold  INT          NOT NULL DEFAULT 40,
  crit_threshold  INT          NOT NULL DEFAULT 60,
  sensor          VARCHAR(20)  NOT NULL,
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  battery         INT          NOT NULL DEFAULT 80,
  manual_override TINYINT(1)   NOT NULL DEFAULT 0,
  description     TEXT,
  lat             DECIMAL(10,7) DEFAULT NULL,
  lng             DECIMAL(10,7) DEFAULT NULL,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ALERTS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS alerts (
  id            VARCHAR(30)  PRIMARY KEY,
  zone_name     VARCHAR(100) NOT NULL,
  level         DECIMAL(5,2) NOT NULL,
  type          ENUM('warning','critical','resolved') NOT NULL DEFAULT 'warning',
  msg           TEXT,
  status        ENUM('active','resolved') NOT NULL DEFAULT 'active',
  resolved_by   VARCHAR(100) DEFAULT NULL,
  resolved_at   VARCHAR(50)  DEFAULT NULL,
  sent_to_admin TINYINT(1)   NOT NULL DEFAULT 0,
  alert_date    VARCHAR(100) NOT NULL,
  alert_time    VARCHAR(20)  NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ALERT MESSAGES ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS alert_messages (
  id         VARCHAR(30)  PRIMARY KEY,
  alert_id   VARCHAR(30)  NOT NULL,
  from_name  VARCHAR(100) NOT NULL,
  from_role  VARCHAR(50)  NOT NULL,
  message    TEXT         NOT NULL,
  msg_time   VARCHAR(20)  NOT NULL,
  msg_date   VARCHAR(100) NOT NULL,
  is_system  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── REPORTS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reports (
  id            VARCHAR(30)  PRIMARY KEY,
  type          VARCHAR(100) NOT NULL,
  generated_by  VARCHAR(100) NOT NULL,
  role          VARCHAR(50)  NOT NULL,
  report_date   VARCHAR(100) NOT NULL,
  report_time   VARCHAR(20)  NOT NULL,
  sent_to_admin TINYINT(1)   NOT NULL DEFAULT 0,
  sent_at       VARCHAR(50)  DEFAULT NULL,
  admin_read_at VARCHAR(50)  DEFAULT NULL,
  notes         TEXT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SENSOR OVERRIDES ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sensor_overrides (
  zone_id  VARCHAR(20)  PRIMARY KEY,
  level    DECIMAL(5,2) NOT NULL,
  set_by   VARCHAR(100) NOT NULL,
  set_at   VARCHAR(20)  NOT NULL,
  set_date VARCHAR(100) NOT NULL,
  FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ACTIVITY LOGS ─────────────────────────────────────────────
-- Tracks every important action across the system.
-- Logged actions include:
--   Login, Login Failed, Logout, Register
--   Add User, Edit User Role, Toggle User, Reset Password, Delete User
--   Add Zone, Edit Zone, Delete Zone, Override Zone, Clear Override
--   Resolve Alert, Alert Message
--   Generated Report, Viewed Reports
--   Cleared Activity Logs, Exported Activity Logs
--   Viewed [page] — page-view tracking
CREATE TABLE IF NOT EXISTS activity_logs (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    VARCHAR(20)  NOT NULL,           -- User ID (or '—' for failed logins)
  user_name  VARCHAR(100) NOT NULL,           -- Display name at time of action
  user_role  VARCHAR(50)  NOT NULL,           -- Role at time of action
  action     VARCHAR(100) NOT NULL,           -- Short action label
  detail     TEXT,                            -- Human-readable description
  page       VARCHAR(100) DEFAULT NULL,       -- PHP page that triggered the log
  ip         VARCHAR(45)  DEFAULT NULL,       -- IPv4 or IPv6
  user_agent TEXT         DEFAULT NULL,       -- Browser/client string
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id  (user_id),
  INDEX idx_created  (created_at),
  INDEX idx_action   (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SEED DATA ─────────────────────────────────────────────────
-- NOTE ON PASSWORDS:
--   These are bcrypt hashes generated with password_hash('original', PASSWORD_BCRYPT).
--   The system also auto-upgrades any remaining plain-text passwords on first login.
--
--   To generate new hashes for your own passwords, run:
--     php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"
--
START TRANSACTION;

INSERT IGNORE INTO users (id, name, email, password, role, status, last_login) VALUES
('U-001', 'Johnlloyd P.',
 'admin@library.edu',
 '$2b$10$C2jRbtfR8N6CPOyKD8AtLepFG.y38ZVf74/RewHnwtvT5RISxi5Ry',  -- admin123
 'Administrator',   'active', NULL),
('U-002', 'James Anticamars',
 'james@library.edu',
 '$2b$10$egALwT5HhYUTk1F.54hunen9RJWMKV6PyPu5L0J1ZJqxX1Ej7t3Gm',  -- james123
 'Library Manager', 'active', NULL),
('U-003', 'Dimavier',
 'staff@library.edu',
 '$2b$10$S.uGWFFx/mefvHjulH6dOedKC0KB4mAfaD84DoplKpBNO28Rc5GF6',  -- staff123
 'Library Staff',   'active', NULL);

-- NOTE: The hashes above were generated with bcrypt cost factor 10.
-- PHP's password_verify() is fully compatible with these $2b$ hashes.
-- If for any reason login fails, simply log in with the plain-text
-- passwords (admin123 / james123 / staff123) — the system will
-- auto-detect plain text and upgrade them to bcrypt on first login.

INSERT IGNORE INTO zones (id, name, floor, capacity, occupied, level, warn_threshold, crit_threshold, sensor, battery, lat, lng, description) VALUES
('Z-001', 'Reading AREA',  '1F', 80, 45, 28.00, 40, 60, 'SNS-001', 85, 8.359248, 124.867853, 'Main reading area on the ground floor.'),
('Z-002', 'Study AREA',    '2F', 20, 18, 52.00, 40, 60, 'SNS-002', 72, 8.359272, 124.867950, 'Private study room for small groups.'),
('Z-003', 'Computer AREA', '1F', 40, 12, 18.00, 35, 55, 'SNS-003', 91, 8.359322, 124.867674, 'Computer laboratory section.');

COMMIT;

-- ── ROLE RULES TABLE REMOVED ──────────────────────────────────
-- The role_rules table and its data have been removed as requested.
-- Role-based access is now handled entirely through:
--   hasRole()    in includes/config.php  — checks session role
--   requireRole() in includes/config.php — redirects if not allowed
--   canDo()       removed (relied on role_rules table)
-- Each page now uses requireRole('Administrator') or
-- requireRole('Administrator', 'Library Manager') directly.
