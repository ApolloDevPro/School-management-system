-- ==========================================================================
-- SSMS - File : 15_gate_module.sql
-- Module      : 2.17 Visitor and Gate Management
-- Depends on  : 00 - 14 (students, staff, exeats, gate passes)
-- ==========================================================================

USE ssms;
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- Visitor Registration
-- --------------------------------------------------------------------------
CREATE TABLE visitors (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  full_name        VARCHAR(160) NOT NULL,
  phone            VARCHAR(40),
  id_type          ENUM('national_id','passport','voter_card','staff_id','other'),
  id_number        VARCHAR(60),
  organisation     VARCHAR(160),
  host_type        ENUM('staff','student','other') NOT NULL DEFAULT 'staff',
  host_staff_id    INT UNSIGNED,
  host_student_id  INT UNSIGNED,
  host_name        VARCHAR(160),
  purpose          VARCHAR(255) NOT NULL,
  vehicle_plate    VARCHAR(20),
  badge_number     VARCHAR(30),
  items_carried    VARCHAR(255),
  time_in          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  time_out         DATETIME,
  duration_minutes SMALLINT,
  checked_in_by    INT UNSIGNED,
  checked_out_by   INT UNSIGNED,
  status           ENUM('inside','out','denied','overstay','refused_exit') NOT NULL DEFAULT 'inside',
  remarks          VARCHAR(255),
  photo_path       VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_visitor_time (time_in,status),
  KEY idx_visitor_name (full_name),
  CONSTRAINT fk_visitor_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_visitor_host_staff FOREIGN KEY (host_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_visitor_host_student FOREIGN KEY (host_student_id) REFERENCES students (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- Gate Passes (students, staff or goods leaving the school)
-- --------------------------------------------------------------------------
CREATE TABLE gate_passes (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pass_number      VARCHAR(40)  NOT NULL,
  school_id        INT UNSIGNED NOT NULL,
  pass_type        ENUM('person','goods','vehicle','official') NOT NULL DEFAULT 'person',
  holder_type      ENUM('student','staff','visitor','other') NOT NULL DEFAULT 'student',
  student_id       INT UNSIGNED,
  staff_id         INT UNSIGNED,
  carrier_name     VARCHAR(160),
  description      VARCHAR(255) NOT NULL,
  quantity         SMALLINT,
  reason           VARCHAR(255),
  destination      VARCHAR(160),
  valid_from       DATETIME NOT NULL,
  valid_until      DATETIME NOT NULL,
  requested_by     INT UNSIGNED,
  approved_by      INT UNSIGNED,
  approved_at      DATETIME,
  issued_by        INT UNSIGNED,
  issued_at        DATETIME,
  verified_out_at  DATETIME,
  verified_out_by  INT UNSIGNED,
  verified_in_at   DATETIME,
  verified_in_by   INT UNSIGNED,
  status           ENUM('requested','approved','rejected','issued','used','returned','expired','cancelled') NOT NULL DEFAULT 'requested',
  rejection_reason VARCHAR(255),
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pass_number (school_id,pass_number),
  KEY idx_pass_status (status,valid_until),
  KEY idx_pass_holder (student_id,staff_id),
  CONSTRAINT fk_pass_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_pass_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_pass_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- Exeat Verification at the gate (writes back to boarding exeats)
-- --------------------------------------------------------------------------
CREATE TABLE exeat_verifications (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  exeat_id         INT UNSIGNED NOT NULL,
  gate_direction   ENUM('out','in') NOT NULL,
  verified_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verified_by      INT UNSIGNED NOT NULL,
  id_checked       TINYINT(1) NOT NULL DEFAULT 1,
  condition_notes  VARCHAR(255),
  vehicle_plate    VARCHAR(20),
  guardian_present TINYINT(1) NOT NULL DEFAULT 0,
  remarks          VARCHAR(255),
  ip_address       VARCHAR(45),
  PRIMARY KEY (id),
  KEY idx_exeat_verification (exeat_id,gate_direction),
  CONSTRAINT fk_exv_exeat FOREIGN KEY (exeat_id) REFERENCES exeats (id) ON DELETE CASCADE,
  CONSTRAINT fk_exv_staff FOREIGN KEY (verified_by) REFERENCES staff (id) ON DELETE RESTRICT
);

-- --------------------------------------------------------------------------
-- Vehicle Log
-- --------------------------------------------------------------------------
CREATE TABLE vehicle_entries (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  plate_number     VARCHAR(20)  NOT NULL,
  driver_name      VARCHAR(140),
  direction        ENUM('in','out') NOT NULL,
  entry_time       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  exit_time        DATETIME,
  purpose          VARCHAR(255),
  vehicle_owner    ENUM('school','visitor','staff','contractor','other') NOT NULL DEFAULT 'visitor',
  school_vehicle_id INT UNSIGNED,
  passenger_count  SMALLINT,
  goods_carried    VARCHAR(255),
  gate_pass_id     INT UNSIGNED,
  host_staff_id    INT UNSIGNED,
  checked_by       INT UNSIGNED,
  odometer_reading DECIMAL(10,1),
  remarks          VARCHAR(255),
  PRIMARY KEY (id),
  KEY idx_vehicle_entry_plate (plate_number,entry_time),
  KEY idx_vehicle_entry_time (direction,entry_time),
  CONSTRAINT fk_ve_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_ve_school_vehicle FOREIGN KEY (school_vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL,
  CONSTRAINT fk_ve_gate_pass FOREIGN KEY (gate_pass_id) REFERENCES gate_passes (id) ON DELETE SET NULL,
  CONSTRAINT fk_ve_host_staff FOREIGN KEY (host_staff_id) REFERENCES staff (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- Security Incident Log
-- --------------------------------------------------------------------------
CREATE TABLE security_incidents (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  incident_number  VARCHAR(40)  NOT NULL,
  school_id        INT UNSIGNED NOT NULL,
  incident_date    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  incident_type    ENUM('theft','trespass','damage','fight','fire','intrusion','drug_abuse','vandalism','accident','other') NOT NULL,
  location         VARCHAR(140) NOT NULL,
  description      TEXT NOT NULL,
  persons_involved VARCHAR(255),
  witnesses        VARCHAR(255),
  items_lost_value DECIMAL(12,2),
  action_taken     TEXT,
  police_reported  TINYINT(1) NOT NULL DEFAULT 0,
  police_reference VARCHAR(80),
  reported_by      INT UNSIGNED,
  reported_to      INT UNSIGNED,
  status           ENUM('open','investigating','resolved','closed','referred_police') NOT NULL DEFAULT 'open',
  resolved_at      DATETIME,
  evidence_path    VARCHAR(255),
  parent_informed  TINYINT(1) NOT NULL DEFAULT 0,
  notes            TEXT,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_security_number (school_id,incident_number),
  KEY idx_security_type (incident_type,status),
  KEY idx_security_date (incident_date),
  CONSTRAINT fk_security_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

