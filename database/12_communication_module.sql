-- ==========================================================================
-- SSMS - File : 12_communication_module.sql
-- Module      : 2.13 Communication Management
-- Depends on  : 00 - 11 (users, students, guardians, staff, announcements)
-- Note        : Event notifications are produced from academic_events
--               (module 2.3) through the notifications table below.
-- ==========================================================================

USE ssms;
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- Notification Templates (SMS, email, portal)
-- --------------------------------------------------------------------------
CREATE TABLE notification_templates (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    INT UNSIGNED NOT NULL,
  template_code VARCHAR(60) NOT NULL,
  name         VARCHAR(120) NOT NULL,
  channel      ENUM('sms','email','portal') NOT NULL DEFAULT 'sms',
  subject      VARCHAR(180),
  body         TEXT NOT NULL,
  placeholders VARCHAR(255),
  related_module VARCHAR(40),
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_by   INT UNSIGNED,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_template_code (school_id,template_code,channel),
  CONSTRAINT fk_template_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Announcements (notices for parents, students, staff or a class)
-- --------------------------------------------------------------------------
CREATE TABLE announcements (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  title          VARCHAR(180) NOT NULL,
  body           TEXT NOT NULL,
  category       ENUM('general','fees','examination','results','events','emergency','holiday','pto') NOT NULL DEFAULT 'general',
  audience       ENUM('all','parents','students','staff','class','role','house') NOT NULL DEFAULT 'all',
  class_id       INT UNSIGNED,
  role_id        INT UNSIGNED,
  house_id       INT UNSIGNED,
  publish_from   DATETIME,
  publish_until  DATETIME,
  show_on_portal TINYINT(1) NOT NULL DEFAULT 1,
  show_on_dashboard TINYINT(1) NOT NULL DEFAULT 1,
  send_sms       TINYINT(1) NOT NULL DEFAULT 0,
  send_email     TINYINT(1) NOT NULL DEFAULT 0,
  attachment_path VARCHAR(255),
  is_emergency   TINYINT(1) NOT NULL DEFAULT 0,
  status         ENUM('draft','scheduled','published','archived') NOT NULL DEFAULT 'draft',
  created_by     INT UNSIGNED,
  published_at   DATETIME,
  view_count     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_announcement_status (status,publish_from),
  KEY idx_announcement_audience (audience,category),
  CONSTRAINT fk_announcement_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_announcement_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_announcement_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_announcement_house FOREIGN KEY (house_id) REFERENCES houses (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Notifications and Message History (single outbox for SMS/email/portal)
-- --------------------------------------------------------------------------
CREATE TABLE notifications (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  template_id      INT UNSIGNED,
  related_module   VARCHAR(40),
  related_entity   VARCHAR(60),
  related_id       INT UNSIGNED,
  channel          ENUM('sms','email','portal','push','in_app') NOT NULL DEFAULT 'sms',
  category         ENUM('fees','attendance','results','announcement','event','emergency','reminder','general','canteen','transport') NOT NULL DEFAULT 'general',
  audience         ENUM('single','guardians','students','staff','role','class','all') NOT NULL DEFAULT 'single',
  subject          VARCHAR(180),
  message          TEXT NOT NULL,
  recipient_count  INT UNSIGNED NOT NULL DEFAULT 0,
  delivered_count  INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count     INT UNSIGNED NOT NULL DEFAULT 0,
  is_emergency     TINYINT(1) NOT NULL DEFAULT 0,
  priority         ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status           ENUM('queued','sending','sent','partially_sent','failed','cancelled') NOT NULL DEFAULT 'queued',
  scheduled_for    DATETIME,
  sent_at          DATETIME,
  sent_by          INT UNSIGNED,
  provider         VARCHAR(60),
  provider_batch_id VARCHAR(120),
  cost             DECIMAL(10,2),
  error_note       VARCHAR(500),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notification_status (status,scheduled_for),
  KEY idx_notification_category (category,created_at),
  CONSTRAINT fk_notification_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_notification_template FOREIGN KEY (template_id) REFERENCES notification_templates (id) ON DELETE SET NULL
);

CREATE TABLE notification_recipients (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_id  BIGINT UNSIGNED NOT NULL,
  recipient_type   ENUM('guardian','student','staff','user','other') NOT NULL,
  guardian_id      INT UNSIGNED,
  student_id       INT UNSIGNED,
  staff_id         INT UNSIGNED,
  user_id          INT UNSIGNED,
  name             VARCHAR(160),
  destination      VARCHAR(120),
  delivery_status  ENUM('pending','sent','delivered','failed','read','acknowledged') NOT NULL DEFAULT 'pending',
  sent_at          DATETIME,
  delivered_at     DATETIME,
  read_at          DATETIME,
  provider_message_id VARCHAR(120),
  error_note       VARCHAR(255),
  attempts         SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_recipient_notification (notification_id,delivery_status),
  KEY idx_recipient_guardian (guardian_id),
  KEY idx_recipient_student (student_id),
  KEY idx_recipient_staff (staff_id),
  CONSTRAINT fk_recipient_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE CASCADE,
  CONSTRAINT fk_recipient_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE CASCADE,
  CONSTRAINT fk_recipient_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_recipient_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE,
  CONSTRAINT fk_recipient_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

ALTER TABLE attendance_notifications
  ADD CONSTRAINT fk_att_notif_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE SET NULL;

-- --------------------------------------------------------------------------
-- Parent / Student / Teacher Messaging
-- --------------------------------------------------------------------------
CREATE TABLE messages (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  parent_message_id BIGINT UNSIGNED,
  direction        ENUM('inbound','outbound','internal') NOT NULL DEFAULT 'inbound',
  sender_type      ENUM('guardian','student','staff','admin','system') NOT NULL,
  sender_guardian_id INT UNSIGNED,
  sender_student_id  INT UNSIGNED,
  sender_staff_id    INT UNSIGNED,
  sender_user_id     INT UNSIGNED,
  recipient_type   ENUM('staff','admin','guardian','student','role','all_staff') NOT NULL DEFAULT 'staff',
  recipient_staff_id INT UNSIGNED,
  recipient_guardian_id INT UNSIGNED,
  recipient_student_id  INT UNSIGNED,
  recipient_role_id INT UNSIGNED,
  subject          VARCHAR(180),
  body             TEXT NOT NULL,
  attachment_path  VARCHAR(255),
  related_module   VARCHAR(40),
  related_id       INT UNSIGNED,
  thread_key       VARCHAR(64),
  status           ENUM('open','replied','archived','spam','closed') NOT NULL DEFAULT 'open',
  priority         ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  read_at          DATETIME,
  handled_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_message_thread (thread_key),
  KEY idx_message_guardian (sender_guardian_id,created_at),
  KEY idx_message_staff (recipient_staff_id,status),
  CONSTRAINT fk_message_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_message_parent FOREIGN KEY (parent_message_id) REFERENCES messages (id) ON DELETE SET NULL,
  CONSTRAINT fk_message_sender_guardian FOREIGN KEY (sender_guardian_id) REFERENCES guardians (id) ON DELETE SET NULL,
  CONSTRAINT fk_message_sender_student FOREIGN KEY (sender_student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_message_sender_staff FOREIGN KEY (sender_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_message_sender_user FOREIGN KEY (sender_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_message_recipient_staff FOREIGN KEY (recipient_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_message_recipient_guardian FOREIGN KEY (recipient_guardian_id) REFERENCES guardians (id) ON DELETE SET NULL,
  CONSTRAINT fk_message_recipient_student FOREIGN KEY (recipient_student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_message_recipient_role FOREIGN KEY (recipient_role_id) REFERENCES roles (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- PTA Management
-- --------------------------------------------------------------------------
CREATE TABLE pta_meetings (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  title            VARCHAR(180) NOT NULL,
  meeting_date     DATE NOT NULL,
  start_time       TIME,
  venue            VARCHAR(160),
  agenda           TEXT,
  attendance_count SMALLINT,
  minutes_path     VARCHAR(255),
  resolutions      TEXT,
  contributions_agreed DECIMAL(14,2),
  contributions_received DECIMAL(14,2),
  chairperson      VARCHAR(140),
  secretary        VARCHAR(140),
  notification_sent TINYINT(1) NOT NULL DEFAULT 0,
  status           ENUM('planned','held','minutes_recorded','closed') NOT NULL DEFAULT 'planned',
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pta_date (meeting_date,status),
  CONSTRAINT fk_pta_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_pta_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_pta_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE pta_attendance (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pta_meeting_id INT UNSIGNED NOT NULL,
  guardian_id    INT UNSIGNED NOT NULL,
  student_id     INT UNSIGNED,
  is_proxy       TINYINT(1) NOT NULL DEFAULT 0,
  proxy_name     VARCHAR(140),
  arrived_at     DATETIME,
  signed         TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pta_attendance (pta_meeting_id,guardian_id,student_id),
  CONSTRAINT fk_pta_att_meeting FOREIGN KEY (pta_meeting_id) REFERENCES pta_meetings (id) ON DELETE CASCADE,
  CONSTRAINT fk_pta_att_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE CASCADE,
  CONSTRAINT fk_pta_att_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

