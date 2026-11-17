
USE ssms;
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- Staff Registration / Staff Profiles
-- --------------------------------------------------------------------------
CREATE TABLE staff (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  staff_number     VARCHAR(40)  NOT NULL,
  user_id          INT UNSIGNED,
  first_name       VARCHAR(60)  NOT NULL,
  middle_name      VARCHAR(60),
  last_name        VARCHAR(60)  NOT NULL,
  gender           ENUM('male','female','other'),
  date_of_birth    DATE,
  nin              VARCHAR(40),
  nationality      VARCHAR(60)  NOT NULL DEFAULT 'Ugandan',
  marital_status   ENUM('single','married','divorced','widowed'),
  phone            VARCHAR(40),
  phone_alt        VARCHAR(40),
  email            VARCHAR(120),
  address          VARCHAR(255),
  highest_qualification VARCHAR(140),
  teacher_registration_number VARCHAR(60),
  employment_type  ENUM('teaching','non_teaching') NOT NULL DEFAULT 'teaching',
  employment_status ENUM('active','probation','contract','suspended','resigned','terminated','retired','deceased') NOT NULL DEFAULT 'active',
  position         VARCHAR(120),
  department_id    INT UNSIGNED,
  date_of_appointment DATE,
  date_of_confirmation DATE,
  date_of_leaving  DATE,
  bank_name        VARCHAR(80),
  bank_account_number VARCHAR(40),
  branch_name      VARCHAR(80),
  nssf_number      VARCHAR(40),
  ura_tin          VARCHAR(40),
  photo_path       VARCHAR(255),
  emergency_contact_name  VARCHAR(140),
  emergency_contact_phone VARCHAR(40),
  notes            VARCHAR(255),
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_staff_number (school_id,staff_number),
  KEY idx_staff_department (department_id),
  KEY idx_staff_status (employment_status),
  CONSTRAINT fk_staff_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_staff_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
);

-- Link administration tables that need a staff member ----------------------
ALTER TABLE users
  ADD CONSTRAINT fk_users_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE SET NULL;

ALTER TABLE streams
  ADD COLUMN class_teacher_id INT UNSIGNED AFTER room_name,
  ADD CONSTRAINT fk_streams_class_teacher FOREIGN KEY (class_teacher_id) REFERENCES staff (id) ON DELETE SET NULL;

ALTER TABLE departments
  ADD COLUMN head_staff_id INT UNSIGNED AFTER type,
  ADD CONSTRAINT fk_departments_head FOREIGN KEY (head_staff_id) REFERENCES staff (id) ON DELETE SET NULL;

ALTER TABLE houses
  ADD COLUMN house_master_staff_id INT UNSIGNED AFTER motto,
  ADD CONSTRAINT fk_houses_master FOREIGN KEY (house_master_staff_id) REFERENCES staff (id) ON DELETE SET NULL;

-- --------------------------------------------------------------------------
-- Recruitment : Vacancies
-- --------------------------------------------------------------------------
CREATE TABLE vacancies (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id           INT UNSIGNED NOT NULL,
  title               VARCHAR(140) NOT NULL,
  department_id       INT UNSIGNED,
  description         TEXT,
  slots               SMALLINT NOT NULL DEFAULT 1,
  min_qualification   VARCHAR(140),
  experience_required VARCHAR(80),
  salary_min          DECIMAL(12,2),
  salary_max          DECIMAL(12,2),
  published_date      DATE,
  closing_date        DATE,
  status              ENUM('draft','open','closed','filled','cancelled') NOT NULL DEFAULT 'draft',
  created_by          INT UNSIGNED,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vacancy_status (school_id,status),
  CONSTRAINT fk_vacancy_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_vacancy_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
);

