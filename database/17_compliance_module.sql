-- ==========================================================================
-- SSMS - File : 17_compliance_module.sql
-- Module      : 2.19 Government Compliance and Capitation
-- Depends on  : 00 - 05 (schools, students, staff, academic years, expenses)
-- ==========================================================================

USE ssms;
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- EMIS Data Management (MoES returns)
-- --------------------------------------------------------------------------
CREATE TABLE emis_returns (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  return_type      ENUM('EMIS_termly','EMIS_annual','census','inspection','other') NOT NULL,
  period_label     VARCHAR(60)  NOT NULL,
  reference_number VARCHAR(60),
  data_snapshot    JSON,
  total_learners   INT UNSIGNED NOT NULL DEFAULT 0,
  total_teachers   INT UNSIGNED NOT NULL DEFAULT 0,
  total_support_staff INT UNSIGNED NOT NULL DEFAULT 0,
  validation_errors JSON,
  status           ENUM('draft','validated','submitted','accepted','rejected','returned_for_correction') NOT NULL DEFAULT 'draft',
  prepared_by      INT UNSIGNED,
  prepared_at      DATETIME,
  validated_by     INT UNSIGNED,
  validated_at     DATETIME,
  submitted_by     INT UNSIGNED,
  submitted_at     DATETIME,
  submission_file_path VARCHAR(255),
  moes_response    TEXT,
  remarks          VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_emis_return (school_id,academic_year_id,return_type),
  CONSTRAINT fk_emis_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_emis_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_emis_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL
);

