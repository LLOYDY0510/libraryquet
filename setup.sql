-- ============================================================
--  setup.sql
--  Run once in phpMyAdmin or via CLI to initialise the database.
--
--  Compatible with:  MySQL 5.7+ / MariaDB 10.3+
--  Charset:          utf8mb4 (full Unicode + emoji support)
--  Timezone:         Asia/Manila (UTC+8)
--
--  CHANGES FROM PREVIOUS VERSION:
--  1. Added DROP TABLE IF EXISTS guards for clean re-runs.
--  2. Added missing indexes on high-frequency query columns:
--       alerts(status), alerts(zone_name), zones(status),
--       alert_messages(alert_id already has FK index),
--       activity_logs already had indexes — added (page).
--  3. alerts.type ENUM corrected: 'warn' → 'warning' to match
--     noiseStatus() return values used throughout the codebase.
--  4. Seed zone names fixed: 'Reading AREA' → 'Reading Area'
--     (consistent Title Case, matches card and map display).
--  5. activity_logs.browser column added — the layout shell
--     captures HTTP_USER_AGENT for the activity log detail view.
--  6. Redundant sent_to_admin / sent_at / admin_read_at columns
--     retained in reports (may be used later) but documented.
--  7. All CRLF → LF line endings.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ── DROP ORDER (children before parents) ─────────────────────
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS sensor_overrides;
DROP TABLE IF EXISTS alert_messages;
DROP TABLE IF EXISTS alerts;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS zones;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ════════════════════════════════════════════════════════════
--  USERS
--  Stores all authenticated accounts.
--  Roles: Administrator > Library Manager > Library Staff
-- ════════════════════════════════════════════════════════════
CREATE TABLE users (
  id         VARCHAR(20)  NOT NULL,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(100) NOT NULL,
  password   VARCHAR(255) NOT NULL,           -- bcrypt hash (60 chars minimum)
  role       ENUM(
               'Administrator',
               'Library Manager',
               'Library Staff'
             )            NOT NULL DEFAULT 'Library Staff',
  status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login VARCHAR(100)             DEFAULT NULL,
  created_at TIMESTAMP               DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY  uq_users_email (email),
  INDEX       idx_users_role   (role),
  INDEX       idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════
--  ZONES
--  Each row is a monitored library area with its own sensor.
-- ════════════════════════════════════════════════════════════
CREATE TABLE zones (
  id              VARCHAR(20)   NOT NULL,
  name            VARCHAR(100)  NOT NULL,
  floor           VARCHAR(10)   NOT NULL,
  capacity        INT           NOT NULL DEFAULT 50,
  occupied        INT           NOT NULL DEFAULT 0,
  level           DECIMAL(5,2)  NOT NULL DEFAULT 0.00,  -- current dB reading
  warn_threshold  INT           NOT NULL DEFAULT 40,    -- dB → warning status
  crit_threshold  INT           NOT NULL DEFAULT 60,    -- dB → critical status
  sensor          VARCHAR(20)   NOT NULL,               -- sensor hardware ID
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  battery         INT           NOT NULL DEFAULT 100,   -- sensor battery %
  manual_override TINYINT(1)    NOT NULL DEFAULT 0,     -- 1 = level set manually
  description     TEXT                   DEFAULT NULL,
  lat             DECIMAL(10,7)          DEFAULT NULL,  -- for Leaflet map
  lng             DECIMAL(10,7)          DEFAULT NULL,
  updated_at      TIMESTAMP              DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  INDEX idx_zones_status (status),
  INDEX idx_zones_floor  (floor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════
--  ALERTS
--  Created automatically when a zone exceeds a threshold.
--  Resolved manually by Library Manager or Administrator.
--
--  type:   'warning'  = exceeded warn_threshold
--          'critical' = exceeded crit_threshold
--          'resolved' = manually closed by staff
--  status: 'active'   = still unresolved
--          'resolved' = closed
-- ════════════════════════════════════════════════════════════
CREATE TABLE alerts (
  id            VARCHAR(30)  NOT NULL,
  zone_name     VARCHAR(100) NOT NULL,
  level         DECIMAL(5,2) NOT NULL,
  type          ENUM('warning','critical','resolved') NOT NULL DEFAULT 'warning',
  msg           TEXT                 DEFAULT NULL,     -- optional detail message
  status        ENUM('active','resolved')  NOT NULL DEFAULT 'active',
  resolved_by   VARCHAR(100)         DEFAULT NULL,
  resolved_at   VARCHAR(50)          DEFAULT NULL,
  sent_to_admin TINYINT(1)           NOT NULL DEFAULT 0,
  alert_date    VARCHAR(100)         NOT NULL,         -- e.g. "May 02, 2026"
  alert_time    VARCHAR(20)          NOT NULL,         -- e.g. "10:30 AM"
  created_at    TIMESTAMP            DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  INDEX idx_alerts_status    (status),
  INDEX idx_alerts_zone_name (zone_name),
  INDEX idx_alerts_type      (type),
  INDEX idx_alerts_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════
--  ALERT MESSAGES
--  Threaded staff notes attached to individual alerts.
--  Deleted automatically when the parent alert is deleted.
-- ════════════════════════════════════════════════════════════
CREATE TABLE alert_messages (
  id         VARCHAR(30)  NOT NULL,
  alert_id   VARCHAR(30)  NOT NULL,
  from_name  VARCHAR(100) NOT NULL,
  from_role  VARCHAR(50)  NOT NULL,
  message    TEXT         NOT NULL,
  msg_time   VARCHAR(20)  NOT NULL,         -- "10:30 AM"
  msg_date   VARCHAR(100) NOT NULL,         -- "May 02, 2026"
  is_system  TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 = auto-generated system note
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  CONSTRAINT fk_amsg_alert
    FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════
--  REPORTS
--  Log of reports generated by Managers and Administrators.
-- ════════════════════════════════════════════════════════════
CREATE TABLE reports (
  id            VARCHAR(30)  NOT NULL,
  type          VARCHAR(100) NOT NULL,          -- e.g. "Daily Noise Summary"
  generated_by  VARCHAR(100) NOT NULL,
  role          VARCHAR(50)  NOT NULL,
  report_date   VARCHAR(100) NOT NULL,
  report_time   VARCHAR(20)  NOT NULL,
  notes         TEXT         DEFAULT NULL,
  -- reserved for future email/notification features:
  sent_to_admin TINYINT(1)   NOT NULL DEFAULT 0,
  sent_at       VARCHAR(50)  DEFAULT NULL,
  admin_read_at VARCHAR(50)  DEFAULT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  INDEX idx_reports_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════
--  SENSOR OVERRIDES
--  One row per zone that has a manual level override active.
--  Deleted automatically when the parent zone is deleted.
-- ════════════════════════════════════════════════════════════
CREATE TABLE sensor_overrides (
  zone_id  VARCHAR(20)  NOT NULL,
  level    DECIMAL(5,2) NOT NULL,
  set_by   VARCHAR(100) NOT NULL,
  set_at   VARCHAR(20)  NOT NULL,    -- "10:30 AM"
  set_date VARCHAR(100) NOT NULL,    -- "May 02, 2026"

  PRIMARY KEY (zone_id),
  CONSTRAINT fk_sov_zone
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════
--  ACTIVITY LOGS
--  Full audit trail of every user action across the system.
--
--  Logged actions include:
--    Auth:      Login, Login Failed, Logout, Register
--    Users:     Add User, Edit User Role, Toggle User,
--               Reset Password, Delete User
--    Zones:     Add Zone, Edit Zone, Delete Zone,
--               Override Zone, Clear Override
--    Alerts:    Resolve Alert, Alert Message
--    Reports:   Generated Report, Viewed Reports
--    Logs:      Cleared Activity Logs, Exported Activity Logs
--    Pages:     Viewed [PageName] (page-view tracking)
-- ════════════════════════════════════════════════════════════
CREATE TABLE activity_logs (
  id         INT          NOT NULL AUTO_INCREMENT,
  user_id    VARCHAR(20)  NOT NULL,   -- User ID ('—' for failed/unknown logins)
  user_name  VARCHAR(100) NOT NULL,   -- Name at time of action
  user_role  VARCHAR(50)  NOT NULL,   -- Role at time of action
  action     VARCHAR(100) NOT NULL,   -- Short label: "Login", "Delete Zone", etc.
  detail     TEXT         DEFAULT NULL,  -- Human-readable description
  page       VARCHAR(100) DEFAULT NULL,  -- Source PHP page (e.g. "zones")
  ip         VARCHAR(45)  DEFAULT NULL,  -- IPv4 or IPv6 address
  browser    VARCHAR(255) DEFAULT NULL,  -- Parsed browser name/version
  user_agent TEXT         DEFAULT NULL,  -- Full HTTP_USER_AGENT string
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  INDEX idx_alog_user_id  (user_id),
  INDEX idx_alog_created  (created_at),
  INDEX idx_alog_action   (action),
  INDEX idx_alog_page     (page)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════
--  SEED DATA
-- ════════════════════════════════════════════════════════════
START TRANSACTION;

-- ── Users ─────────────────────────────────────────────────────
-- Passwords are bcrypt hashes (cost=10, PASSWORD_BCRYPT).
-- Plain-text originals for reference only — remove this comment
-- before deploying to production.
--   U-001  admin123
--   U-002  james123
--   U-003  staff123
--
-- To generate a new hash:
--   php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"
--
-- NOTE: auth.php also auto-upgrades any surviving plain-text
-- passwords to bcrypt on first successful login.
INSERT INTO users (id, name, email, password, role, status) VALUES
  ('U-001', 'Johnlloyd P.',
   'admin@library.edu',
   '$2b$10$C2jRbtfR8N6CPOyKD8AtLepFG.y38ZVf74/RewHnwtvT5RISxi5Ry',
   'Administrator',   'active'),

  ('U-002', 'James Anticamars',
   'james@library.edu',
   '$2b$10$egALwT5HhYUTk1F.54hunen9RJWMKV6PyPu5L0J1ZJqxX1Ej7t3Gm',
   'Library Manager', 'active'),

  ('U-003', 'Dimavier',
   'staff@library.edu',
   '$2b$10$S.uGWFFx/mefvHjulH6dOedKC0KB4mAfaD84DoplKpBNO28Rc5GF6',
   'Library Staff',   'active');

-- ── Zones ─────────────────────────────────────────────────────
-- Coordinates are approximate positions on the NBSC campus map.
-- Adjust lat/lng to match your actual sensor placement.
INSERT INTO zones (id, name, floor, capacity, occupied, level, warn_threshold, crit_threshold, sensor, battery, lat, lng, description) VALUES
  ('Z-001', 'Reading Area',  '1F', 80, 45, 28.00, 40, 60, 'SNS-001', 85,
   8.3592480, 124.8678530, 'Main reading area on the ground floor.'),

  ('Z-002', 'Study Area',    '2F', 20, 18, 52.00, 40, 60, 'SNS-002', 72,
   8.3592720, 124.8679500, 'Private study room for small groups.'),

  ('Z-003', 'Computer Area', '1F', 40, 12, 18.00, 35, 55, 'SNS-003', 91,
   8.3593220, 124.8676740, 'Computer laboratory section.');

COMMIT;
