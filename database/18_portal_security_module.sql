USE ssms;
SET NAMES utf8mb4;

CREATE TABLE portal_sessions (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  session_token_hash CHAR(64) NOT NULL,
  ip_address      VARCHAR(45),
  user_agent      VARCHAR(255),
  device_type     ENUM('desktop','mobile','tablet','unknown') NOT NULL DEFAULT 'unknown',
  portal_type     ENUM('admin','parent','student','staff') NOT NULL DEFAULT 'admin',
  started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME NOT NULL,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  logout_at       DATETIME,
  logout_reason   ENUM('user','timeout','admin','password_change','concurrent'),
  PRIMARY KEY (id),
  KEY idx_session_user (user_id,is_active),
  KEY idx_session_expiry (expires_at,is_active),
  CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    INT UNSIGNED,
  username     VARCHAR(60),
  user_id      INT UNSIGNED,
  user_type    ENUM('staff','parent','student','system'),
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address   VARCHAR(45),
  user_agent   VARCHAR(255),
  was_successful TINYINT(1) NOT NULL DEFAULT 0,
  failure_reason ENUM('wrong_password','no_account','locked','inactive','expired_password','two_factor','other'),
  PRIMARY KEY (id),
  KEY idx_attempt_user (user_id,attempted_at),
  KEY idx_attempt_ip (ip_address,attempted_at),
  CONSTRAINT fk_attempt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE password_resets (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  token_hash    CHAR(64) NOT NULL,
  channel       ENUM('email','sms','admin') NOT NULL DEFAULT 'email',
  requested_ip  VARCHAR(45),
  requested_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    DATETIME NOT NULL,
  used_at       DATETIME,
  is_used       TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_reset_token (token_hash),
  KEY idx_reset_user (user_id,is_used),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE password_history (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by     INT UNSIGNED,
  change_reason  ENUM('first_login','policy_change','self_reset','admin_reset') NOT NULL DEFAULT 'self_reset',
  PRIMARY KEY (id),
  KEY idx_password_history (user_id,created_at),
  CONSTRAINT fk_password_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE two_factor_codes (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  channel      ENUM('sms','email','authenticator') NOT NULL DEFAULT 'sms',
  code_hash    CHAR(64) NOT NULL,
  destination  VARCHAR(120),
  attempts     TINYINT NOT NULL DEFAULT 0,
  max_attempts TINYINT NOT NULL DEFAULT 5,
  issued_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME NOT NULL,
  verified_at  DATETIME,
  is_used      TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_2fa_user (user_id,is_used),
  CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE data_backups (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED,
  backup_type    ENUM('full','database','files','incremental') NOT NULL DEFAULT 'database',
  schedule_name  VARCHAR(80),
  file_path      VARCHAR(500) NOT NULL,
  file_size_bytes BIGINT UNSIGNED,
  checksum       CHAR(64),
  started_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at    DATETIME,
  duration_seconds INT UNSIGNED,
  status         ENUM('running','success','failed','partial','verified','expired') NOT NULL DEFAULT 'running',
  error_message  VARCHAR(500),
  retention_until DATE,
  is_encrypted   TINYINT(1) NOT NULL DEFAULT 0,
  performed_by   INT UNSIGNED,
  trigger_type   ENUM('scheduled','manual','auto_before_upgrade') NOT NULL DEFAULT 'manual',
  restored_at    DATETIME,
  restore_note   VARCHAR(255),
  PRIMARY KEY (id),
  KEY idx_backup_status (status,started_at),
  CONSTRAINT fk_backup_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE data_import_jobs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  target_module  VARCHAR(40)  NOT NULL,
  file_name      VARCHAR(255) NOT NULL,
  file_path      VARCHAR(500) NOT NULL,
  file_size_bytes BIGINT UNSIGNED,
  total_rows     INT UNSIGNED NOT NULL DEFAULT 0,
  success_rows   INT UNSIGNED NOT NULL DEFAULT 0,
  error_rows     INT UNSIGNED NOT NULL DEFAULT 0,
  skipped_rows   INT UNSIGNED NOT NULL DEFAULT 0,
  errors         JSON,
  mapping        JSON,
  status         ENUM('uploaded','validating','valid','invalid','importing','completed','failed','cancelled') NOT NULL DEFAULT 'uploaded',
  dry_run        TINYINT(1) NOT NULL DEFAULT 1,
  academic_year_id INT UNSIGNED,
  uploaded_by    INT UNSIGNED,
  started_at     DATETIME,
  finished_at    DATETIME,
  error_message  VARCHAR(500),
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_import_status (target_module,status),
  CONSTRAINT fk_import_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_import_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
);

CREATE TABLE data_export_jobs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  target_module  VARCHAR(40)  NOT NULL,
  report_code    VARCHAR(60),
  format         ENUM('csv','excel','pdf','json') NOT NULL DEFAULT 'excel',
  filters        JSON,
  row_count      INT UNSIGNED,
  file_path      VARCHAR(500),
  file_size_bytes BIGINT UNSIGNED,
  status         ENUM('queued','running','completed','failed','expired') NOT NULL DEFAULT 'queued',
  error_message  VARCHAR(500),
  requested_by   INT UNSIGNED,
  requested_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at   DATETIME,
  expires_at     DATETIME,
  download_count SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_export_status (status,target_module),
  CONSTRAINT fk_export_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE consent_records (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  subject_type     ENUM('student','guardian','staff') NOT NULL,
  student_id       INT UNSIGNED,
  guardian_id      INT UNSIGNED,
  staff_id         INT UNSIGNED,
  consent_type     ENUM('data_collection','photo_use','health_data','discipline_records','sms_communication','email_communication','portal_access','research_use','third_party_share') NOT NULL,
  granted          TINYINT(1) NOT NULL DEFAULT 1,
  granted_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  withdrawn_at     DATETIME,
  recorded_by      INT UNSIGNED,
  witnessed_by     INT UNSIGNED,
  proof_path       VARCHAR(255),
  notes            VARCHAR(255),
  expires_at       DATETIME,
  PRIMARY KEY (id),
  KEY idx_consent_subject (subject_type,student_id,guardian_id,staff_id),
  KEY idx_consent_type (consent_type,granted),
  CONSTRAINT fk_consent_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_consent_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_consent_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE CASCADE,
  CONSTRAINT fk_consent_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
);

CREATE TABLE data_subject_requests (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  request_type   ENUM('access','correction','erasure','objection','restriction','portability','withdraw_consent') NOT NULL,
  subject_type   ENUM('student','guardian','staff') NOT NULL,
  student_id     INT UNSIGNED,
  guardian_id    INT UNSIGNED,
  staff_id       INT UNSIGNED,
  requester_name VARCHAR(160) NOT NULL,
  requester_phone VARCHAR(40),
  requester_email VARCHAR(120),
  details        TEXT,
  received_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due_by         DATETIME,
  status         ENUM('received','verifying','in_progress','completed','rejected','escalated') NOT NULL DEFAULT 'received',
  response       TEXT,
  responded_by   INT UNSIGNED,
  responded_at   DATETIME,
  verification_note VARCHAR(255),
  evidence_path  VARCHAR(255),
  PRIMARY KEY (id),
  KEY idx_dsr_status (status,due_by),
  CONSTRAINT fk_dsr_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_dsr_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_dsr_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE SET NULL,
  CONSTRAINT fk_dsr_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE SET NULL
);

CREATE TABLE support_tickets (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_number  VARCHAR(40)  NOT NULL,
  school_id      INT UNSIGNED NOT NULL,
  user_id        INT UNSIGNED NOT NULL,
  category       ENUM('login','student_records','fees','examinations','attendance','report_printing','sms','payments','hardware','other') NOT NULL DEFAULT 'other',
  subject        VARCHAR(180) NOT NULL,
  description    TEXT NOT NULL,
  priority       ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status         ENUM('open','in_progress','waiting_user','resolved','closed','reopened') NOT NULL DEFAULT 'open',
  assigned_to    INT UNSIGNED,
  attachment_path VARCHAR(255),
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  first_response_at DATETIME,
  resolved_at    DATETIME,
  resolution     TEXT,
  closed_at      DATETIME,
  satisfaction_rating TINYINT,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ticket_number (school_id,ticket_number),
  KEY idx_ticket_status (status,priority),
  CONSTRAINT fk_ticket_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE ticket_replies (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id    BIGINT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  reply_body   TEXT NOT NULL,
  attachment_path VARCHAR(255),
  is_internal_note TINYINT(1) NOT NULL DEFAULT 0,
  status_change ENUM('open','in_progress','waiting_user','resolved','closed','reopened'),
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reply_ticket (ticket_id,created_at),
  CONSTRAINT fk_reply_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets (id) ON DELETE CASCADE,
  CONSTRAINT fk_reply_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE search_logs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  user_id        INT UNSIGNED,
  query_text     VARCHAR(255) NOT NULL,
  module_filter  VARCHAR(40),
  results_count  INT UNSIGNED,
  top_result_type VARCHAR(40),
  top_result_id  INT UNSIGNED,
  searched_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address     VARCHAR(45),
  PRIMARY KEY (id),
  KEY idx_search_user (user_id,searched_at),
  KEY idx_search_query (query_text),
  CONSTRAINT fk_search_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_search_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);