CREATE TABLE job_applications (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vacancy_id        INT UNSIGNED,
  school_id         INT UNSIGNED NOT NULL,
  full_name         VARCHAR(140) NOT NULL,
  gender            ENUM('male','female','other'),
  date_of_birth     DATE,
  phone             VARCHAR(40),
  email             VARCHAR(120),
  address           VARCHAR(255),
  qualification     VARCHAR(160),
  experience_years  TINYINT,
  current_position  VARCHAR(120),
  cv_path           VARCHAR(255),
  cover_letter_path VARCHAR(255),
  applied_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status            ENUM('received','shortlisted','interviewed','appointed','rejected','withdrawn') NOT NULL DEFAULT 'received',
  shortlisted_by    INT UNSIGNED,
  interview_date    DATETIME,
  interview_notes   TEXT,
  decision_notes    VARCHAR(255),
  appointed_staff_id INT UNSIGNED,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_application_status (school_id,status),
  CONSTRAINT fk_application_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_application_vacancy FOREIGN KEY (vacancy_id) REFERENCES vacancies (id) ON DELETE SET NULL,
  CONSTRAINT fk_application_staff FOREIGN KEY (appointed_staff_id) REFERENCES staff (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- Staff Documents
-- --------------------------------------------------------------------------
CREATE TABLE staff_documents (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id    INT UNSIGNED NOT NULL,
  doc_type    ENUM('contract','appointment_letter','academic_certificate','qualification','national_id','passport_photo','reference','other') NOT NULL DEFAULT 'other',
  title       VARCHAR(160) NOT NULL,
  file_path   VARCHAR(255) NOT NULL,
  expiry_date DATE,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_staff_doc (staff_id,doc_type),
  CONSTRAINT fk_staff_doc_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Contract Management
-- --------------------------------------------------------------------------
CREATE TABLE contracts (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id          INT UNSIGNED NOT NULL,
  contract_number   VARCHAR(40),
  contract_type     ENUM('permanent','probation','contract','casual','internship','volunteer') NOT NULL DEFAULT 'contract',
  position          VARCHAR(120),
  start_date        DATE NOT NULL,
  end_date          DATE,
  monthly_salary    DECIMAL(12,2),
  renewal_reminder_date DATE,
  status            ENUM('active','expired','renewed','terminated') NOT NULL DEFAULT 'active',
  document_path     VARCHAR(255),
  notes             VARCHAR(255),
  created_by        INT UNSIGNED,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contract_status (staff_id,status),
  KEY idx_contract_end (end_date),
  CONSTRAINT fk_contract_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Leave Management
-- --------------------------------------------------------------------------
CREATE TABLE leave_types (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  name            VARCHAR(60) NOT NULL,
  code            VARCHAR(20) NOT NULL,
  days_per_year   SMALLINT NOT NULL DEFAULT 0,
  is_paid         TINYINT(1) NOT NULL DEFAULT 1,
  requires_document TINYINT(1) NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leave_type (school_id,code),
  CONSTRAINT fk_leave_type_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE leave_requests (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id      INT UNSIGNED NOT NULL,
  leave_type_id INT UNSIGNED NOT NULL,
  start_date    DATE NOT NULL,
  end_date      DATE NOT NULL,
  days_requested DECIMAL(5,1) NOT NULL,
  reason        VARCHAR(255),
  document_path VARCHAR(255),
  status        ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  balance_at_apply DECIMAL(5,1),
  reviewed_by   INT UNSIGNED,
  reviewed_at   DATETIME,
  review_comments VARCHAR(255),
  cover_arranged_by INT UNSIGNED,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leave_status (status,start_date),
  KEY idx_leave_staff (staff_id,leave_type_id),
  CONSTRAINT fk_leave_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE,
  CONSTRAINT fk_leave_type FOREIGN KEY (leave_type_id) REFERENCES leave_types (id) ON DELETE RESTRICT,
  CONSTRAINT fk_leave_reviewer FOREIGN KEY (reviewed_by) REFERENCES staff (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- Allowances and statutory rules (PAYE / NSSF)
-- Statutory rates are settings the school can update when URA/NSSF change them.
-- --------------------------------------------------------------------------
CREATE TABLE allowances (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  code           VARCHAR(30) NOT NULL,
  name           VARCHAR(80) NOT NULL,
  amount_type    ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed',
  default_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  percentage_rate DECIMAL(6,3),
  is_taxable     TINYINT(1) NOT NULL DEFAULT 1,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_allowance_code (school_id,code),
  CONSTRAINT fk_allowance_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE statutory_rates (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  code           VARCHAR(40) NOT NULL,
  name           VARCHAR(100) NOT NULL,
  rate_type      ENUM('percentage','fixed','band') NOT NULL DEFAULT 'percentage',
  rate_value     DECIMAL(8,3) NOT NULL DEFAULT 0,
  band_min       DECIMAL(12,2),
  band_max       DECIMAL(12,2),
  is_employer    TINYINT(1) NOT NULL DEFAULT 0,
  effective_from DATE NOT NULL,
  effective_to   DATE,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  notes          VARCHAR(255),
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_statutory (school_id,code,effective_from),
  CONSTRAINT fk_statutory_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Payroll Management
-- --------------------------------------------------------------------------
CREATE TABLE payroll_periods (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     INT UNSIGNED NOT NULL,
  period_year   SMALLINT NOT NULL,
  period_month  TINYINT  NOT NULL,
  period_label  VARCHAR(40) NOT NULL,
  start_date    DATE NOT NULL,
  end_date      DATE NOT NULL,
  status        ENUM('draft','processing','approved','paid','closed') NOT NULL DEFAULT 'draft',
  processed_by  INT UNSIGNED,
  processed_at  DATETIME,
  approved_by   INT UNSIGNED,
  approved_at   DATETIME,
  notes         VARCHAR(255),
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payroll_period (school_id,period_year,period_month),
  CONSTRAINT fk_payroll_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE payroll_items (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payroll_period_id INT UNSIGNED NOT NULL,
  staff_id          INT UNSIGNED NOT NULL,
  basic_pay         DECIMAL(12,2) NOT NULL DEFAULT 0,
  allowances_total  DECIMAL(12,2) NOT NULL DEFAULT 0,
  gross_pay         DECIMAL(12,2) NOT NULL DEFAULT 0,
  nssf_employee     DECIMAL(12,2) NOT NULL DEFAULT 0,
  nssf_employer     DECIMAL(12,2) NOT NULL DEFAULT 0,
  paye              DECIMAL(12,2) NOT NULL DEFAULT 0,
  other_deductions  DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_deductions  DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_pay           DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_paid           TINYINT(1) NOT NULL DEFAULT 0,
  paid_at           DATETIME,
  payment_method    ENUM('bank','cash','mobile_money'),
  payslip_path      VARCHAR(255),
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payroll_item (payroll_period_id,staff_id),
  KEY idx_payroll_staff (staff_id),
  CONSTRAINT fk_payroll_item_period FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods (id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_item_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
);

CREATE TABLE payroll_earnings (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payroll_item_id INT UNSIGNED NOT NULL,
  allowance_id    INT UNSIGNED,
  line_type       ENUM('basic','allowance') NOT NULL DEFAULT 'allowance',
  description     VARCHAR(120) NOT NULL,
  amount          DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_earnings_item (payroll_item_id),
  CONSTRAINT fk_earnings_item FOREIGN KEY (payroll_item_id) REFERENCES payroll_items (id) ON DELETE CASCADE,
  CONSTRAINT fk_earnings_allowance FOREIGN KEY (allowance_id) REFERENCES allowances (id) ON DELETE SET NULL
);

CREATE TABLE payroll_deductions (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payroll_item_id INT UNSIGNED NOT NULL,
  deduction_type  ENUM('nssf_employee','nssf_employer','paye','loan','advance','absence','other') NOT NULL,
  description     VARCHAR(120) NOT NULL,
  amount          DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_statutory    TINYINT(1) NOT NULL DEFAULT 0,
  remittance_reference VARCHAR(60),
  remitted_at     DATETIME,
  PRIMARY KEY (id),
  KEY idx_deduction_item (payroll_item_id),
  CONSTRAINT fk_deduction_item FOREIGN KEY (payroll_item_id) REFERENCES payroll_items (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Staff Appraisal
-- --------------------------------------------------------------------------
CREATE TABLE staff_appraisals (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id      INT UNSIGNED NOT NULL,
  period_label  VARCHAR(60) NOT NULL,
  period_start  DATE,
  period_end    DATE,
  criteria      JSON,
  overall_score DECIMAL(5,2),
  comments      TEXT,
  targets       TEXT,
  recommendations VARCHAR(255),
  appraised_by  INT UNSIGNED,
  appraisal_date DATE,
  status        ENUM('draft','completed','acknowledged','disputed') NOT NULL DEFAULT 'draft',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_appraisal_staff (staff_id,period_end),
  CONSTRAINT fk_appraisal_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE,
  CONSTRAINT fk_appraisal_reviewer FOREIGN KEY (appraised_by) REFERENCES staff (id) ON DELETE SET NULL
);

-- --------------------------------------------------------------------------
-- Staff Termination
-- --------------------------------------------------------------------------
CREATE TABLE staff_terminations (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id           INT UNSIGNED NOT NULL,
  termination_type   ENUM('resignation','retirement','dismissal','end_of_contract','death') NOT NULL,
  effective_date     DATE NOT NULL,
  notice_period_days SMALLINT,
  reason             VARCHAR(255),
  final_dues         DECIMAL(12,2),
  dues_paid          TINYINT(1) NOT NULL DEFAULT 0,
  is_cleared         TINYINT(1) NOT NULL DEFAULT 0,
  decided_by         INT UNSIGNED,
  decided_at         DATETIME,
  document_path      VARCHAR(255),
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_termination_staff (staff_id),
  CONSTRAINT fk_termination_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE,
  CONSTRAINT fk_termination_by FOREIGN KEY (decided_by) REFERENCES staff (id) ON DELETE SET NULL
);