CREATE TABLE emis_return_items (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  return_id     INT UNSIGNED NOT NULL,
  metric_code   VARCHAR(60)  NOT NULL,
  metric_label  VARCHAR(160) NOT NULL,
  class_id      INT UNSIGNED,
  dimension     ENUM('total','male','female','day','boarding','teacher','other'),
  value_num     DECIMAL(12,2),
  value_text    VARCHAR(160),
  breakdown     JSON,
  source_note   VARCHAR(255),
  PRIMARY KEY (id),
  KEY idx_emis_item (return_id,metric_code),
  CONSTRAINT fk_emis_item_return FOREIGN KEY (return_id) REFERENCES emis_returns (id) ON DELETE CASCADE,
  CONSTRAINT fk_emis_item_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- Enrolment Returns
-- --------------------------------------------------------------------------
CREATE TABLE enrolment_returns (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  return_date      DATE NOT NULL,
  submission_type  ENUM('termly','annual','ad_hoc') NOT NULL DEFAULT 'termly',
  total_male       INT UNSIGNED NOT NULL DEFAULT 0,
  total_female     INT UNSIGNED NOT NULL DEFAULT 0,
  total_boarding   INT UNSIGNED NOT NULL DEFAULT 0,
  total_day        INT UNSIGNED NOT NULL DEFAULT 0,
  new_entrants     INT UNSIGNED NOT NULL DEFAULT 0,
  with_disability  INT UNSIGNED NOT NULL DEFAULT 0,
  status           ENUM('draft','submitted','accepted','rejected') NOT NULL DEFAULT 'draft',
  prepared_by      INT UNSIGNED,
  submitted_by     INT UNSIGNED,
  submitted_at     DATETIME,
  file_path        VARCHAR(255),
  remarks          VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_enrolment_return (academic_year_id,term_id),
  CONSTRAINT fk_enrol_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_enrol_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_enrol_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL
);

CREATE TABLE enrolment_return_lines (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  return_id       INT UNSIGNED NOT NULL,
  class_id        INT UNSIGNED NOT NULL,
  stream_id       INT UNSIGNED,
  male_count      SMALLINT NOT NULL DEFAULT 0,
  female_count    SMALLINT NOT NULL DEFAULT 0,
  total_count     SMALLINT NOT NULL DEFAULT 0,
  boarding_count  SMALLINT NOT NULL DEFAULT 0,
  day_count       SMALLINT NOT NULL DEFAULT 0,
  new_entrants    SMALLINT NOT NULL DEFAULT 0,
  with_disability SMALLINT NOT NULL DEFAULT 0,
  repeaters       SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enrol_line (return_id,class_id,stream_id),
  CONSTRAINT fk_enrol_line_return FOREIGN KEY (return_id) REFERENCES enrolment_returns (id) ON DELETE CASCADE,
  CONSTRAINT fk_enrol_line_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_enrol_line_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- UPE / USE Capitation Grants
-- --------------------------------------------------------------------------
CREATE TABLE capitation_grants (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  grant_type       ENUM('UPE','USE','other_grant') NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  amount_expected  DECIMAL(14,2) NOT NULL DEFAULT 0,
  amount_received  DECIMAL(14,2) NOT NULL DEFAULT 0,
  beneficiary_count INT UNSIGNED,
  per_learner_rate DECIMAL(10,2),
  received_date    DATE,
  reference_number VARCHAR(80),
  bank_account_id  INT UNSIGNED,
  account_id       INT UNSIGNED,
  allocation_status ENUM('unallocated','partially_allocated','fully_allocated') NOT NULL DEFAULT 'unallocated',
  amount_allocated DECIMAL(14,2) NOT NULL DEFAULT 0,
  amount_spent     DECIMAL(14,2) NOT NULL DEFAULT 0,
  received_by      INT UNSIGNED,
  approved_by      INT UNSIGNED,
  status           ENUM('expected','received','partially_received','reconciled','returned') NOT NULL DEFAULT 'expected',
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_grant_year (academic_year_id,grant_type),
  CONSTRAINT fk_grant_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_grant_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_grant_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL,
  CONSTRAINT fk_grant_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts (id) ON DELETE SET NULL,
  CONSTRAINT fk_grant_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE grant_expenditures (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  grant_id       INT UNSIGNED NOT NULL,
  expense_id     INT UNSIGNED,
  category       ENUM('instructional_materials','textbooks','school_feeds','development','staffing','utilities','other') NOT NULL,
  description    VARCHAR(255) NOT NULL,
  amount         DECIMAL(14,2) NOT NULL,
  spent_date     DATE NOT NULL,
  beneficiary_summary VARCHAR(255),
  receipt_path   VARCHAR(255),
  approved_by    INT UNSIGNED,
  notes          VARCHAR(255),
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_grant_expense (grant_id,category),
  CONSTRAINT fk_grant_exp_grant FOREIGN KEY (grant_id) REFERENCES capitation_grants (id) ON DELETE CASCADE,
  CONSTRAINT fk_grant_exp_expense FOREIGN KEY (expense_id) REFERENCES expenses (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- Inspection Records
-- --------------------------------------------------------------------------
CREATE TABLE inspections (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  inspection_date  DATE NOT NULL,
  inspection_type  ENUM('routine','MoES','district','diocese','UNEPI','accreditation','other') NOT NULL,
  inspector_name   VARCHAR(160),
  inspecting_body  VARCHAR(160),
  focus_areas      VARCHAR(255),
  findings         TEXT,
  commendations    TEXT,
  recommendations  TEXT,
  report_path      VARCHAR(255),
  compliance_level ENUM('excellent','good','satisfactory','unsatisfactory','critical'),
  follow_up_date   DATE,
  actions_taken    TEXT,
  assigned_to      INT UNSIGNED,
  status           ENUM('scheduled','conducted','follow_up','actions_in_progress','closed') NOT NULL DEFAULT 'scheduled',
  closed_at        DATETIME,
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inspection_date (inspection_date,status),
  CONSTRAINT fk_inspection_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Licensing and Registration Records
-- --------------------------------------------------------------------------
CREATE TABLE licences (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id          INT UNSIGNED NOT NULL,
  licence_type       ENUM('registration_certificate','operating_licence','uneb_index','building_permit','food_handler_permit','fire_safety','other') NOT NULL,
  licence_number     VARCHAR(80)  NOT NULL,
  issuing_body       VARCHAR(160),
  issue_date         DATE NOT NULL,
  expiry_date        DATE,
  renewal_interval_months SMALLINT,
  document_path      VARCHAR(255),
  responsible_person INT UNSIGNED,
  reminder_sent      TINYINT(1) NOT NULL DEFAULT 0,
  reminder_date      DATE,
  status             ENUM('valid','expiring_soon','expired','renewed','revoked','suspended') NOT NULL DEFAULT 'valid',
  renewed_from_id    INT UNSIGNED,
  renewal_date       DATE,
  notes              VARCHAR(255),
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_licence_number (school_id,licence_type,licence_number),
  KEY idx_licence_expiry (expiry_date,status),
  CONSTRAINT fk_licence_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_licence_person FOREIGN KEY (responsible_person) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_licence_renewed FOREIGN KEY (renewed_from_id) REFERENCES licences (id) ON DELETE SET NULL
);

