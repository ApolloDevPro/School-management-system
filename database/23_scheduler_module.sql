USE ssms;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cron_jobs (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED,
  name             VARCHAR(140) NOT NULL,
  description      VARCHAR(255),
  handler_class    VARCHAR(160) NOT NULL,
  handler_method   VARCHAR(60) NOT NULL DEFAULT 'run',
  interval_minutes INT UNSIGNED NOT NULL DEFAULT 60,
  next_run_at      DATETIME,
  last_run_at      DATETIME,
  last_run_status  ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
  last_run_message VARCHAR(255),
  run_count        INT UNSIGNED NOT NULL DEFAULT 0,
  failure_count    INT UNSIGNED NOT NULL DEFAULT 0,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cron_next_run (next_run_at, is_active),
  KEY idx_cron_school (school_id),
  CONSTRAINT fk_cron_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cron_job_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id      INT UNSIGNED NOT NULL,
  status      ENUM('success','error') NOT NULL,
  message     TEXT,
  duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cron_log_job (job_id, created_at),
  CONSTRAINT fk_cron_log_job FOREIGN KEY (job_id) REFERENCES cron_jobs (id) ON DELETE CASCADE
);
