

CREATE DATABASE IF NOT EXISTS ssms;

USE ssms;

SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- School Profile
-- --------------------------------------------------------------------------
CREATE TABLE schools (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(160)  NOT NULL,
  motto             VARCHAR(200),
  logo_path         VARCHAR(255),
  physical_address  VARCHAR(255),
  postal_address    VARCHAR(255),
  phone             VARCHAR(40),
  alternate_phone   VARCHAR(40),
  email             VARCHAR(120),
  website           VARCHAR(160),
  registration_number VARCHAR(60),
  ownership_type    ENUM('government','private','church','community','other') NOT NULL DEFAULT 'private',
  emis_code         VARCHAR(40),
  uneb_index_number VARCHAR(40),
  district          VARCHAR(80),
  sub_county        VARCHAR(80),
  date_established  DATE,
  currency          VARCHAR(10)   NOT NULL DEFAULT 'UGX',
  currency_symbol   VARCHAR(10)   NOT NULL DEFAULT 'Ugx',
  date_format       VARCHAR(20)   NOT NULL DEFAULT 'd/m/Y',
  timezone          VARCHAR(60)   NOT NULL DEFAULT 'Africa/Kampala',
  status            ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);

-- --------------------------------------------------------------------------
-- Role Management
-- --------------------------------------------------------------------------
CREATE TABLE roles (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(80)  NOT NULL,
  slug        VARCHAR(80)  NOT NULL,
  description VARCHAR(255),
  is_system   TINYINT(1)   NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_role_slug (school_id,slug),
  CONSTRAINT fk_roles_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Permission Management (one row per controlled module / sensitive area)
-- The action level (view, add, edit, approve, print, delete) is held on
-- role_permissions, so a role can be given view-only access to a module.
-- --------------------------------------------------------------------------
CREATE TABLE permissions (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module_code  VARCHAR(40)  NOT NULL,
  code         VARCHAR(80)  NOT NULL,
  name         VARCHAR(120) NOT NULL,
  description  VARCHAR(255),
  sort_order   SMALLINT     NOT NULL DEFAULT 0,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permission_code (code)
);

CREATE TABLE role_permissions (
  role_id      INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  can_view     TINYINT(1) NOT NULL DEFAULT 0,
  can_add      TINYINT(1) NOT NULL DEFAULT 0,
  can_edit     TINYINT(1) NOT NULL DEFAULT 0,
  can_approve  TINYINT(1) NOT NULL DEFAULT 0,
  can_print    TINYINT(1) NOT NULL DEFAULT 0,
  can_delete   TINYINT(1) NOT NULL DEFAULT 0,
  created_at   TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id,permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- User Management
-- staff_id / guardian_id / student_id are linked later (FKs added after the
-- referenced tables are created, to avoid circular dependencies).
-- --------------------------------------------------------------------------
CREATE TABLE users (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id          INT UNSIGNED NOT NULL,
  username           VARCHAR(60)  NOT NULL,
  full_name          VARCHAR(140) NOT NULL,
  email              VARCHAR(120),
  phone              VARCHAR(40),
  password_hash      VARCHAR(255),
  user_type          ENUM('staff','parent','student','system') NOT NULL DEFAULT 'staff',
  role_id            INT UNSIGNED,
  staff_id           INT UNSIGNED,
  guardian_id        INT UNSIGNED,
  student_id         INT UNSIGNED,
  preferred_language VARCHAR(20)  NOT NULL DEFAULT 'en',
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  is_active          TINYINT(1)   NOT NULL DEFAULT 1,
  failed_attempts    SMALLINT     NOT NULL DEFAULT 0,
  locked_until       DATETIME,
  last_login_at      DATETIME,
  last_login_ip      VARCHAR(45),
  created_by         INT UNSIGNED,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_username (school_id,username),
  UNIQUE KEY uq_user_email (school_id,email),
  KEY idx_users_role (role_id),
  CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE SET NULL
);

CREATE TABLE user_roles (
  user_id    INT UNSIGNED NOT NULL,
  role_id    INT UNSIGNED NOT NULL,
  granted_by INT UNSIGNED,
  granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id,role_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Academic Year Management
-- --------------------------------------------------------------------------
CREATE TABLE academic_years (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(20)  NOT NULL,
  start_date  DATE         NOT NULL,
  end_date    DATE         NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 0,
  status      ENUM('planned','active','closed') NOT NULL DEFAULT 'planned',
  notes       VARCHAR(255),
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_academic_year (school_id,name),
  CONSTRAINT fk_academic_years_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Term Management
-- --------------------------------------------------------------------------
CREATE TABLE terms (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED NOT NULL,
  name             VARCHAR(30)  NOT NULL,
  term_number      TINYINT      NOT NULL,
  start_date       DATE         NOT NULL,
  end_date         DATE         NOT NULL,
  report_date      DATE,
  is_active        TINYINT(1)   NOT NULL DEFAULT 0,
  status           ENUM('planned','active','closed') NOT NULL DEFAULT 'planned',
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_term (academic_year_id,term_number),
  CONSTRAINT fk_terms_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Class Management (S1 - S6)
-- --------------------------------------------------------------------------
CREATE TABLE classes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(30)  NOT NULL,
  level       ENUM('O-Level','A-Level') NOT NULL,
  order_index SMALLINT     NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_class_name (school_id,name),
  CONSTRAINT fk_classes_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Stream Management
-- class_teacher_id is added in 01_hr_module.sql (staff table created there).
-- --------------------------------------------------------------------------
CREATE TABLE streams (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id       INT UNSIGNED NOT NULL,
  name           VARCHAR(40)  NOT NULL,
  capacity       SMALLINT     NOT NULL DEFAULT 50,
  room_name      VARCHAR(60),
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stream_name (class_id,name),
  CONSTRAINT fk_streams_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Department Management (single source for Academic and HR)
-- head_staff_id is added in 01_hr_module.sql
-- --------------------------------------------------------------------------
CREATE TABLE departments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(100) NOT NULL,
  type        ENUM('academic','non-academic') NOT NULL DEFAULT 'academic',
  description VARCHAR(255),
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_department_name (school_id,name),
  CONSTRAINT fk_departments_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- House Management
-- house_master_staff_id is added in 01_hr_module.sql
-- --------------------------------------------------------------------------
CREATE TABLE houses (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id  INT UNSIGNED NOT NULL,
  name       VARCHAR(60)  NOT NULL,
  colour     VARCHAR(30),
  motto      VARCHAR(160),
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_house_name (school_id,name),
  CONSTRAINT fk_houses_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- System Settings (general configuration, no code changes needed)
-- --------------------------------------------------------------------------
CREATE TABLE settings (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  setting_group  VARCHAR(40)  NOT NULL DEFAULT 'general',
  setting_key    VARCHAR(80)  NOT NULL,
  setting_value  TEXT,
  value_type     ENUM('string','int','decimal','bool','json','date') NOT NULL DEFAULT 'string',
  description    VARCHAR(255),
  is_editable    TINYINT(1)   NOT NULL DEFAULT 1,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_setting_key (school_id,setting_key),
  CONSTRAINT fk_settings_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Number sequences (admission numbers, receipts, invoices, gate passes...)
-- --------------------------------------------------------------------------
CREATE TABLE sequences (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  seq_key     VARCHAR(50)  NOT NULL,
  prefix      VARCHAR(20)  NOT NULL DEFAULT '',
  suffix      VARCHAR(20)  NOT NULL DEFAULT '',
  next_value  INT UNSIGNED NOT NULL DEFAULT 1,
  padding     TINYINT      NOT NULL DEFAULT 5,
  description VARCHAR(160),
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sequence_key (school_id,seq_key),
  CONSTRAINT fk_sequences_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Audit Logs (who did what, when)
-- --------------------------------------------------------------------------
CREATE TABLE audit_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED,
  user_name   VARCHAR(140),
  role_name   VARCHAR(80),
  module      VARCHAR(60),
  action      VARCHAR(30)  NOT NULL,
  entity_type VARCHAR(60),
  entity_id   INT UNSIGNED,
  summary     VARCHAR(255),
  old_values  JSON,
  new_values  JSON,
  ip_address  VARCHAR(45),
  user_agent  VARCHAR(255),
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_school_date (school_id,created_at),
  KEY idx_audit_user (user_id),
  KEY idx_audit_entity (entity_type,entity_id),
  CONSTRAINT fk_audit_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);
