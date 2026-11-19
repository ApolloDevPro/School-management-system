SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS ssms;

USE ssms;

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

CREATE TABLE admission_applications (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id          INT UNSIGNED NOT NULL,
  academic_year_id   INT UNSIGNED NOT NULL,
  term_id            INT UNSIGNED,
  application_number VARCHAR(40),
  first_name         VARCHAR(60) NOT NULL,
  middle_name        VARCHAR(60),
  last_name          VARCHAR(60) NOT NULL,
  gender             ENUM('male','female') NOT NULL,
  date_of_birth      DATE,
  previous_school    VARCHAR(160),
  previous_result    VARCHAR(120),
  applied_class_id   INT UNSIGNED,
  guardian_name      VARCHAR(140),
  guardian_phone     VARCHAR(40),
  guardian_email     VARCHAR(120),
  source             ENUM('walk_in','website','transfer','recommendation','other') DEFAULT 'walk_in',
  status             ENUM('pending','admitted','rejected','waitlisted','withdrawn') NOT NULL DEFAULT 'pending',
  decided_by         INT UNSIGNED,
  decided_at         DATETIME,
  decision_notes     VARCHAR(255),
  admission_number   VARCHAR(40),
  admission_letter_path VARCHAR(255),
  student_id         INT UNSIGNED,
  notes              TEXT,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_application_number (school_id,application_number),
  KEY idx_application_status (school_id,academic_year_id,status),
  CONSTRAINT fk_admission_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_admission_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
  CONSTRAINT fk_admission_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL,
  CONSTRAINT fk_admission_class FOREIGN KEY (applied_class_id) REFERENCES classes (id) ON DELETE SET NULL
);

CREATE TABLE students (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id           INT UNSIGNED NOT NULL,
  admission_number    VARCHAR(40)  NOT NULL,
  registration_number VARCHAR(40),
  uneb_lin            VARCHAR(40),
  nin                 VARCHAR(40),
  user_id             INT UNSIGNED,
  first_name          VARCHAR(60)  NOT NULL,
  middle_name         VARCHAR(60),
  last_name           VARCHAR(60)  NOT NULL,
  gender              ENUM('male','female') NOT NULL,
  date_of_birth       DATE,
  place_of_birth      VARCHAR(120),
  nationality         VARCHAR(60)  NOT NULL DEFAULT 'Ugandan',
  religion            VARCHAR(60),
  next_of_kin         VARCHAR(140),
  previous_school     VARCHAR(160),
  previous_result     VARCHAR(120),
  special_needs       TINYINT(1)   NOT NULL DEFAULT 0,
  special_needs_notes VARCHAR(255),
  photo_path          VARCHAR(255),
  student_type        ENUM('day','boarding') NOT NULL DEFAULT 'day',
  house_id            INT UNSIGNED,
  current_class_id    INT UNSIGNED,
  current_stream_id   INT UNSIGNED,
  admission_date      DATE,
  academic_year_id    INT UNSIGNED,
  status              ENUM('applicant','active','completed','transferred_out','withdrawn','suspended','graduated') NOT NULL DEFAULT 'active',
  status_reason       VARCHAR(255),
  leaving_date        DATE,
  created_by          INT UNSIGNED,
  created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_admission (school_id,admission_number),
  KEY idx_student_class (current_class_id),
  KEY idx_student_stream (current_stream_id),
  KEY idx_student_status (school_id,status),
  KEY idx_student_name (last_name,first_name),
  CONSTRAINT fk_student_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_student_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_student_class FOREIGN KEY (current_class_id) REFERENCES classes (id) ON DELETE SET NULL,
  CONSTRAINT fk_student_stream FOREIGN KEY (current_stream_id) REFERENCES streams (id) ON DELETE SET NULL,
  CONSTRAINT fk_student_house FOREIGN KEY (house_id) REFERENCES houses (id) ON DELETE SET NULL,
  CONSTRAINT fk_student_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
);

CREATE TABLE guardians (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED,
  first_name   VARCHAR(60)  NOT NULL,
  last_name    VARCHAR(60)  NOT NULL,
  full_name    VARCHAR(140),
  relationship ENUM('father','mother','guardian','sponsor','relative','other') NOT NULL DEFAULT 'guardian',
  phone        VARCHAR(40),
  phone_alt    VARCHAR(40),
  email        VARCHAR(120),
  occupation   VARCHAR(120),
  address      VARCHAR(255),
  district     VARCHAR(80),
  nin          VARCHAR(40),
  is_primary   TINYINT(1)   NOT NULL DEFAULT 0,
  receive_sms  TINYINT(1)   NOT NULL DEFAULT 1,
  receive_email TINYINT(1)  NOT NULL DEFAULT 1,
  receive_portal_notifications TINYINT(1) NOT NULL DEFAULT 1,
  notes        VARCHAR(255),
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_guardian_phone (phone),
  KEY idx_guardian_name (last_name),
  CONSTRAINT fk_guardian_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_guardian_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE student_guardians (
  student_id    INT UNSIGNED NOT NULL,
  guardian_id   INT UNSIGNED NOT NULL,
  relationship  ENUM('father','mother','guardian','sponsor','relative','other') NOT NULL DEFAULT 'guardian',
  is_primary    TINYINT(1) NOT NULL DEFAULT 0,
  lives_with    TINYINT(1) NOT NULL DEFAULT 0,
  is_fee_payer  TINYINT(1) NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id,guardian_id),
  KEY idx_sg_guardian (guardian_id),
  CONSTRAINT fk_sg_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_sg_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE CASCADE
);

CREATE TABLE student_class_history (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  action           ENUM('admission','transfer_in','promotion','repetition','demotion','reallocation','completion') NOT NULL DEFAULT 'admission',
  is_current       TINYINT(1) NOT NULL DEFAULT 1,
  effective_date   DATE NOT NULL,
  notes            VARCHAR(255),
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sch_student (student_id,is_current),
  KEY idx_sch_class (class_id,academic_year_id),
  CONSTRAINT fk_sch_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_sch_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sch_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE SET NULL,
  CONSTRAINT fk_sch_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sch_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL
);

CREATE TABLE promotion_batches (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  name             VARCHAR(120) NOT NULL,
  from_level       ENUM('S1','S2','S3','S4','S5') NOT NULL,
  promotion_rules  TEXT,
  total_processed  SMALLINT NOT NULL DEFAULT 0,
  status           ENUM('draft','processing','completed','reversed') NOT NULL DEFAULT 'draft',
  processed_by     INT UNSIGNED,
  processed_at     DATETIME,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_promo_batch (school_id,academic_year_id),
  CONSTRAINT fk_promo_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_promo_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE
);

CREATE TABLE promotion_records (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id          INT UNSIGNED,
  student_id        INT UNSIGNED NOT NULL,
  academic_year_id  INT UNSIGNED NOT NULL,
  from_class_id     INT UNSIGNED NOT NULL,
  to_class_id       INT UNSIGNED,
  from_stream_id    INT UNSIGNED,
  to_stream_id      INT UNSIGNED,
  decision          ENUM('promoted','repeated','completed','deferred','discontinued') NOT NULL,
  reason            VARCHAR(255),
  criteria_used     VARCHAR(255),
  decided_by        INT UNSIGNED,
  decided_date      DATE NOT NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_promotion_student (student_id,academic_year_id),
  KEY idx_promotion_batch (batch_id),
  CONSTRAINT fk_promotion_batch FOREIGN KEY (batch_id) REFERENCES promotion_batches (id) ON DELETE SET NULL,
  CONSTRAINT fk_promotion_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_promotion_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
  CONSTRAINT fk_promotion_from_class FOREIGN KEY (from_class_id) REFERENCES classes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_promotion_to_class FOREIGN KEY (to_class_id) REFERENCES classes (id) ON DELETE SET NULL,
  CONSTRAINT fk_promotion_from_stream FOREIGN KEY (from_stream_id) REFERENCES streams (id) ON DELETE SET NULL,
  CONSTRAINT fk_promotion_to_stream FOREIGN KEY (to_stream_id) REFERENCES streams (id) ON DELETE SET NULL
);

CREATE TABLE transfers (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id         INT UNSIGNED NOT NULL,
  direction          ENUM('in','out') NOT NULL,
  other_school_name  VARCHAR(160) NOT NULL,
  reason             VARCHAR(255),
  transfer_date      DATE NOT NULL,
  previous_class     VARCHAR(40),
  previous_result    VARCHAR(120),
  receiving_class_id INT UNSIGNED,
  letter_path        VARCHAR(255),
  academic_record_path VARCHAR(255),
  documents_received TINYINT(1) NOT NULL DEFAULT 0,
  approved_by        INT UNSIGNED,
  approved_at        DATETIME,
  notes              VARCHAR(255),
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_transfer_student (student_id,direction),
  CONSTRAINT fk_transfer_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_transfer_class FOREIGN KEY (receiving_class_id) REFERENCES classes (id) ON DELETE SET NULL
);

CREATE TABLE discipline_incidents (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  incident_date    DATE NOT NULL,
  incident_time    TIME,
  category         ENUM('late_coming','truancy','disrespect','fighting','theft','vandalism','cheating','substance','uniform','bullying','other') NOT NULL,
  location         VARCHAR(100),
  description      TEXT NOT NULL,
  witness_staff_id INT UNSIGNED,
  reported_by      INT UNSIGNED,
  action_taken     VARCHAR(255),
  sanction         ENUM('none','warning','counselling','community_service','suspension','expulsion','parent_meeting') NOT NULL DEFAULT 'warning',
  sanction_start   DATE,
  sanction_end     DATE,
  parent_notified  TINYINT(1) NOT NULL DEFAULT 0,
  parent_notified_at DATETIME,
  status           ENUM('open','under_review','follow_up','closed') NOT NULL DEFAULT 'open',
  follow_up_date   DATE,
  follow_up_notes  TEXT,
  closed_by        INT UNSIGNED,
  closed_at        DATETIME,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_discipline_student (student_id,incident_date),
  KEY idx_discipline_status (status),
  CONSTRAINT fk_discipline_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE student_documents (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id  INT UNSIGNED NOT NULL,
  doc_type    ENUM('admission_letter','birth_certificate','previous_report','ple_slip','uce_slip','uace_slip','nin','passport_photo','medical_form','transfer_letter','other') NOT NULL DEFAULT 'other',
  title       VARCHAR(160) NOT NULL,
  file_path   VARCHAR(255) NOT NULL,
  file_size   INT UNSIGNED,
  expiry_date DATE,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  verified_by INT UNSIGNED,
  uploaded_by INT UNSIGNED,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_student_doc (student_id,doc_type),
  CONSTRAINT fk_student_doc FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE withdrawals (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id        INT UNSIGNED NOT NULL,
  withdrawal_date   DATE NOT NULL,
  reason            ENUM('personal','academic','financial','health','relocation','misconduct','other') NOT NULL,
  reason_details    VARCHAR(255),
  requested_by      VARCHAR(140),
  approved_by       INT UNSIGNED,
  approved_at       DATETIME,
  outstanding_fees  DECIMAL(12,2),
  documents_issued  VARCHAR(255),
  remarks           VARCHAR(255),
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_withdrawal (student_id,withdrawal_date),
  CONSTRAINT fk_withdrawal_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE alumni (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id         INT UNSIGNED NOT NULL,
  completion_year    SMALLINT NOT NULL,
  level_completed    ENUM('S4','S6') NOT NULL,
  current_name       VARCHAR(140),
  phone              VARCHAR(40),
  email              VARCHAR(120),
  current_address    VARCHAR(255),
  occupation         VARCHAR(120),
  further_education  VARCHAR(160),
  achievements       TEXT,
  testimonial_issued_at DATETIME,
  is_contactable     TINYINT(1) NOT NULL DEFAULT 1,
  notes              VARCHAR(255),
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alumni_student (student_id),
  KEY idx_alumni_year (completion_year),
  CONSTRAINT fk_alumni_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE curricula (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  code        VARCHAR(30)  NOT NULL,
  level_scope ENUM('O-Level','A-Level','Both') NOT NULL,
  description TEXT,
  status      ENUM('active','archived','draft') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_curriculum (school_id,code),
  CONSTRAINT fk_curriculum_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE subjects (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id         INT UNSIGNED NOT NULL,
  code              VARCHAR(20)  NOT NULL,
  name              VARCHAR(120) NOT NULL,
  short_name        VARCHAR(30),
  level             ENUM('O-Level','A-Level','Both') NOT NULL DEFAULT 'O-Level',
  category          ENUM('compulsory','elective','principal','subsidiary') NOT NULL DEFAULT 'compulsory',
  department_id     INT UNSIGNED,
  uneb_subject_code VARCHAR(20),
  credit_points     DECIMAL(4,1),
  is_composite      TINYINT(1)   NOT NULL DEFAULT 0,
  order_index       SMALLINT     NOT NULL DEFAULT 0,
  is_active         TINYINT(1)   NOT NULL DEFAULT 1,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subject_code (school_id,code),
  KEY idx_subject_level (level,category),
  CONSTRAINT fk_subject_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_subject_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
);

CREATE TABLE subject_allocations (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  is_compulsory    TINYINT(1) NOT NULL DEFAULT 1,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subject_allocation (subject_id,class_id,stream_id,academic_year_id,term_id),
  KEY idx_alloc_class (class_id,academic_year_id),
  CONSTRAINT fk_alloc_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_alloc_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_alloc_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
  CONSTRAINT fk_alloc_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_alloc_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE alevel_combinations (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    INT UNSIGNED NOT NULL,
  code         VARCHAR(20)  NOT NULL,
  name         VARCHAR(120) NOT NULL,
  description  VARCHAR(255),
  department_id INT UNSIGNED,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  open_from_year SMALLINT,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_combination (school_id,code),
  CONSTRAINT fk_combination_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_combination_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
);

CREATE TABLE combination_subjects (
  combination_id INT UNSIGNED NOT NULL,
  subject_id     INT UNSIGNED NOT NULL,
  subject_role   ENUM('principal','subsidiary') NOT NULL DEFAULT 'principal',
  is_compulsory  TINYINT(1) NOT NULL DEFAULT 1,
  order_index    SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (combination_id,subject_id),
  CONSTRAINT fk_cs_combination FOREIGN KEY (combination_id) REFERENCES alevel_combinations (id) ON DELETE CASCADE,
  CONSTRAINT fk_cs_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

CREATE TABLE student_subjects (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  is_compulsory    TINYINT(1) NOT NULL DEFAULT 1,
  status           ENUM('registered','dropped','completed') NOT NULL DEFAULT 'registered',
  registered_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dropped_at       DATETIME,
  dropped_reason   VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_subject (student_id,subject_id,academic_year_id,term_id),
  KEY idx_ss_subject (subject_id,academic_year_id),
  CONSTRAINT fk_ss_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_ss_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_ss_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_ss_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE student_combinations (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  combination_id   INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  status           ENUM('active','changed','completed') NOT NULL DEFAULT 'active',
  allocated_by     INT UNSIGNED,
  allocated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes            VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_combination (student_id,academic_year_id),
  KEY idx_scombo_combination (combination_id),
  CONSTRAINT fk_scombo_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_scombo_combination FOREIGN KEY (combination_id) REFERENCES alevel_combinations (id) ON DELETE RESTRICT,
  CONSTRAINT fk_scombo_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT
);

CREATE TABLE teacher_allocations (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id         INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  lessons_per_week SMALLINT NOT NULL DEFAULT 1,
  is_primary       TINYINT(1) NOT NULL DEFAULT 1,
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_teacher_allocation (staff_id,subject_id,class_id,stream_id,academic_year_id,term_id),
  KEY idx_ta_subject (subject_id,academic_year_id),
  CONSTRAINT fk_ta_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE rooms (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id  INT UNSIGNED NOT NULL,
  name       VARCHAR(60)  NOT NULL,
  room_type  ENUM('classroom','laboratory','hall','library','exam_room','other') NOT NULL DEFAULT 'classroom',
  capacity   SMALLINT     NOT NULL DEFAULT 40,
  building   VARCHAR(60),
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_room_name (school_id,name),
  CONSTRAINT fk_room_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE timetables (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  name             VARCHAR(120) NOT NULL,
  scope            ENUM('class','teacher') NOT NULL DEFAULT 'class',
  class_id         INT UNSIGNED,
  stream_id        INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED NOT NULL,
  status           ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  version          SMALLINT NOT NULL DEFAULT 1,
  published_at     DATETIME,
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_timetable_scope (class_id,stream_id,academic_year_id),
  CONSTRAINT fk_timetable_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_timetable_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_timetable_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
  CONSTRAINT fk_timetable_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_timetable_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE timetable_slots (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  timetable_id INT UNSIGNED NOT NULL,
  day_of_week  ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  period_number TINYINT NOT NULL,
  start_time   TIME NOT NULL,
  end_time     TIME NOT NULL,
  subject_id   INT UNSIGNED NOT NULL,
  staff_id     INT UNSIGNED NOT NULL,
  class_id     INT UNSIGNED NOT NULL,
  stream_id    INT UNSIGNED,
  room_id      INT UNSIGNED,
  lesson_type  ENUM('lesson','break','assembly','games','registration') NOT NULL DEFAULT 'lesson',
  notes        VARCHAR(160),
  PRIMARY KEY (id),
  UNIQUE KEY uq_slot_teacher (timetable_id,day_of_week,period_number,staff_id),
  UNIQUE KEY uq_slot_stream (timetable_id,day_of_week,period_number,stream_id),
  KEY idx_slot_subject (subject_id),
  CONSTRAINT fk_slot_timetable FOREIGN KEY (timetable_id) REFERENCES timetables (id) ON DELETE CASCADE,
  CONSTRAINT fk_slot_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE RESTRICT,
  CONSTRAINT fk_slot_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE RESTRICT,
  CONSTRAINT fk_slot_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_slot_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
  CONSTRAINT fk_slot_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE SET NULL
);

CREATE TABLE schemes_of_work (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id         INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED NOT NULL,
  week_number      TINYINT NOT NULL,
  week_starting    DATE,
  topic            VARCHAR(180) NOT NULL,
  sub_topics       TEXT,
  objectives       TEXT,
  learning_experiences TEXT,
  teaching_methods VARCHAR(255),
  learning_materials VARCHAR(255),
  assessment       TEXT,
  remarks          VARCHAR(255),
  status           ENUM('draft','submitted','approved','returned') NOT NULL DEFAULT 'draft',
  submitted_at     DATETIME,
  reviewed_by      INT UNSIGNED,
  reviewed_at      DATETIME,
  review_comments  VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_scheme (staff_id,subject_id,class_id,term_id,week_number),
  KEY idx_scheme_review (status,reviewed_by),
  CONSTRAINT fk_scheme_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE,
  CONSTRAINT fk_scheme_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_scheme_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_scheme_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
  CONSTRAINT fk_scheme_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_scheme_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE lesson_plans (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id         INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED NOT NULL,
  lesson_date      DATE NOT NULL,
  lesson_number    SMALLINT,
  topic            VARCHAR(180) NOT NULL,
  objectives       TEXT,
  key_concepts     TEXT,
  learning_activities TEXT,
  resources        VARCHAR(255),
  assessment_method VARCHAR(255),
  reflection       TEXT,
  status           ENUM('draft','submitted','approved','returned') NOT NULL DEFAULT 'draft',
  submitted_at     DATETIME,
  reviewed_by      INT UNSIGNED,
  reviewed_at      DATETIME,
  review_comments  VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lesson_plan (staff_id,subject_id,class_id,lesson_date),
  KEY idx_lesson_status (status,lesson_date),
  CONSTRAINT fk_lesson_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE,
  CONSTRAINT fk_lesson_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_lesson_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_lesson_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
  CONSTRAINT fk_lesson_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_lesson_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE syllabuses (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id    INT UNSIGNED NOT NULL,
  curriculum_id INT UNSIGNED,
  title         VARCHAR(180) NOT NULL,
  level         ENUM('O-Level','A-Level') NOT NULL,
  version       VARCHAR(30),
  file_path     VARCHAR(255),
  is_current    TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_syllabus_subject (subject_id,level),
  CONSTRAINT fk_syllabus_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_syllabus_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula (id) ON DELETE SET NULL
);

CREATE TABLE syllabus_topics (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  syllabus_id     INT UNSIGNED NOT NULL,
  parent_id       INT UNSIGNED,
  order_index     SMALLINT NOT NULL DEFAULT 0,
  code            VARCHAR(20),
  title           VARCHAR(180) NOT NULL,
  sub_topics      TEXT,
  learning_outcomes TEXT,
  suggested_lessons SMALLINT,
  is_project_topic TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_topic_syllabus (syllabus_id,order_index),
  CONSTRAINT fk_topic_syllabus FOREIGN KEY (syllabus_id) REFERENCES syllabuses (id) ON DELETE CASCADE,
  CONSTRAINT fk_topic_parent FOREIGN KEY (parent_id) REFERENCES syllabus_topics (id) ON DELETE CASCADE
);

CREATE TABLE syllabus_coverage (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id         INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED NOT NULL,
  taught_date      DATE NOT NULL,
  taught_by        INT UNSIGNED,
  lessons_taken    SMALLINT NOT NULL DEFAULT 1,
  coverage_percent DECIMAL(5,2),
  remarks          VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coverage (topic_id,class_id,stream_id,term_id),
  KEY idx_coverage_class (class_id,term_id),
  CONSTRAINT fk_coverage_topic FOREIGN KEY (topic_id) REFERENCES syllabus_topics (id) ON DELETE CASCADE,
  CONSTRAINT fk_coverage_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_coverage_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
  CONSTRAINT fk_coverage_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_coverage_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE generic_skills (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  code        VARCHAR(30)  NOT NULL,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255),
  level_scope ENUM('O-Level','A-Level','Both') NOT NULL DEFAULT 'O-Level',
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_skill (school_id,code),
  CONSTRAINT fk_skill_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE integrated_activities (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED NOT NULL,
  title            VARCHAR(180) NOT NULL,
  activity_type    ENUM('project','practical','integrated_activity','field_work','presentation') NOT NULL,
  description      TEXT,
  instructions     TEXT,
  max_score        TINYINT NOT NULL DEFAULT 3,
  start_date       DATE,
  due_date         DATE,
  status           ENUM('planned','assigned','in_progress','submitted','marked') NOT NULL DEFAULT 'planned',
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_class (class_id,term_id),
  CONSTRAINT fk_activity_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE activity_skills (
  activity_id INT UNSIGNED NOT NULL,
  skill_id    INT UNSIGNED NOT NULL,
  PRIMARY KEY (activity_id,skill_id),
  CONSTRAINT fk_as_activity FOREIGN KEY (activity_id) REFERENCES integrated_activities (id) ON DELETE CASCADE,
  CONSTRAINT fk_as_skill FOREIGN KEY (skill_id) REFERENCES generic_skills (id) ON DELETE CASCADE
);

CREATE TABLE homework (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id         INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED NOT NULL,
  title            VARCHAR(180) NOT NULL,
  description      TEXT,
  attachment_path  VARCHAR(255),
  assigned_date    DATE NOT NULL,
  due_date         DATE NOT NULL,
  total_marks      DECIMAL(6,2),
  status           ENUM('draft','published','closed','cancelled') NOT NULL DEFAULT 'draft',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_homework_class (class_id,due_date),
  CONSTRAINT fk_homework_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE,
  CONSTRAINT fk_homework_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_homework_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_homework_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
  CONSTRAINT fk_homework_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_homework_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE homework_submissions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  homework_id   INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  submitted_at  DATETIME,
  attachment_path VARCHAR(255),
  marks         DECIMAL(6,2),
  feedback      VARCHAR(255),
  status        ENUM('pending','submitted','late','marked','returned') NOT NULL DEFAULT 'pending',
  marked_by     INT UNSIGNED,
  marked_at     DATETIME,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homework_submission (homework_id,student_id),
  KEY idx_submission_student (student_id),
  CONSTRAINT fk_submission_homework FOREIGN KEY (homework_id) REFERENCES homework (id) ON DELETE CASCADE,
  CONSTRAINT fk_submission_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE academic_events (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED,
  term_id          INT UNSIGNED,
  title            VARCHAR(160) NOT NULL,
  event_type       ENUM('term_start','term_end','examination','holiday','event','deadline','meeting','sports','visiting_day') NOT NULL DEFAULT 'event',
  start_date       DATE NOT NULL,
  end_date         DATE,
  start_time       TIME,
  end_time         TIME,
  venue            VARCHAR(120),
  description      TEXT,
  audience         ENUM('all','staff','students','parents','class') NOT NULL DEFAULT 'all',
  reminders_sent   TINYINT(1) NOT NULL DEFAULT 0,
  is_published     TINYINT(1) NOT NULL DEFAULT 1,
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_event_date (start_date),
  CONSTRAINT fk_event_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_event_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_event_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE exams (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED NOT NULL,
  name             VARCHAR(120) NOT NULL,
  exam_type        ENUM('midterm','end_of_term','mock','entrance','remedial','assignment','final') NOT NULL,
  level_scope      ENUM('O-Level','A-Level','Both') NOT NULL DEFAULT 'Both',
  start_date       DATE,
  end_date         DATE,
  max_mark         SMALLINT NOT NULL DEFAULT 100,
  status           ENUM('planning','scheduled','in_progress','marks_entry','results_ready','published','cancelled') NOT NULL DEFAULT 'planning',
  published_at     DATETIME,
  created_by       INT UNSIGNED,
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_exam_term (academic_year_id,term_id,exam_type),
  CONSTRAINT fk_exam_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_exam_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_exam_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE exam_schedule (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id     INT UNSIGNED NOT NULL,
  subject_id  INT UNSIGNED NOT NULL,
  class_id    INT UNSIGNED NOT NULL,
  stream_id   INT UNSIGNED,
  paper_date  DATE NOT NULL,
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  room_id     INT UNSIGNED,
  max_mark    SMALLINT NOT NULL DEFAULT 100,
  syllabus_scope VARCHAR(255),
  status      ENUM('scheduled','ongoing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  notes       VARCHAR(255),
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_exam_paper (exam_id,subject_id,class_id,stream_id),
  KEY idx_schedule_date (paper_date),
  CONSTRAINT fk_schedule_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
  CONSTRAINT fk_schedule_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_schedule_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_schedule_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
  CONSTRAINT fk_schedule_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE SET NULL
);

CREATE TABLE exam_invigilators (
  schedule_id INT UNSIGNED NOT NULL,
  staff_id    INT UNSIGNED NOT NULL,
  duty_role   ENUM('invigilator','supervisor','timekeeper','security') NOT NULL DEFAULT 'invigilator',
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (schedule_id,staff_id,duty_role),
  CONSTRAINT fk_invig_schedule FOREIGN KEY (schedule_id) REFERENCES exam_schedule (id) ON DELETE CASCADE,
  CONSTRAINT fk_invig_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
);

CREATE TABLE assessment_components (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  level_scope    ENUM('O-Level','A-Level') NOT NULL,
  component_code ENUM('school_ca','uneb_exam','project_work','midterm','end_of_term','class_work','assignment') NOT NULL,
  name           VARCHAR(120) NOT NULL,
  weight_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  scale_min      DECIMAL(5,2) NOT NULL DEFAULT 0,
  scale_max      DECIMAL(5,2) NOT NULL DEFAULT 100,
  applies_to     ENUM('internal_reports','official_results','both') NOT NULL DEFAULT 'both',
  effective_from DATE,
  effective_to   DATE,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  notes          VARCHAR(255),
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_component (school_id,level_scope,component_code,applies_to),
  CONSTRAINT fk_component_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE ca_records (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED NOT NULL,
  activity_id      INT UNSIGNED,
  assessment_title VARCHAR(180) NOT NULL,
  activity_type    ENUM('activity','project','integrated_task','field_work','presentation','assessment') NOT NULL DEFAULT 'activity',
  score            DECIMAL(5,2) NOT NULL,
  scale_min        DECIMAL(5,2) NOT NULL DEFAULT 1,
  scale_max        DECIMAL(5,2) NOT NULL DEFAULT 3,
  competencies     TEXT,
  remarks          VARCHAR(255),
  recorded_by      INT UNSIGNED,
  recorded_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ca_record (student_id,subject_id,term_id,assessment_title),
  KEY idx_ca_subject (subject_id,term_id),
  KEY idx_ca_class (class_id,term_id),
  CONSTRAINT fk_ca_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_ca_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_ca_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_ca_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_ca_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE SET NULL,
  CONSTRAINT fk_ca_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_ca_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
  CONSTRAINT fk_ca_activity FOREIGN KEY (activity_id) REFERENCES integrated_activities (id) ON DELETE SET NULL
);

CREATE TABLE project_work_records (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  cycle_year       TINYINT NOT NULL DEFAULT 1,
  project_title    VARCHAR(180),
  description      TEXT,
  score            DECIMAL(5,2),
  scale_min        DECIMAL(5,2) NOT NULL DEFAULT 1,
  scale_max        DECIMAL(5,2) NOT NULL DEFAULT 3,
  competencies     TEXT,
  evidence_path    VARCHAR(255),
  assessed_by      INT UNSIGNED,
  assessed_date    DATE,
  status           ENUM('pending','submitted','assessed','missing') NOT NULL DEFAULT 'pending',
  remarks          VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_project_work (student_id,subject_id,academic_year_id),
  KEY idx_project_status (status,academic_year_id),
  CONSTRAINT fk_project_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_project_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_project_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_project_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE
);

CREATE TABLE ca_submissions (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  cycle            ENUM('UCE','UACE') NOT NULL DEFAULT 'UCE',
  submission_type  ENUM('continuous_assessment','candidate_registration','project_work') NOT NULL,
  reference_number VARCHAR(60),
  file_path        VARCHAR(255),
  record_count     INT UNSIGNED NOT NULL DEFAULT 0,
  missing_count    INT UNSIGNED NOT NULL DEFAULT 0,
  status           ENUM('draft','validated','ready','submitted','accepted','rejected') NOT NULL DEFAULT 'draft',
  validated_by     INT UNSIGNED,
  validated_at     DATETIME,
  submitted_by     INT UNSIGNED,
  submitted_at     DATETIME,
  uneb_response    TEXT,
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ca_submission (school_id,academic_year_id,submission_type),
  CONSTRAINT fk_ca_sub_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_ca_sub_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE
);

CREATE TABLE ca_submission_items (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  subject_id    INT UNSIGNED NOT NULL,
  accumulated_score DECIMAL(6,2),
  project_work_score DECIMAL(5,2),
  status        ENUM('ready','missing_score','missing_project','not_registered','submitted','rejected') NOT NULL DEFAULT 'ready',
  issue_note    VARCHAR(255),
  uneb_grade    VARCHAR(10),
  PRIMARY KEY (id),
  UNIQUE KEY uq_submission_item (submission_id,student_id,subject_id),
  KEY idx_submission_item_student (student_id),
  CONSTRAINT fk_csi_submission FOREIGN KEY (submission_id) REFERENCES ca_submissions (id) ON DELETE CASCADE,
  CONSTRAINT fk_csi_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_csi_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

CREATE TABLE marks (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id          INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  teacher_id       INT UNSIGNED,
  ca_mark          DECIMAL(6,2),
  exam_mark        DECIMAL(6,2),
  project_mark     DECIMAL(6,2),
  max_ca_mark      DECIMAL(6,2),
  max_exam_mark    DECIMAL(6,2) NOT NULL DEFAULT 100,
  total_mark       DECIMAL(6,2),
  percentage       DECIMAL(6,2),
  source           ENUM('manual','excel','ca_average','import') NOT NULL DEFAULT 'manual',
  status           ENUM('draft','submitted','verified','locked','reopened') NOT NULL DEFAULT 'draft',
  remarks          VARCHAR(255),
  entered_by       INT UNSIGNED,
  entered_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verified_by      INT UNSIGNED,
  verified_at      DATETIME,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mark (exam_id,student_id,subject_id),
  KEY idx_mark_subject (subject_id,status),
  KEY idx_mark_student (student_id),
  CONSTRAINT fk_mark_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
  CONSTRAINT fk_mark_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_mark_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_mark_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_mark_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE SET NULL,
  CONSTRAINT fk_mark_teacher FOREIGN KEY (teacher_id) REFERENCES staff (id) ON DELETE SET NULL
);

CREATE TABLE marks_verification_log (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mark_id      INT UNSIGNED NOT NULL,
  action       ENUM('submit','verify','lock','unlock','correct','bulk_upload') NOT NULL,
  old_value    JSON,
  new_value    JSON,
  reason       VARCHAR(255),
  actor_id     INT UNSIGNED,
  acted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address   VARCHAR(45),
  PRIMARY KEY (id),
  KEY idx_mvl_mark (mark_id),
  CONSTRAINT fk_mvl_mark FOREIGN KEY (mark_id) REFERENCES marks (id) ON DELETE CASCADE
);

CREATE TABLE grading_scales (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    INT UNSIGNED NOT NULL,
  code         VARCHAR(30)  NOT NULL,
  name         VARCHAR(120) NOT NULL,
  level_scope  ENUM('O-Level','A-Level','Legacy','Internal') NOT NULL,
  uses_division TINYINT(1) NOT NULL DEFAULT 0,
  uses_points  TINYINT(1) NOT NULL DEFAULT 0,
  description  VARCHAR(255),
  is_default   TINYINT(1) NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_scale (school_id,code),
  CONSTRAINT fk_scale_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE grade_bands (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  grading_scale_id INT UNSIGNED NOT NULL,
  band_order    SMALLINT NOT NULL DEFAULT 0,
  min_percent   DECIMAL(6,2) NOT NULL,
  max_percent   DECIMAL(6,2) NOT NULL,
  grade_code    VARCHAR(10) NOT NULL,
  grade_name    VARCHAR(60) NOT NULL,
  descriptor    VARCHAR(255),
  points        DECIMAL(5,2),
  division      VARCHAR(10),
  remarks       VARCHAR(160),
  is_pass       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_band (grading_scale_id,grade_code),
  CONSTRAINT fk_band_scale FOREIGN KEY (grading_scale_id) REFERENCES grading_scales (id) ON DELETE CASCADE
);

CREATE TABLE subject_results (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id          INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  ca_percent       DECIMAL(6,2),
  exam_percent     DECIMAL(6,2),
  project_percent  DECIMAL(6,2),
  weighted_percent DECIMAL(6,2),
  grading_scale_id INT UNSIGNED,
  grade_code       VARCHAR(10),
  grade_name       VARCHAR(60),
  points           DECIMAL(5,2),
  is_provisional   TINYINT(1) NOT NULL DEFAULT 1,
  computed_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subject_result (exam_id,student_id,subject_id),
  KEY idx_result_subject (subject_id,class_id),
  CONSTRAINT fk_result_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
  CONSTRAINT fk_result_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_result_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_result_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_result_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE SET NULL,
  CONSTRAINT fk_result_scale FOREIGN KEY (grading_scale_id) REFERENCES grading_scales (id) ON DELETE SET NULL
);

CREATE TABLE result_summaries (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id          INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  subjects_taken   SMALLINT NOT NULL DEFAULT 0,
  subjects_passed  SMALLINT NOT NULL DEFAULT 0,
  average_percent  DECIMAL(6,2),
  total_points     DECIMAL(8,2),
  aggregate        DECIMAL(8,2),
  division         VARCHAR(10),
  best_level       VARCHAR(10),
  worst_level      VARCHAR(10),
  position         SMALLINT,
  class_position   SMALLINT,
  stream_position  SMALLINT,
  result_code      ENUM('Result 1','Result 2','Result 3'),
  remarks          VARCHAR(255),
  teacher_remarks  VARCHAR(255),
  head_remarks     VARCHAR(255),
  computed_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_summary (exam_id,student_id),
  KEY idx_summary_class (class_id,position),
  CONSTRAINT fk_summary_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
  CONSTRAINT fk_summary_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_summary_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_summary_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE SET NULL
);

CREATE TABLE rankings (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id          INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  rank_scope       ENUM('class','stream','level','school') NOT NULL DEFAULT 'class',
  rank_position    SMALLINT NOT NULL,
  rank_value       DECIMAL(8,2),
  method           ENUM('average','points','subject_count') NOT NULL DEFAULT 'average',
  is_internal      TINYINT(1) NOT NULL DEFAULT 1,
  is_published     TINYINT(1) NOT NULL DEFAULT 0,
  published_at     DATETIME,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ranking (exam_id,student_id,rank_scope),
  KEY idx_ranking_position (class_id,rank_position),
  CONSTRAINT fk_ranking_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
  CONSTRAINT fk_ranking_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_ranking_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_ranking_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE SET NULL
);

CREATE TABLE certificate_eligibility (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  exam_id          INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  cycle            ENUM('UCE','UACE') NOT NULL DEFAULT 'UCE',
  subjects_taken   SMALLINT NOT NULL DEFAULT 0,
  compulsory_missing SMALLINT NOT NULL DEFAULT 0,
  has_project_work TINYINT(1) NOT NULL DEFAULT 0,
  best_level       VARCHAR(10),
  qualified        TINYINT(1) NOT NULL DEFAULT 0,
  result_code      ENUM('Result 1','Result 2','Result 3'),
  result_meaning   VARCHAR(255),
  issues           JSON,
  checked_before_registration TINYINT(1) NOT NULL DEFAULT 0,
  checked_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  checked_by       INT UNSIGNED,
  notes            VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_certificate (student_id,academic_year_id,cycle),
  KEY idx_cert_status (qualified,result_code),
  CONSTRAINT fk_cert_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_cert_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE SET NULL,
  CONSTRAINT fk_cert_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE
);

CREATE TABLE report_cards (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  exam_id          INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  summary_id       INT UNSIGNED,
  status           ENUM('draft','generated','released','withheld','void') NOT NULL DEFAULT 'draft',
  withheld_reason  VARCHAR(255),
  portal_visible   TINYINT(1) NOT NULL DEFAULT 0,
  printed_count    SMALLINT NOT NULL DEFAULT 0,
  file_path        VARCHAR(255),
  generated_by     INT UNSIGNED,
  generated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  released_at      DATETIME,
  PRIMARY KEY (id),
  UNIQUE KEY uq_report_card (student_id,exam_id),
  KEY idx_report_status (status,term_id),
  CONSTRAINT fk_report_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_report_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
  CONSTRAINT fk_report_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_report_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
  CONSTRAINT fk_report_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_report_summary FOREIGN KEY (summary_id) REFERENCES result_summaries (id) ON DELETE SET NULL
);

CREATE TABLE transcripts (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  transcript_type  ENUM('cumulative','transfer','testimonial','provisional','alumni') NOT NULL DEFAULT 'cumulative',
  period_from      DATE,
  period_to        DATE,
  request_reason   VARCHAR(255),
  requested_by     INT UNSIGNED,
  copies           SMALLINT NOT NULL DEFAULT 1,
  prepared_by      INT UNSIGNED,
  status           ENUM('requested','preparing','ready','issued','cancelled') NOT NULL DEFAULT 'requested',
  file_path        VARCHAR(255),
  issued_at        DATETIME,
  issued_to        VARCHAR(160),
  verified_code    VARCHAR(60),
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_transcript_student (student_id,status),
  CONSTRAINT fk_transcript_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE remedial_records (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  exam_id          INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  original_percent DECIMAL(6,2),
  original_grade   VARCHAR(10),
  remedial_date    DATE,
  remedial_mark    DECIMAL(6,2),
  new_percent      DECIMAL(6,2),
  new_grade        VARCHAR(10),
  remedial_type    ENUM('remedial','retake','special_paper') NOT NULL DEFAULT 'remedial',
  status           ENUM('scheduled','sat','passed','not_passed','void') NOT NULL DEFAULT 'scheduled',
  approved_by      INT UNSIGNED,
  remarks          VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_remedial (student_id,subject_id,exam_id),
  CONSTRAINT fk_remedial_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_remedial_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_remedial_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
  CONSTRAINT fk_remedial_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_levels (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(60) NOT NULL,
  code        VARCHAR(20) NOT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_level (school_id, code),
  CONSTRAINT fk_uneb_level_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_candidates (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  index_number    VARCHAR(30) NOT NULL,
  full_name       VARCHAR(160) NOT NULL,
  gender          ENUM('male','female') NOT NULL,
  date_of_birth   DATE,
  student_id      INT UNSIGNED,
  level_id        INT UNSIGNED NOT NULL,
  status          ENUM('registered','entered','withdrawn','completed') NOT NULL DEFAULT 'registered',
  notes           VARCHAR(255),
  registered_by   INT UNSIGNED,
  registered_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_index (school_id, index_number),
  KEY idx_uneb_level (level_id, status),
  CONSTRAINT fk_uneb_cand_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_cand_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_uneb_cand_level FOREIGN KEY (level_id) REFERENCES uneb_levels (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_candidate_subjects (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  candidate_id  INT UNSIGNED NOT NULL,
  subject_id    INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_cand_subject (candidate_id, subject_id),
  CONSTRAINT fk_uneb_subj_candidate FOREIGN KEY (candidate_id) REFERENCES uneb_candidates (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_subj_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_results (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  level_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED,
  exam_year        YEAR NOT NULL,
  index_number     VARCHAR(30) NOT NULL,
  full_name        VARCHAR(160) NOT NULL,
  gender           ENUM('male','female') NOT NULL,
  subject_results  TEXT,
  overall_level    VARCHAR(10),
  result_code      ENUM('Result 1','Result 2','Result 3'),
  ca_score_submitted TINYINT(1) NOT NULL DEFAULT 0,
  notes            VARCHAR(255),
  recorded_by      INT UNSIGNED,
  recorded_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_result (school_id, level_id, exam_year, index_number),
  KEY idx_uneb_result_year (exam_year, index_number),
  CONSTRAINT fk_uneb_res_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_res_level FOREIGN KEY (level_id) REFERENCES uneb_levels (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_res_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
);

CREATE TABLE question_banks (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  subject_id  INT UNSIGNED NOT NULL,
  title       VARCHAR(160) NOT NULL,
  description VARCHAR(255),
  created_by  INT UNSIGNED,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bank_subject (subject_id),
  CONSTRAINT fk_bank_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_bank_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

CREATE TABLE questions (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bank_id        INT UNSIGNED NOT NULL,
  syllabus_topic_id INT UNSIGNED,
  question_type  ENUM('mcq','true_false','short_answer','essay','structured','practical') NOT NULL DEFAULT 'mcq',
  difficulty     ENUM('easy','moderate','difficult') NOT NULL DEFAULT 'moderate',
  question_text  TEXT NOT NULL,
  options        JSON,
  correct_answer TEXT,
  marks          DECIMAL(5,2) NOT NULL DEFAULT 1,
  explanation    TEXT,
  times_used     INT UNSIGNED NOT NULL DEFAULT 0,
  status         ENUM('active','archived','draft') NOT NULL DEFAULT 'active',
  created_by     INT UNSIGNED,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_question_bank (bank_id,difficulty),
  CONSTRAINT fk_question_bank FOREIGN KEY (bank_id) REFERENCES question_banks (id) ON DELETE CASCADE,
  CONSTRAINT fk_question_topic FOREIGN KEY (syllabus_topic_id) REFERENCES syllabus_topics (id) ON DELETE SET NULL
);

CREATE TABLE gl_accounts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     INT UNSIGNED NOT NULL,
  account_code  VARCHAR(20)  NOT NULL,
  account_name  VARCHAR(140) NOT NULL,
  account_type  ENUM('income','expense','asset','liability','equity') NOT NULL,
  parent_id     INT UNSIGNED,
  description   VARCHAR(255),
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_account_code (school_id,account_code),
  KEY idx_account_type (account_type),
  CONSTRAINT fk_account_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_account_parent FOREIGN KEY (parent_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE fee_structures (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  name             VARCHAR(140) NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  class_level      ENUM('all','O-Level','A-Level') NOT NULL DEFAULT 'all',
  class_id         INT UNSIGNED,
  student_category ENUM('all','day','boarding','new','continuing') NOT NULL DEFAULT 'all',
  total_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
  effective_from   DATE,
  status           ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  description      VARCHAR(255),
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fee_structure (academic_year_id,term_id,status),
  CONSTRAINT fk_fee_structure_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_fee_structure_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_fee_structure_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
  CONSTRAINT fk_fee_structure_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE SET NULL
);

CREATE TABLE fee_structure_items (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fee_structure_id INT UNSIGNED NOT NULL,
  item_name      VARCHAR(140) NOT NULL,
  item_code      VARCHAR(40),
  account_id     INT UNSIGNED,
  amount         DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_mandatory   TINYINT(1)   NOT NULL DEFAULT 1,
  applies_to     ENUM('all','day','boarding') NOT NULL DEFAULT 'all',
  sort_order     SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_fee_item_structure (fee_structure_id),
  CONSTRAINT fk_fee_item_structure FOREIGN KEY (fee_structure_id) REFERENCES fee_structures (id) ON DELETE CASCADE,
  CONSTRAINT fk_fee_item_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE payment_methods (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id             INT UNSIGNED NOT NULL,
  code                  VARCHAR(30)  NOT NULL,
  name                  VARCHAR(80)  NOT NULL,
  channel               ENUM('cash','bank','cheque','mobile_money','online','other') NOT NULL DEFAULT 'cash',
  account_id            INT UNSIGNED,
  requires_verification TINYINT(1)   NOT NULL DEFAULT 1,
  is_active             TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order            SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_method (school_id,code),
  CONSTRAINT fk_payment_method_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_method_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE fee_invoices (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_number   VARCHAR(40)  NOT NULL,
  school_id        INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  fee_structure_id INT UNSIGNED,
  issue_date       DATE NOT NULL,
  due_date         DATE,
  subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_total   DECIMAL(12,2) NOT NULL DEFAULT 0,
  waived_total     DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  balance          DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency         VARCHAR(10)  NOT NULL DEFAULT 'UGX',
  status           ENUM('draft','unpaid','part_paid','paid','void') NOT NULL DEFAULT 'unpaid',
  notes            VARCHAR(255),
  generated_by     INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoice_number (school_id,invoice_number),
  KEY idx_invoice_student (student_id,academic_year_id,term_id),
  KEY idx_invoice_status (status,due_date),
  CONSTRAINT fk_invoice_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_invoice_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_invoice_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoice_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL,
  CONSTRAINT fk_invoice_structure FOREIGN KEY (fee_structure_id) REFERENCES fee_structures (id) ON DELETE SET NULL
);

CREATE TABLE fee_invoice_items (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id    INT UNSIGNED NOT NULL,
  item_name     VARCHAR(140) NOT NULL,
  account_id    INT UNSIGNED,
  amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  waived_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
  source        ENUM('structure','manual','transport','boarding','canteen','library_fine') NOT NULL DEFAULT 'structure',
  sort_order    SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_invoice_item (invoice_id),
  CONSTRAINT fk_invoice_item_invoice FOREIGN KEY (invoice_id) REFERENCES fee_invoices (id) ON DELETE CASCADE,
  CONSTRAINT fk_invoice_item_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE payments (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_reference VARCHAR(40) NOT NULL,
  school_id        INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  invoice_id       INT UNSIGNED,
  payment_method_id INT UNSIGNED,
  channel          ENUM('cash','bank','cheque','mobile_money','online','other') NOT NULL DEFAULT 'cash',
  amount           DECIMAL(12,2) NOT NULL,
  currency         VARCHAR(10)  NOT NULL DEFAULT 'UGX',
  exchange_rate    DECIMAL(12,6) NOT NULL DEFAULT 1,
  amount_local     DECIMAL(12,2) NOT NULL,
  payment_date     DATE NOT NULL,
  payment_time     TIME,
  external_reference VARCHAR(80),
  payer_name       VARCHAR(160),
  payer_phone      VARCHAR(40),
  received_by      INT UNSIGNED,
  verified_by      INT UNSIGNED,
  verified_at      DATETIME,
  status           ENUM('pending','verified','posted','rejected','reversed','void') NOT NULL DEFAULT 'pending',
  reversal_reason  VARCHAR(255),
  notes            VARCHAR(255),
  posted_at        DATETIME,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_reference (school_id,payment_reference),
  KEY idx_payment_student (student_id,payment_date),
  KEY idx_payment_status (status,payment_date),
  CONSTRAINT fk_payment_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES fee_invoices (id) ON DELETE SET NULL,
  CONSTRAINT fk_payment_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id) ON DELETE SET NULL
);

CREATE TABLE payment_verifications (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id     INT UNSIGNED NOT NULL,
  verified_by    INT UNSIGNED NOT NULL,
  verified_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  result         ENUM('confirmed','rejected','suspended') NOT NULL,
  evidence_path  VARCHAR(255),
  bank_statement_ref VARCHAR(80),
  comments       VARCHAR(255),
  PRIMARY KEY (id),
  KEY idx_verification_payment (payment_id),
  CONSTRAINT fk_verification_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE
);

CREATE TABLE receipts (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  receipt_number  VARCHAR(40)  NOT NULL,
  school_id       INT UNSIGNED NOT NULL,
  payment_id      INT UNSIGNED NOT NULL,
  student_id      INT UNSIGNED NOT NULL,
  amount          DECIMAL(12,2) NOT NULL,
  method_label    VARCHAR(80),
  narrative       VARCHAR(255),
  issued_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  issued_by       INT UNSIGNED,
  delivery_status ENUM('printed','emailed','sms','portal','pending') NOT NULL DEFAULT 'printed',
  delivery_reference VARCHAR(80),
  printed_count   SMALLINT NOT NULL DEFAULT 1,
  pdf_path        VARCHAR(255),
  is_void         TINYINT(1) NOT NULL DEFAULT 0,
  void_reason     VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_receipt_number (school_id,receipt_number),
  KEY idx_receipt_payment (payment_id),
  KEY idx_receipt_student (student_id,issued_at),
  CONSTRAINT fk_receipt_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_receipt_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE,
  CONSTRAINT fk_receipt_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE fee_reminders (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  guardian_id      INT UNSIGNED,
  invoice_id       INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  channel          ENUM('sms','email','portal','print') NOT NULL DEFAULT 'sms',
  balance_at_time  DECIMAL(12,2) NOT NULL DEFAULT 0,
  message          VARCHAR(500),
  scheduled_for    DATETIME,
  sent_at          DATETIME,
  sent_by          INT UNSIGNED,
  status           ENUM('scheduled','sent','failed','cancelled') NOT NULL DEFAULT 'scheduled',
  error_note       VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reminder_student (student_id,status),
  CONSTRAINT fk_reminder_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_reminder_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE SET NULL,
  CONSTRAINT fk_reminder_invoice FOREIGN KEY (invoice_id) REFERENCES fee_invoices (id) ON DELETE SET NULL,
  CONSTRAINT fk_reminder_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_reminder_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL
);

CREATE TABLE clearances (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  clearance_type   ENUM('examination','report_card','end_of_term','transfer','graduation') NOT NULL,
  status           ENUM('pending','cleared','blocked','exception_granted') NOT NULL DEFAULT 'pending',
  outstanding_balance DECIMAL(12,2),
  reason           VARCHAR(255),
  policy_note      VARCHAR(255),
  checked_by       INT UNSIGNED,
  checked_at       DATETIME,
  approved_by      INT UNSIGNED,
  approved_at      DATETIME,
  exception_reason VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clearance (student_id,academic_year_id,term_id,clearance_type),
  KEY idx_clearance_status (status,clearance_type),
  CONSTRAINT fk_clearance_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_clearance_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_clearance_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL
);

CREATE TABLE discounts_waivers (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED,
  class_id         INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  adjustment_type  ENUM('discount','waiver','bursary_offset') NOT NULL DEFAULT 'discount',
  reason           ENUM('sibling','staff_child','scholarship','merit','hardship','early_payment','other') NOT NULL,
  description      VARCHAR(255),
  value_type       ENUM('amount','percent') NOT NULL DEFAULT 'amount',
  value            DECIMAL(12,2) NOT NULL DEFAULT 0,
  max_amount       DECIMAL(12,2),
  invoice_id       INT UNSIGNED,
  approved_by      INT UNSIGNED,
  approved_at      DATETIME,
  expires_on       DATE,
  status           ENUM('pending','approved','rejected','applied','expired') NOT NULL DEFAULT 'pending',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_discount_student (student_id,academic_year_id),
  CONSTRAINT fk_discount_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_discount_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_discount_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_discount_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_discount_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
  CONSTRAINT fk_discount_invoice FOREIGN KEY (invoice_id) REFERENCES fee_invoices (id) ON DELETE SET NULL
);

CREATE TABLE sponsors (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  name            VARCHAR(160) NOT NULL,
  sponsor_type    ENUM('ngo','church','company','government','individual','other') NOT NULL DEFAULT 'company',
  contact_person  VARCHAR(140),
  phone           VARCHAR(40),
  email           VARCHAR(120),
  address         VARCHAR(255),
  account_id      INT UNSIGNED,
  payment_terms   VARCHAR(120),
  credit_limit    DECIMAL(12,2),
  balance         DECIMAL(12,2) NOT NULL DEFAULT 0,
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes           VARCHAR(255),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sponsor_name (name),
  CONSTRAINT fk_sponsor_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_sponsor_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE bursaries (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  name            VARCHAR(140) NOT NULL,
  sponsor_id      INT UNSIGNED,
  funding_source  ENUM('school','government','donor','pta','other') NOT NULL DEFAULT 'donor',
  description     VARCHAR(255),
  coverage_type   ENUM('full','partial') NOT NULL DEFAULT 'partial',
  annual_budget   DECIMAL(12,2),
  start_date      DATE,
  end_date        DATE,
  status          ENUM('open','closed','suspended') NOT NULL DEFAULT 'open',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bursary_status (status),
  CONSTRAINT fk_bursary_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_bursary_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors (id) ON DELETE SET NULL
);

CREATE TABLE bursary_awards (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bursary_id       INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  award_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
  award_type       ENUM('amount','percent') NOT NULL DEFAULT 'amount',
  award_value      DECIMAL(12,2) NOT NULL DEFAULT 0,
  start_date       DATE,
  end_date         DATE,
  status           ENUM('active','completed','revoked','expired') NOT NULL DEFAULT 'active',
  approved_by      INT UNSIGNED,
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bursary_award (bursary_id,student_id,academic_year_id,term_id),
  KEY idx_award_student (student_id),
  CONSTRAINT fk_award_bursary FOREIGN KEY (bursary_id) REFERENCES bursaries (id) ON DELETE CASCADE,
  CONSTRAINT fk_award_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_award_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_award_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE sponsor_billings (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  billing_number   VARCHAR(40)  NOT NULL,
  school_id        INT UNSIGNED NOT NULL,
  sponsor_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  billing_date     DATE NOT NULL,
  due_date         DATE,
  total_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  balance          DECIMAL(12,2) NOT NULL DEFAULT 0,
  status           ENUM('draft','issued','part_paid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  generated_by     INT UNSIGNED,
  issued_at        DATETIME,
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_billing_number (school_id,billing_number),
  KEY idx_billing_sponsor (sponsor_id,status),
  CONSTRAINT fk_billing_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_billing_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors (id) ON DELETE CASCADE,
  CONSTRAINT fk_billing_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_billing_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE sponsor_billing_items (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  billing_id    INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  invoice_id    INT UNSIGNED,
  description   VARCHAR(160) NOT NULL,
  amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_billing_item (billing_id),
  KEY idx_billing_student (student_id),
  CONSTRAINT fk_billing_item_billing FOREIGN KEY (billing_id) REFERENCES sponsor_billings (id) ON DELETE CASCADE,
  CONSTRAINT fk_billing_item_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_billing_item_invoice FOREIGN KEY (invoice_id) REFERENCES fee_invoices (id) ON DELETE SET NULL
);

CREATE TABLE budgets (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  name             VARCHAR(140) NOT NULL,
  budget_type      ENUM('termly','annual','project') NOT NULL DEFAULT 'termly',
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  department_id    INT UNSIGNED,
  total_amount     DECIMAL(14,2) NOT NULL DEFAULT 0,
  status           ENUM('draft','submitted','approved','rejected','closed') NOT NULL DEFAULT 'draft',
  created_by       INT UNSIGNED,
  approved_by      INT UNSIGNED,
  approved_at      DATETIME,
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_budget_year (academic_year_id,term_id,status),
  CONSTRAINT fk_budget_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_budget_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_budget_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
  CONSTRAINT fk_budget_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
);

CREATE TABLE budget_lines (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  budget_id      INT UNSIGNED NOT NULL,
  account_id     INT UNSIGNED NOT NULL,
  planned_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  actual_amount  DECIMAL(14,2) NOT NULL DEFAULT 0,
  variance       DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes          VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_budget_line (budget_id,account_id),
  CONSTRAINT fk_budget_line_budget FOREIGN KEY (budget_id) REFERENCES budgets (id) ON DELETE CASCADE,
  CONSTRAINT fk_budget_line_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE RESTRICT
);

CREATE TABLE expenses (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  voucher_number   VARCHAR(40)  NOT NULL,
  school_id        INT UNSIGNED NOT NULL,
  account_id       INT UNSIGNED NOT NULL,
  department_id    INT UNSIGNED,
  academic_year_id INT UNSIGNED,
  term_id          INT UNSIGNED,
  amount           DECIMAL(14,2) NOT NULL,
  expense_date     DATE NOT NULL,
  payee            VARCHAR(160) NOT NULL,
  description      VARCHAR(255) NOT NULL,
  category         ENUM('utilities','salaries','repairs','supplies','transport','learning_materials','food','maintenance','other') NOT NULL DEFAULT 'other',
  payment_method   ENUM('cash','bank','cheque','mobile_money','other') NOT NULL DEFAULT 'cash',
  bank_account_id  INT UNSIGNED,
  requested_by     INT UNSIGNED,
  approved_by      INT UNSIGNED,
  approval_status  ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  approved_at      DATETIME,
  receipt_path     VARCHAR(255),
  is_recurring     TINYINT(1) NOT NULL DEFAULT 0,
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_voucher (school_id,voucher_number),
  KEY idx_expense_date (expense_date),
  KEY idx_expense_account (account_id,expense_date),
  CONSTRAINT fk_expense_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_expense_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE RESTRICT,
  CONSTRAINT fk_expense_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_expense_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL,
  CONSTRAINT fk_expense_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL
);

CREATE TABLE petty_cash_funds (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  fund_name       VARCHAR(80)  NOT NULL,
  opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  current_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  custodian_staff_id INT UNSIGNED,
  account_id      INT UNSIGNED,
  opened_on       DATE,
  status          ENUM('open','closed','suspended') NOT NULL DEFAULT 'open',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_petty_fund (school_id,fund_name),
  CONSTRAINT fk_petty_fund_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_petty_fund_staff FOREIGN KEY (custodian_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_petty_fund_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE petty_cash_vouchers (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fund_id       INT UNSIGNED NOT NULL,
  voucher_number VARCHAR(40) NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  spent_on      VARCHAR(255) NOT NULL,
  spent_by      INT UNSIGNED,
  expense_date  DATE NOT NULL,
  account_id    INT UNSIGNED,
  approved_by   INT UNSIGNED,
  receipt_path  VARCHAR(255),
  balance_after DECIMAL(12,2),
  status        ENUM('requested','approved','rejected','paid') NOT NULL DEFAULT 'requested',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_petty_voucher (fund_id,voucher_number),
  CONSTRAINT fk_petty_voucher_fund FOREIGN KEY (fund_id) REFERENCES petty_cash_funds (id) ON DELETE CASCADE,
  CONSTRAINT fk_petty_voucher_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE bank_accounts (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  bank_name        VARCHAR(100) NOT NULL,
  account_name     VARCHAR(140) NOT NULL,
  account_number   VARCHAR(40)  NOT NULL,
  branch_name      VARCHAR(80),
  swift_code       VARCHAR(20),
  currency         VARCHAR(10)  NOT NULL DEFAULT 'UGX',
  opening_balance  DECIMAL(14,2) NOT NULL DEFAULT 0,
  current_balance  DECIMAL(14,2) NOT NULL DEFAULT 0,
  account_id       INT UNSIGNED,
  is_default       TINYINT(1)   NOT NULL DEFAULT 0,
  status           ENUM('active','closed','frozen') NOT NULL DEFAULT 'active',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bank_account (school_id,account_number),
  CONSTRAINT fk_bank_account_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_bank_gl FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE bank_transactions (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bank_account_id  INT UNSIGNED NOT NULL,
  transaction_date DATE NOT NULL,
  value_date       DATE,
  description      VARCHAR(255) NOT NULL,
  deposit          DECIMAL(14,2) NOT NULL DEFAULT 0,
  withdrawal       DECIMAL(14,2) NOT NULL DEFAULT 0,
  running_balance  DECIMAL(14,2),
  reference        VARCHAR(80),
  payment_id       INT UNSIGNED,
  expense_id       INT UNSIGNED,
  is_reconciled    TINYINT(1)   NOT NULL DEFAULT 0,
  reconciled_at    DATETIME,
  source           ENUM('manual','import','system') NOT NULL DEFAULT 'manual',
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bank_txn_account (bank_account_id,transaction_date),
  CONSTRAINT fk_bank_txn_account FOREIGN KEY (bank_account_id) REFERENCES bank_accounts (id) ON DELETE CASCADE,
  CONSTRAINT fk_bank_txn_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL,
  CONSTRAINT fk_bank_txn_expense FOREIGN KEY (expense_id) REFERENCES expenses (id) ON DELETE SET NULL
);

CREATE TABLE bank_reconciliations (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bank_account_id  INT UNSIGNED NOT NULL,
  period_start     DATE NOT NULL,
  period_end       DATE NOT NULL,
  statement_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
  book_balance     DECIMAL(14,2) NOT NULL DEFAULT 0,
  difference       DECIMAL(14,2) NOT NULL DEFAULT 0,
  unmatched_count  INT UNSIGNED NOT NULL DEFAULT 0,
  reconciled_by    INT UNSIGNED,
  reconciled_at    DATETIME,
  status           ENUM('draft','in_progress','matched','completed','disputed') NOT NULL DEFAULT 'draft',
  notes            TEXT,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reconciliation (bank_account_id,period_start,period_end),
  CONSTRAINT fk_recon_account FOREIGN KEY (bank_account_id) REFERENCES bank_accounts (id) ON DELETE CASCADE
);

CREATE TABLE ledger_entries (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  entry_date       DATE NOT NULL,
  entry_group      CHAR(36)     NOT NULL,
  account_id       INT UNSIGNED NOT NULL,
  debit            DECIMAL(14,2) NOT NULL DEFAULT 0,
  credit           DECIMAL(14,2) NOT NULL DEFAULT 0,
  memo             VARCHAR(255),
  reference_type   ENUM('payment','expense','sponsor_payment','transfer','adjustment','petty_cash','other') NOT NULL,
  reference_id     INT UNSIGNED,
  academic_year_id INT UNSIGNED,
  term_id          INT UNSIGNED,
  is_posted        TINYINT(1) NOT NULL DEFAULT 1,
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ledger_date (entry_date),
  KEY idx_ledger_account (account_id,entry_date),
  KEY idx_ledger_group (entry_group),
  CONSTRAINT fk_ledger_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_ledger_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE RESTRICT
);

CREATE TABLE gateway_configs (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id          INT UNSIGNED NOT NULL,
  provider_code      VARCHAR(40)  NOT NULL,
  provider_name      VARCHAR(100) NOT NULL,
  provider_type      ENUM('mobile_money','bank','card','other') NOT NULL DEFAULT 'mobile_money',
  api_base_url       VARCHAR(255),
  merchant_id        VARCHAR(120),
  account_identifier VARCHAR(120),
  currency           VARCHAR(10)  NOT NULL DEFAULT 'UGX',
  credential_key     VARCHAR(120),
  credential_value   VARCHAR(500),
  environment        ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
  callback_url       VARCHAR(255),
  is_active          TINYINT(1)   NOT NULL DEFAULT 1,
  supports_refund    TINYINT(1)   NOT NULL DEFAULT 0,
  last_health_check  DATETIME,
  notes              VARCHAR(255),
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gateway_provider (school_id,provider_code),
  CONSTRAINT fk_gateway_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE payment_references (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  reference_code VARCHAR(60) NOT NULL,
  reference_type ENUM('admission_number','custom','invoice_number') NOT NULL DEFAULT 'admission_number',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  generated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes         VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_reference_code (school_id,reference_code),
  KEY idx_reference_student (student_id,is_active),
  CONSTRAINT fk_reference_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_reference_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE gateway_transactions (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id          INT UNSIGNED NOT NULL,
  gateway_config_id  INT UNSIGNED NOT NULL,
  payment_reference_id INT UNSIGNED,
  provider_txn_id    VARCHAR(120),
  invoice_id         INT UNSIGNED,
  student_id         INT UNSIGNED,
  amount             DECIMAL(12,2) NOT NULL,
  currency           VARCHAR(10) NOT NULL DEFAULT 'UGX',
  payer_msisdn       VARCHAR(40),
  payer_name         VARCHAR(160),
  purpose            ENUM('fees','canteen','transport','other') NOT NULL DEFAULT 'fees',
  status             ENUM('initiated','pending','success','failed','reversed','duplicate','expired') NOT NULL DEFAULT 'initiated',
  failure_reason     VARCHAR(255),
  initiated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_at       DATETIME,
  payload_request    JSON,
  payload_response   JSON,
  payment_id         INT UNSIGNED,
  posted_at          DATETIME,
  receipt_sent_at    DATETIME,
  receipt_channel    ENUM('sms','email','portal','none'),
  attempt_count      SMALLINT NOT NULL DEFAULT 1,
  resolved_by        INT UNSIGNED,
  resolved_at        DATETIME,
  resolution_note    VARCHAR(255),
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_provider_txn (gateway_config_id,provider_txn_id),
  KEY idx_txn_status (status,initiated_at),
  KEY idx_txn_student (student_id,status),
  KEY idx_txn_invoice (invoice_id),
  CONSTRAINT fk_txn_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_txn_gateway FOREIGN KEY (gateway_config_id) REFERENCES gateway_configs (id) ON DELETE RESTRICT,
  CONSTRAINT fk_txn_reference FOREIGN KEY (payment_reference_id) REFERENCES payment_references (id) ON DELETE SET NULL,
  CONSTRAINT fk_txn_invoice FOREIGN KEY (invoice_id) REFERENCES fee_invoices (id) ON DELETE SET NULL,
  CONSTRAINT fk_txn_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_txn_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
);

CREATE TABLE gateway_events (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gateway_config_id INT UNSIGNED,
  event_id          VARCHAR(160),
  event_type        ENUM('callback','webhook','poll','refund','reversal','health_check','other') NOT NULL,
  http_method       VARCHAR(10),
  ip_address        VARCHAR(45),
  signature         VARCHAR(255),
  signature_valid   TINYINT(1),
  payload           JSON NOT NULL,
  related_txn_id    INT UNSIGNED,
  processing_status ENUM('received','processed','ignored','error') NOT NULL DEFAULT 'received',
  error_message     VARCHAR(500),
  received_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_event_txn (related_txn_id),
  KEY idx_event_time (received_at),
  CONSTRAINT fk_event_gateway FOREIGN KEY (gateway_config_id) REFERENCES gateway_configs (id) ON DELETE SET NULL,
  CONSTRAINT fk_event_txn FOREIGN KEY (related_txn_id) REFERENCES gateway_transactions (id) ON DELETE SET NULL
);

CREATE TABLE failed_payment_cases (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gateway_txn_id      INT UNSIGNED NOT NULL,
  school_id           INT UNSIGNED NOT NULL,
  issue_type          ENUM('not_posted','duplicate','wrong_reference','reversed','amount_mismatch','stuck_pending','parent_complaint','other') NOT NULL,
  reported_by         INT UNSIGNED,
  reported_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  description         VARCHAR(500),
  priority            ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status              ENUM('open','investigating','resolved','escalated','closed_unresolved') NOT NULL DEFAULT 'open',
  resolution          VARCHAR(500),
  resolved_by         INT UNSIGNED,
  resolved_at         DATETIME,
  parent_feedback     VARCHAR(255),
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_case_status (status,priority),
  KEY idx_case_txn (gateway_txn_id),
  CONSTRAINT fk_case_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_case_txn FOREIGN KEY (gateway_txn_id) REFERENCES gateway_transactions (id) ON DELETE CASCADE
);

CREATE TABLE bank_payment_matches (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bank_txn_id     INT UNSIGNED NOT NULL,
  payment_id      INT UNSIGNED,
  student_id      INT UNSIGNED,
  match_type      ENUM('auto_reference','auto_amount','manual','unmatched') NOT NULL DEFAULT 'auto_reference',
  confidence      DECIMAL(5,2),
  matched_by      INT UNSIGNED,
  matched_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status          ENUM('suggested','confirmed','rejected','reversed') NOT NULL DEFAULT 'suggested',
  notes           VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_bank_match (bank_txn_id,payment_id),
  KEY idx_match_payment (payment_id),
  CONSTRAINT fk_match_bank_txn FOREIGN KEY (bank_txn_id) REFERENCES bank_transactions (id) ON DELETE CASCADE,
  CONSTRAINT fk_match_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL,
  CONSTRAINT fk_match_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL
);

CREATE TABLE student_attendance (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED NOT NULL,
  attendance_date  DATE NOT NULL,
  record_scope     ENUM('daily','lesson') NOT NULL DEFAULT 'daily',
  class_id         INT UNSIGNED NOT NULL,
  stream_id        INT UNSIGNED,
  student_id       INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED,
  period_number    TINYINT,
  status           ENUM('present','absent','late','excused','sick','suspended') NOT NULL DEFAULT 'present',
  minutes_late     SMALLINT,
  reason           VARCHAR(255),
  notes            VARCHAR(255),
  recorded_by      INT UNSIGNED,
  recorded_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  record_key       VARCHAR(60) GENERATED ALWAYS AS
    (IF(record_scope = 'daily', 'daily',
        CONCAT('L', IFNULL(subject_id, 0), 'P', IFNULL(period_number, 0)))) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_attendance (student_id,attendance_date,record_key),
  KEY idx_attendance_date (class_id,attendance_date),
  KEY idx_attendance_status (attendance_date,status),
  KEY idx_attendance_student (student_id,attendance_date),
  CONSTRAINT fk_attendance_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE staff_attendance (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  staff_id         INT UNSIGNED NOT NULL,
  attendance_date  DATE NOT NULL,
  status           ENUM('present','absent','late','on_leave','duty_off','sick') NOT NULL DEFAULT 'present',
  time_in          TIME,
  time_out         TIME,
  minutes_late     SMALLINT,
  hours_worked     DECIMAL(5,2),
  remarks          VARCHAR(255),
  recorded_by      INT UNSIGNED,
  recorded_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_staff_attendance (staff_id,attendance_date),
  KEY idx_staff_att_date (attendance_date,status),
  CONSTRAINT fk_staff_att_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_staff_att_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
);

CREATE TABLE late_arrivals (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  person_type      ENUM('student','staff') NOT NULL,
  student_id       INT UNSIGNED,
  staff_id         INT UNSIGNED,
  late_date        DATE NOT NULL,
  time_arrived     TIME NOT NULL,
  minutes_late     SMALLINT NOT NULL DEFAULT 0,
  reason           VARCHAR(255),
  action_taken     VARCHAR(255),
  parent_notified  TINYINT(1) NOT NULL DEFAULT 0,
  recorded_by      INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_late_date (late_date,person_type),
  KEY idx_late_student (student_id,late_date),
  CONSTRAINT fk_late_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_late_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_late_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
);

CREATE TABLE absentee_followups (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  first_absent_date DATE NOT NULL,
  last_absent_date  DATE NOT NULL,
  consecutive_days SMALLINT NOT NULL DEFAULT 1,
  absence_pattern  ENUM('once','occasional','frequent','chronic') NOT NULL DEFAULT 'once',
  action           ENUM('none','sms','phone_call','letter','home_visit','class_meeting') NOT NULL DEFAULT 'sms',
  action_date      DATE,
  action_by        INT UNSIGNED,
  parent_response  VARCHAR(255),
  status           ENUM('open','in_progress','resolved','escalated','withdrawn') NOT NULL DEFAULT 'open',
  resolved_at      DATETIME,
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_followup_student (student_id,status),
  KEY idx_followup_date (status,last_absent_date),
  CONSTRAINT fk_followup_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_followup_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE attendance_notifications (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  guardian_id      INT UNSIGNED,
  attendance_id    BIGINT UNSIGNED,
  notification_type ENUM('absent','late','early_leave','pattern') NOT NULL,
  channel          ENUM('sms','email','portal','call') NOT NULL DEFAULT 'sms',
  message          VARCHAR(500),
  notification_id  BIGINT UNSIGNED,
  sent_at          DATETIME,
  sent_by          INT UNSIGNED,
  status           ENUM('queued','sent','failed','read','acknowledged') NOT NULL DEFAULT 'queued',
  parent_response  VARCHAR(255),
  error_note       VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_att_notif_student (student_id,status),
  CONSTRAINT fk_att_notif_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_att_notif_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_att_notif_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE SET NULL,
  CONSTRAINT fk_att_notif_attendance FOREIGN KEY (attendance_id) REFERENCES student_attendance (id) ON DELETE SET NULL
);

CREATE TABLE book_categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(80)  NOT NULL,
  code        VARCHAR(20),
  description VARCHAR(255),
  parent_id   INT UNSIGNED,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_book_category (school_id,name),
  CONSTRAINT fk_book_cat_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_book_cat_parent FOREIGN KEY (parent_id) REFERENCES book_categories (id) ON DELETE SET NULL
);

CREATE TABLE books (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  title          VARCHAR(255) NOT NULL,
  author         VARCHAR(160),
  isbn           VARCHAR(30),
  publisher      VARCHAR(160),
  edition        VARCHAR(30),
  publish_year   SMALLINT,
  category_id    INT UNSIGNED,
  subject_id     INT UNSIGNED,
  language       VARCHAR(40)  NOT NULL DEFAULT 'English',
  shelf_location VARCHAR(60),
  is_reference_only TINYINT(1) NOT NULL DEFAULT 0,
  total_copies   SMALLINT     NOT NULL DEFAULT 1,
  available_copies SMALLINT   NOT NULL DEFAULT 1,
  lost_copies    SMALLINT     NOT NULL DEFAULT 0,
  description    VARCHAR(500),
  status         ENUM('active','withdrawn','lost') NOT NULL DEFAULT 'active',
  created_by     INT UNSIGNED,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_book_title (title),
  KEY idx_book_isbn (isbn),
  KEY idx_book_category (category_id),
  CONSTRAINT fk_book_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_book_category FOREIGN KEY (category_id) REFERENCES book_categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_book_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE SET NULL
);

CREATE TABLE book_copies (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  book_id         INT UNSIGNED NOT NULL,
  accession_number VARCHAR(40) NOT NULL,
  barcode         VARCHAR(60),
  book_condition   ENUM('new','good','worn','damaged','missing_pages') NOT NULL DEFAULT 'good',
  status          ENUM('available','borrowed','reserved','lost','damaged','withdrawn') NOT NULL DEFAULT 'available',
  acquired_date   DATE,
  acquired_cost   DECIMAL(12,2),
  last_seen_date  DATE,
  remarks         VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_accession (accession_number),
  KEY idx_copy_book (book_id,status),
  CONSTRAINT fk_copy_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
);

CREATE TABLE borrowings (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id         INT UNSIGNED NOT NULL,
  copy_id           INT UNSIGNED NOT NULL,
  book_id           INT UNSIGNED NOT NULL,
  borrower_type     ENUM('student','staff') NOT NULL DEFAULT 'student',
  student_id        INT UNSIGNED,
  staff_id          INT UNSIGNED,
  issue_date        DATE NOT NULL,
  due_date          DATE NOT NULL,
  renewal_count     SMALLINT NOT NULL DEFAULT 0,
  return_date       DATE,
  condition_on_return ENUM('good','worn','damaged','missing_pages'),
  returned_to       INT UNSIGNED,
  status            ENUM('borrowed','returned','overdue','lost','reserved') NOT NULL DEFAULT 'borrowed',
  issued_by         INT UNSIGNED,
  notes             VARCHAR(255),
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_borrow_copy (copy_id,status),
  KEY idx_borrow_student (student_id,status),
  KEY idx_borrow_due (status,due_date),
  CONSTRAINT fk_borrow_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_borrow_copy FOREIGN KEY (copy_id) REFERENCES book_copies (id) ON DELETE RESTRICT,
  CONSTRAINT fk_borrow_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE RESTRICT,
  CONSTRAINT fk_borrow_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_borrow_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE SET NULL
);

CREATE TABLE library_notices (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  borrowing_id INT UNSIGNED NOT NULL,
  notice_type  ENUM('due_soon','overdue','overdue_reminder','fine_notice','recall') NOT NULL,
  channel      ENUM('sms','email','portal','print','none') NOT NULL DEFAULT 'portal',
  message      VARCHAR(500),
  days_overdue SMALLINT,
  sent_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_by      INT UNSIGNED,
  status       ENUM('queued','sent','failed','acknowledged') NOT NULL DEFAULT 'sent',
  PRIMARY KEY (id),
  KEY idx_lib_notice_borrowing (borrowing_id,notice_type),
  CONSTRAINT fk_lib_notice_borrowing FOREIGN KEY (borrowing_id) REFERENCES borrowings (id) ON DELETE CASCADE
);

CREATE TABLE library_fines (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     INT UNSIGNED NOT NULL,
  borrowing_id  INT UNSIGNED,
  student_id    INT UNSIGNED,
  staff_id      INT UNSIGNED,
  fine_type     ENUM('overdue','damaged','lost','non_return','other') NOT NULL,
  days_charged  SMALLINT,
  amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount_paid   DECIMAL(12,2) NOT NULL DEFAULT 0,
  reason        VARCHAR(255),
  status        ENUM('unpaid','part_paid','paid','waived') NOT NULL DEFAULT 'unpaid',
  added_to_fee_account TINYINT(1) NOT NULL DEFAULT 0,
  invoice_item_id INT UNSIGNED,
  payment_id    INT UNSIGNED,
  waived_by     INT UNSIGNED,
  waived_reason VARCHAR(255),
  created_by    INT UNSIGNED,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fine_status (status,fine_type),
  KEY idx_fine_student (student_id),
  CONSTRAINT fk_fine_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_fine_borrowing FOREIGN KEY (borrowing_id) REFERENCES borrowings (id) ON DELETE SET NULL,
  CONSTRAINT fk_fine_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_fine_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_fine_invoice_item FOREIGN KEY (invoice_item_id) REFERENCES fee_invoice_items (id) ON DELETE SET NULL,
  CONSTRAINT fk_fine_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
);

CREATE TABLE dormitories (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id           INT UNSIGNED NOT NULL,
  name                VARCHAR(100) NOT NULL,
  dormitory_type      ENUM('boys','girls','mixed','staff') NOT NULL,
  house_id            INT UNSIGNED,
  warden_staff_id     INT UNSIGNED,
  assistant_warden_staff_id INT UNSIGNED,
  capacity            SMALLINT NOT NULL DEFAULT 0,
  occupied_beds       SMALLINT NOT NULL DEFAULT 0,
  total_rooms         SMALLINT NOT NULL DEFAULT 0,
  location            VARCHAR(120),
  status              ENUM('operational','full_under_maintenance','closed') NOT NULL DEFAULT 'operational',
  notes               VARCHAR(255),
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dormitory (school_id,name),
  CONSTRAINT fk_dorm_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_dorm_house FOREIGN KEY (house_id) REFERENCES houses (id) ON DELETE SET NULL,
  CONSTRAINT fk_dorm_warden FOREIGN KEY (warden_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_dorm_assistant FOREIGN KEY (assistant_warden_staff_id) REFERENCES staff (id) ON DELETE SET NULL
);

CREATE TABLE dorm_rooms (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dormitory_id  INT UNSIGNED NOT NULL,
  room_number   VARCHAR(20)  NOT NULL,
  floor         VARCHAR(20),
  capacity      SMALLINT     NOT NULL DEFAULT 4,
  occupied_beds SMALLINT     NOT NULL DEFAULT 0,
  room_type     ENUM('normal','prefect','sick_room','store','visitors') NOT NULL DEFAULT 'normal',
  status        ENUM('available','full','maintenance','closed') NOT NULL DEFAULT 'available',
  remarks       VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_room_number (dormitory_id,room_number),
  CONSTRAINT fk_dorm_room FOREIGN KEY (dormitory_id) REFERENCES dormitories (id) ON DELETE CASCADE
);

CREATE TABLE beds (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dorm_room_id      INT UNSIGNED NOT NULL,
  bed_label    VARCHAR(20)  NOT NULL,
  bed_type     ENUM('single_bunk','bed_stead','mattress','other') NOT NULL DEFAULT 'single_bunk',
  status       ENUM('available','occupied','under_repair','reserved','out_of_use') NOT NULL DEFAULT 'available',
  last_occupied_student_id INT UNSIGNED,
  remarks      VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_bed_label (dorm_room_id,bed_label),
  KEY idx_bed_status (status),
  CONSTRAINT fk_bed_dorm_room FOREIGN KEY (dorm_room_id) REFERENCES dorm_rooms (id) ON DELETE CASCADE,
  CONSTRAINT fk_bed_student FOREIGN KEY (last_occupied_student_id) REFERENCES students (id) ON DELETE SET NULL
);

CREATE TABLE boarding_registrations (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id        INT UNSIGNED NOT NULL,
  academic_year_id  INT UNSIGNED NOT NULL,
  term_id           INT UNSIGNED NOT NULL,
  dormitory_id      INT UNSIGNED,
  items_received    TEXT,
  emergency_contact VARCHAR(255),
  status            ENUM('registered','checked_in','checked_out','cancelled','transferred') NOT NULL DEFAULT 'registered',
  registered_by     INT UNSIGNED,
  registered_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  checked_in_at     DATETIME,
  checked_out_at    DATETIME,
  notes             VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_boarding_registration (student_id,academic_year_id,term_id),
  KEY idx_boarding_dorm (dormitory_id,status),
  CONSTRAINT fk_boarding_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_boarding_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_boarding_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
  CONSTRAINT fk_boarding_dorm FOREIGN KEY (dormitory_id) REFERENCES dormitories (id) ON DELETE SET NULL
);

CREATE TABLE bed_allocations (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  bed_id           INT UNSIGNED NOT NULL,
  dorm_room_id          INT UNSIGNED NOT NULL,
  dormitory_id     INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  allocation_date  DATE NOT NULL,
  released_date    DATE,
  release_reason   VARCHAR(255),
  status           ENUM('active','released','transferred') NOT NULL DEFAULT 'active',
  allocated_by     INT UNSIGNED,
  approved_by      INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bed_alloc_student (student_id,status),
  KEY idx_bed_alloc_bed (bed_id,status),
  KEY idx_bed_alloc_dorm (dormitory_id,status),
  CONSTRAINT fk_bed_alloc_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_bed_alloc_bed FOREIGN KEY (bed_id) REFERENCES beds (id) ON DELETE RESTRICT,
  CONSTRAINT fk_bed_alloc_room FOREIGN KEY (dorm_room_id) REFERENCES dorm_rooms (id) ON DELETE RESTRICT,
  CONSTRAINT fk_bed_alloc_dorm FOREIGN KEY (dormitory_id) REFERENCES dormitories (id) ON DELETE RESTRICT,
  CONSTRAINT fk_bed_alloc_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_bed_alloc_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE room_transfers (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id     INT UNSIGNED NOT NULL,
  from_bed_id    INT UNSIGNED,
  from_room_id   INT UNSIGNED,
  from_dorm_id   INT UNSIGNED,
  to_bed_id      INT UNSIGNED,
  to_room_id     INT UNSIGNED,
  to_dorm_id     INT UNSIGNED,
  reason         VARCHAR(255),
  transfer_date  DATE NOT NULL,
  approved_by    INT UNSIGNED,
  approved_at    DATETIME,
  created_by     INT UNSIGNED,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_transfer_student (student_id,transfer_date),
  CONSTRAINT fk_rt_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_rt_from_bed FOREIGN KEY (from_bed_id) REFERENCES beds (id) ON DELETE SET NULL,
  CONSTRAINT fk_rt_from_dorm_room FOREIGN KEY (from_room_id) REFERENCES dorm_rooms (id) ON DELETE SET NULL,
  CONSTRAINT fk_rt_from_dorm FOREIGN KEY (from_dorm_id) REFERENCES dormitories (id) ON DELETE SET NULL,
  CONSTRAINT fk_rt_to_bed FOREIGN KEY (to_bed_id) REFERENCES beds (id) ON DELETE SET NULL,
  CONSTRAINT fk_rt_to_dorm_room FOREIGN KEY (to_room_id) REFERENCES dorm_rooms (id) ON DELETE SET NULL,
  CONSTRAINT fk_rt_to_dorm FOREIGN KEY (to_dorm_id) REFERENCES dormitories (id) ON DELETE SET NULL
);

CREATE TABLE boarding_roll_calls (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dormitory_id  INT UNSIGNED NOT NULL,
  call_date     DATE NOT NULL,
  call_slot     ENUM('morning','afternoon','evening','night') NOT NULL DEFAULT 'evening',
  expected_count SMALLINT NOT NULL DEFAULT 0,
  present_count  SMALLINT NOT NULL DEFAULT 0,
  absent_count   SMALLINT NOT NULL DEFAULT 0,
  conducted_by  INT UNSIGNED,
  conducted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status        ENUM('in_progress','completed','corrected') NOT NULL DEFAULT 'in_progress',
  notes         VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_roll_call (dormitory_id,call_date,call_slot),
  CONSTRAINT fk_roll_call_dorm FOREIGN KEY (dormitory_id) REFERENCES dormitories (id) ON DELETE CASCADE
);

CREATE TABLE roll_call_entries (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  roll_call_id  INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  bed_id        INT UNSIGNED,
  is_present    TINYINT(1) NOT NULL DEFAULT 1,
  reason        VARCHAR(255),
  followed_up   TINYINT(1) NOT NULL DEFAULT 0,
  notes         VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_roll_entry (roll_call_id,student_id),
  CONSTRAINT fk_roll_entry_call FOREIGN KEY (roll_call_id) REFERENCES boarding_roll_calls (id) ON DELETE CASCADE,
  CONSTRAINT fk_roll_entry_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_roll_entry_bed FOREIGN KEY (bed_id) REFERENCES beds (id) ON DELETE SET NULL
);

CREATE TABLE exeats (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id         INT UNSIGNED NOT NULL,
  exeat_number       VARCHAR(40),
  request_date       DATE NOT NULL,
  out_date           DATE NOT NULL,
  expected_return    DATETIME NOT NULL,
  actual_return      DATETIME,
  reason             VARCHAR(255) NOT NULL,
  destination        VARCHAR(255),
  accompanied_by     VARCHAR(140),
  guardian_notified  TINYINT(1) NOT NULL DEFAULT 0,
  guardian_id        INT UNSIGNED,
  requested_by       INT UNSIGNED,
  approved_by        INT UNSIGNED,
  approved_at        DATETIME,
  status             ENUM('requested','approved','rejected','out_returned','out_overdue','cancelled') NOT NULL DEFAULT 'requested',
  rejection_reason   VARCHAR(255),
  gate_out_verified_at  DATETIME,
  gate_out_verified_by  INT UNSIGNED,
  gate_in_verified_at   DATETIME,
  gate_in_verified_by   INT UNSIGNED,
  notes              VARCHAR(255),
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_exeat_number (exeat_number),
  KEY idx_exeat_student (student_id,status),
  KEY idx_exeat_dates (out_date,status),
  CONSTRAINT fk_exeat_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_exeat_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE SET NULL
);

CREATE TABLE maintenance_requests (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     INT UNSIGNED NOT NULL,
  dormitory_id  INT UNSIGNED,
  dorm_room_id       INT UNSIGNED,
  asset_item_id INT UNSIGNED,
  request_number VARCHAR(40),
  item_type     ENUM('bed','window','door','light','plumbing','roof','furniture','other') NOT NULL,
  title         VARCHAR(160) NOT NULL,
  description   TEXT,
  priority      ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  reported_by   INT UNSIGNED,
  reported_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  assigned_to   INT UNSIGNED,
  status        ENUM('reported','assigned','in_progress','on_hold','completed','cancelled') NOT NULL DEFAULT 'reported',
  completed_at  DATETIME,
  cost          DECIMAL(12,2),
  verified_by   INT UNSIGNED,
  remarks       VARCHAR(255),
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_maintenance_status (status,priority),
  CONSTRAINT fk_maint_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_maint_dorm FOREIGN KEY (dormitory_id) REFERENCES dormitories (id) ON DELETE CASCADE,
  CONSTRAINT fk_maint_dorm_room FOREIGN KEY (dorm_room_id) REFERENCES dorm_rooms (id) ON DELETE CASCADE
);

CREATE TABLE vehicles (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id         INT UNSIGNED NOT NULL,
  plate_number      VARCHAR(20)  NOT NULL,
  vehicle_type      ENUM('bus','van','pickup','ambulance','motorcycle','trailer') NOT NULL DEFAULT 'bus',
  make              VARCHAR(60),
  model             VARCHAR(60),
  model_year        SMALLINT,
  colour            VARCHAR(40),
  seating_capacity  SMALLINT     NOT NULL DEFAULT 0,
  fuel_type         ENUM('petrol','diesel','electric','hybrid') NOT NULL DEFAULT 'diesel',
  insurance_number  VARCHAR(60),
  insurance_expiry  DATE,
  insurance_premium DECIMAL(12,2),
  inspection_due    DATE,
  registration_expiry DATE,
  current_odometer  DECIMAL(10,1),
  assigned_driver_id INT UNSIGNED,
  status            ENUM('active','in_repair','grounded','sold','retired') NOT NULL DEFAULT 'active',
  photo_path        VARCHAR(255),
  notes             VARCHAR(255),
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plate (plate_number),
  KEY idx_vehicle_status (status),
  CONSTRAINT fk_vehicle_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE drivers (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  staff_id       INT UNSIGNED,
  full_name      VARCHAR(140) NOT NULL,
  phone          VARCHAR(40),
  licence_number VARCHAR(60)  NOT NULL,
  licence_class  VARCHAR(20),
  licence_expiry DATE,
  date_joined    DATE,
  is_external    TINYINT(1)   NOT NULL DEFAULT 0,
  vehicle_id     INT UNSIGNED,
  status         ENUM('active','on_leave','suspended','former') NOT NULL DEFAULT 'active',
  notes          VARCHAR(255),
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_driver_staff (staff_id),
  KEY idx_driver_licence (licence_expiry),
  CONSTRAINT fk_driver_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_driver_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_driver_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL
);

CREATE TABLE routes (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  route_code      VARCHAR(20)  NOT NULL,
  route_name      VARCHAR(140) NOT NULL,
  description     VARCHAR(255),
  distance_km     DECIMAL(6,2),
  pick_up_time    TIME,
  drop_off_time   TIME,
  fare_per_term   DECIMAL(12,2) NOT NULL DEFAULT 0,
  fare_per_trip   DECIMAL(12,2),
  vehicle_id      INT UNSIGNED,
  driver_id       INT UNSIGNED,
  status          ENUM('active','inactive','seasonal') NOT NULL DEFAULT 'active',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_route_code (school_id,route_code),
  CONSTRAINT fk_route_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_route_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL,
  CONSTRAINT fk_route_driver FOREIGN KEY (driver_id) REFERENCES drivers (id) ON DELETE SET NULL
);

CREATE TABLE route_stops (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  route_id        INT UNSIGNED NOT NULL,
  stop_name       VARCHAR(140) NOT NULL,
  landmark        VARCHAR(160),
  sequence_order  SMALLINT NOT NULL DEFAULT 1,
  pick_up_time    TIME,
  drop_off_time   TIME,
  fare_adjustment DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_route_stop (route_id,stop_name),
  CONSTRAINT fk_stop_route FOREIGN KEY (route_id) REFERENCES routes (id) ON DELETE CASCADE
);

CREATE TABLE student_route_allocations (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  route_id         INT UNSIGNED NOT NULL,
  stop_id          INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  pickup_point     VARCHAR(160),
  status           ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  allocated_by     INT UNSIGNED,
  allocated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes            VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_route (student_id,route_id,academic_year_id,term_id),
  KEY idx_route_students (route_id,status),
  CONSTRAINT fk_sra_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_sra_route FOREIGN KEY (route_id) REFERENCES routes (id) ON DELETE CASCADE,
  CONSTRAINT fk_sra_stop FOREIGN KEY (stop_id) REFERENCES route_stops (id) ON DELETE SET NULL,
  CONSTRAINT fk_sra_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_sra_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE
);

CREATE TABLE transport_charges (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  route_id         INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  charge_basis     ENUM('per_term','per_trip','per_distance','annual') NOT NULL DEFAULT 'per_term',
  amount           DECIMAL(12,2) NOT NULL DEFAULT 0,
  account_id       INT UNSIGNED,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_transport_charge (route_id,class_id,academic_year_id,term_id),
  CONSTRAINT fk_tc_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_route FOREIGN KEY (route_id) REFERENCES routes (id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE vehicle_maintenance (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id      INT UNSIGNED NOT NULL,
  maintenance_type ENUM('service','repair','inspection','tyres','insurance','body_work','other') NOT NULL,
  provider        VARCHAR(140),
  description     VARCHAR(255),
  odometer_reading DECIMAL(10,1),
  service_date    DATE NOT NULL,
  cost            DECIMAL(12,2) NOT NULL DEFAULT 0,
  next_due_date   DATE,
  next_due_odometer DECIMAL(10,1),
  document_path   VARCHAR(255),
  status          ENUM('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  approved_by     INT UNSIGNED,
  remarks         VARCHAR(255),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_maintenance_vehicle (vehicle_id,service_date),
  KEY idx_maintenance_due (next_due_date),
  CONSTRAINT fk_maintenance_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE
);

CREATE TABLE fuel_records (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id      INT UNSIGNED NOT NULL,
  fuel_date       DATE NOT NULL,
  litres          DECIMAL(8,2) NOT NULL DEFAULT 0,
  amount          DECIMAL(12,2) NOT NULL DEFAULT 0,
  price_per_litre DECIMAL(8,2),
  station         VARCHAR(120),
  odometer_reading DECIMAL(10,1),
  distance_since_last DECIMAL(8,1),
  consumption_per_km DECIMAL(8,2),
  driver_id       INT UNSIGNED,
  recorded_by     INT UNSIGNED,
  payment_ref     VARCHAR(80),
  remarks         VARCHAR(255),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fuel_vehicle (vehicle_id,fuel_date),
  CONSTRAINT fk_fuel_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE,
  CONSTRAINT fk_fuel_driver FOREIGN KEY (driver_id) REFERENCES drivers (id) ON DELETE SET NULL
);

CREATE TABLE item_categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(80)  NOT NULL,
  code        VARCHAR(20),
  parent_id   INT UNSIGNED,
  description VARCHAR(255),
  default_account_id INT UNSIGNED,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_item_category (school_id,name),
  CONSTRAINT fk_item_cat_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_item_cat_parent FOREIGN KEY (parent_id) REFERENCES item_categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_item_cat_account FOREIGN KEY (default_account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE items (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  item_code       VARCHAR(40)  NOT NULL,
  name            VARCHAR(160) NOT NULL,
  category_id     INT UNSIGNED,
  unit            VARCHAR(30)  NOT NULL DEFAULT 'piece',
  description     VARCHAR(255),
  reorder_level   DECIMAL(12,2) NOT NULL DEFAULT 0,
  max_level       DECIMAL(12,2),
  current_stock   DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit_cost       DECIMAL(12,2),
  unit_price      DECIMAL(12,2),
  account_id      INT UNSIGNED,
  is_perishable   TINYINT(1)   NOT NULL DEFAULT 0,
  is_stocked      TINYINT(1)   NOT NULL DEFAULT 1,
  lead_time_days  SMALLINT,
  status          ENUM('active','discontinued','locked') NOT NULL DEFAULT 'active',
  created_by      INT UNSIGNED,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_item_code (school_id,item_code),
  KEY idx_item_category (category_id),
  KEY idx_item_stock (current_stock,reorder_level),
  CONSTRAINT fk_item_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_item_category FOREIGN KEY (category_id) REFERENCES item_categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_item_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE suppliers (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  name            VARCHAR(160) NOT NULL,
  contact_person  VARCHAR(140),
  phone           VARCHAR(40),
  alternate_phone VARCHAR(40),
  email           VARCHAR(120),
  address         VARCHAR(255),
  tin             VARCHAR(40),
  items_supplied  VARCHAR(255),
  payment_terms   VARCHAR(120),
  rating          TINYINT,
  total_orders    INT UNSIGNED NOT NULL DEFAULT 0,
  total_value     DECIMAL(14,2) NOT NULL DEFAULT 0,
  status          ENUM('active','on_hold','blacklisted','former') NOT NULL DEFAULT 'active',
  notes           VARCHAR(255),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_supplier_name (name),
  CONSTRAINT fk_supplier_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE stock_movements (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  item_id          INT UNSIGNED NOT NULL,
  movement_type    ENUM('opening','receipt','issue','return','adjustment','transfer','loss','expiration') NOT NULL,
  quantity         DECIMAL(12,2) NOT NULL,
  unit_cost        DECIMAL(12,2),
  total_cost       DECIMAL(14,2),
  quantity_after   DECIMAL(12,2),
  movement_date    DATE NOT NULL,
  department_id    INT UNSIGNED,
  issued_to        VARCHAR(140),
  issued_to_staff_id INT UNSIGNED,
  reference_type   ENUM('purchase_order','manual','issue_slip','transfer','count','other') NOT NULL DEFAULT 'manual',
  reference_id     INT UNSIGNED,
  stock_location   VARCHAR(80),
  reason           VARCHAR(255),
  performed_by     INT UNSIGNED,
  approved_by      INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stock_item (item_id,movement_date),
  KEY idx_stock_type (movement_type,movement_date),
  CONSTRAINT fk_stock_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_staff FOREIGN KEY (issued_to_staff_id) REFERENCES staff (id) ON DELETE SET NULL
);

CREATE TABLE purchase_requests (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_number   VARCHAR(40)  NOT NULL,
  school_id        INT UNSIGNED NOT NULL,
  department_id    INT UNSIGNED,
  requested_by     INT UNSIGNED,
  request_date     DATE NOT NULL,
  needed_by        DATE,
  priority         ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  justification    VARCHAR(500),
  estimated_total  DECIMAL(14,2) NOT NULL DEFAULT 0,
  status           ENUM('draft','submitted','approved','partially_approved','rejected','ordered','received','cancelled') NOT NULL DEFAULT 'draft',
  current_step     SMALLINT NOT NULL DEFAULT 1,
  approved_by      INT UNSIGNED,
  approved_at      DATETIME,
  rejection_reason VARCHAR(255),
  purchase_order_id INT UNSIGNED,
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_request_number (school_id,request_number),
  KEY idx_request_status (status,priority),
  CONSTRAINT fk_pr_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
);

CREATE TABLE purchase_request_items (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_request_id INT UNSIGNED NOT NULL,
  item_id          INT UNSIGNED,
  description      VARCHAR(160) NOT NULL,
  quantity         DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit             VARCHAR(30)  NOT NULL DEFAULT 'piece',
  estimated_unit_cost DECIMAL(12,2),
  estimated_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  approved_quantity DECIMAL(12,2),
  remarks          VARCHAR(255),
  PRIMARY KEY (id),
  KEY idx_pri_request (purchase_request_id),
  CONSTRAINT fk_pri_request FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_pri_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE SET NULL
);

CREATE TABLE purchase_request_approvals (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_request_id INT UNSIGNED NOT NULL,
  step_order        SMALLINT NOT NULL DEFAULT 1,
  approver_role     VARCHAR(60),
  approver_staff_id INT UNSIGNED,
  decision          ENUM('pending','approved','rejected','skipped') NOT NULL DEFAULT 'pending',
  decision_date     DATETIME,
  comments          VARCHAR(255),
  action_by_ip      VARCHAR(45),
  PRIMARY KEY (id),
  UNIQUE KEY uq_request_step (purchase_request_id,step_order),
  KEY idx_approval_staff (approver_staff_id),
  CONSTRAINT fk_pra_request FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_pra_staff FOREIGN KEY (approver_staff_id) REFERENCES staff (id) ON DELETE SET NULL
);

CREATE TABLE purchase_orders (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_number         VARCHAR(40)  NOT NULL,
  school_id         INT UNSIGNED NOT NULL,
  purchase_request_id INT UNSIGNED,
  supplier_id       INT UNSIGNED NOT NULL,
  order_date        DATE NOT NULL,
  expected_delivery DATE,
  delivered_date    DATE,
  subtotal          DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_amount        DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount_amount   DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_amount      DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency          VARCHAR(10)  NOT NULL DEFAULT 'UGX',
  status            ENUM('draft','sent','acknowledged','partial','received','cancelled','closed') NOT NULL DEFAULT 'draft',
  created_by        INT UNSIGNED,
  approved_by       INT UNSIGNED,
  approved_at       DATETIME,
  sent_at           DATETIME,
  delivery_note     VARCHAR(255),
  notes             VARCHAR(255),
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_po_number (school_id,po_number),
  KEY idx_po_supplier (supplier_id,status),
  CONSTRAINT fk_po_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_po_request FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests (id) ON DELETE SET NULL,
  CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT
);

CREATE TABLE purchase_order_items (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_order_id INT UNSIGNED NOT NULL,
  item_id           INT UNSIGNED,
  description       VARCHAR(160) NOT NULL,
  quantity_ordered  DECIMAL(12,2) NOT NULL DEFAULT 0,
  quantity_received DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit              VARCHAR(30)  NOT NULL DEFAULT 'piece',
  unit_cost         DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount            DECIMAL(14,2) NOT NULL DEFAULT 0,
  received_date     DATE,
  item_condition   ENUM('good','damaged','partial','rejected'),
  remarks           VARCHAR(255),
  PRIMARY KEY (id),
  KEY idx_poi_order (purchase_order_id),
  CONSTRAINT fk_poi_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE SET NULL
);

CREATE TABLE assets (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id          INT UNSIGNED NOT NULL,
  asset_tag          VARCHAR(40)  NOT NULL,
  name               VARCHAR(160) NOT NULL,
  category_id        INT UNSIGNED,
  description        VARCHAR(255),
  serial_number      VARCHAR(80),
  purchase_date      DATE,
  purchase_cost      DECIMAL(14,2),
  current_value      DECIMAL(14,2),
  depreciation_rate  DECIMAL(5,2),
  accumulated_depreciation DECIMAL(14,2) NOT NULL DEFAULT 0,
  supplier_id        INT UNSIGNED,
  warranty_expiry    DATE,
  location           VARCHAR(120),
  department_id      INT UNSIGNED,
  condition_status   ENUM('new','good','fair','poor','damaged') NOT NULL DEFAULT 'good',
  assigned_to_staff_id INT UNSIGNED,
  status             ENUM('in_use','in_store','under_repair','disposed','sold','lost') NOT NULL DEFAULT 'in_use',
  disposal_date      DATE,
  disposal_amount    DECIMAL(14,2),
  disposal_reason    VARCHAR(255),
  notes              VARCHAR(255),
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asset_tag (school_id,asset_tag),
  KEY idx_asset_status (status),
  CONSTRAINT fk_asset_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_asset_category FOREIGN KEY (category_id) REFERENCES item_categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_asset_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL,
  CONSTRAINT fk_asset_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_asset_staff FOREIGN KEY (assigned_to_staff_id) REFERENCES staff (id) ON DELETE SET NULL
);

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

CREATE TABLE medical_records (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id         INT UNSIGNED NOT NULL,
  blood_group        VARCHAR(10),
  allergies          TEXT,
  chronic_conditions TEXT,
  current_medication TEXT,
  vaccination_status TEXT,
  last_vaccination_date DATE,
  immunisation_record TEXT,
  disability_status  ENUM('none','physical','visual','hearing','intellectual','multiple') NOT NULL DEFAULT 'none',
  disability_notes   VARCHAR(255),
  height_cm          DECIMAL(5,1),
  weight_kg          DECIMAL(5,1),
  last_checkup_date  DATE,
  health_insurance   VARCHAR(120),
  restricted_note    TEXT,
  is_confidential    TINYINT(1) NOT NULL DEFAULT 0,
  updated_by         INT UNSIGNED,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_medical_student (student_id),
  CONSTRAINT fk_medical_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE emergency_contacts (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id     INT UNSIGNED NOT NULL,
  name           VARCHAR(140) NOT NULL,
  relationship   VARCHAR(60)  NOT NULL,
  phone          VARCHAR(40)  NOT NULL,
  alternate_phone VARCHAR(40),
  address        VARCHAR(255),
  is_primary     TINYINT(1)   NOT NULL DEFAULT 0,
  notes          VARCHAR(255),
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_emergency_student (student_id,is_primary),
  CONSTRAINT fk_emergency_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE sickbay_visits (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  visit_date       DATE NOT NULL,
  time_in          TIME NOT NULL,
  time_out         TIME,
  duration_minutes SMALLINT,
  symptoms         TEXT,
  temperature_c    DECIMAL(4,1),
  diagnosis        VARCHAR(500),
  treatment_given  TEXT,
  bed_used         VARCHAR(30),
  seen_by          INT UNSIGNED,
  follow_up_date   DATE,
  follow_up_notes  VARCHAR(255),
  parent_informed  TINYINT(1) NOT NULL DEFAULT 0,
  parent_informed_at DATETIME,
  referred_to      ENUM('none','hospital','specialist','parent','clinic') NOT NULL DEFAULT 'none',
  status           ENUM('in_progress','discharged','referred','admitted') NOT NULL DEFAULT 'discharged',
  is_outbreak_case TINYINT(1) NOT NULL DEFAULT 0,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sickbay_date (visit_date),
  KEY idx_sickbay_student (student_id,visit_date),
  CONSTRAINT fk_sickbay_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_sickbay_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE medication_records (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  visit_id         BIGINT UNSIGNED,
  medication_name  VARCHAR(160) NOT NULL,
  dosage           VARCHAR(80)  NOT NULL,
  route            ENUM('oral','topical','injection','inhalation','eye_drops','ear_drops','other') NOT NULL DEFAULT 'oral',
  frequency        VARCHAR(80),
  reason           VARCHAR(255),
  administered_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  administered_by  INT UNSIGNED,
  given_by_request ENUM('self','parent','prescription','nurse') NOT NULL DEFAULT 'nurse',
  expiry_date      DATE,
  batch_number     VARCHAR(60),
  reaction_notes   VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_medication_student (student_id,administered_at),
  KEY idx_medication_visit (visit_id),
  CONSTRAINT fk_medication_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_medication_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_medication_visit FOREIGN KEY (visit_id) REFERENCES sickbay_visits (id) ON DELETE SET NULL
);

CREATE TABLE medical_incidents (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED,
  incident_number  VARCHAR(40),
  incident_date    DATE NOT NULL,
  incident_time    TIME,
  incident_type    ENUM('accident','injury','illness','emergency','food_poisoning','allergic_reaction','death','other') NOT NULL,
  location         VARCHAR(120),
  description      TEXT NOT NULL,
  action_taken     TEXT,
  witnesses        VARCHAR(255),
  reported_by      INT UNSIGNED,
  reported_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  parent_informed  TINYINT(1) NOT NULL DEFAULT 0,
  parent_informed_at DATETIME,
  parent_response  VARCHAR(255),
  referred_to_hospital TINYINT(1) NOT NULL DEFAULT 0,
  hospital_name    VARCHAR(160),
  transport_used   VARCHAR(120),
  incident_cost    DECIMAL(12,2),
  status           ENUM('reported','investigating','parent_involved','resolved','escalated') NOT NULL DEFAULT 'reported',
  closed_by        INT UNSIGNED,
  closed_at        DATETIME,
  documentation_path VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_incident_date (incident_date,incident_type),
  KEY idx_incident_student (student_id),
  CONSTRAINT fk_incident_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_incident_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE counselling_sessions (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id         INT UNSIGNED NOT NULL,
  student_id        INT UNSIGNED NOT NULL,
  session_number    SMALLINT NOT NULL DEFAULT 1,
  session_date      DATE NOT NULL,
  session_time      TIME,
  duration_minutes  SMALLINT,
  category          ENUM('academic','personal','social','emotional','career','behavioural','crisis','other') NOT NULL,
  referral_source   ENUM('self','teacher','parent','head_teacher','peer','discipline','other'),
  concerns          TEXT,
  observations      TEXT,
  action_plan       TEXT,
  resources_shared  VARCHAR(255),
  counsellor_staff_id INT UNSIGNED NOT NULL,
  follow_up_date    DATE,
  parent_involved   TINYINT(1) NOT NULL DEFAULT 0,
  is_confidential   TINYINT(1) NOT NULL DEFAULT 1,
  risk_level        ENUM('none','low','medium','high') NOT NULL DEFAULT 'none',
  status            ENUM('scheduled','held','follow_up','closed','referred') NOT NULL DEFAULT 'held',
  closed_date       DATE,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_counselling (student_id,session_number,session_date),
  KEY idx_counselling_counsellor (counsellor_staff_id,session_date),
  CONSTRAINT fk_counselling_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_counselling_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_counselling_staff FOREIGN KEY (counsellor_staff_id) REFERENCES staff (id) ON DELETE RESTRICT
);

CREATE TABLE welfare_records (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id         INT UNSIGNED NOT NULL,
  student_id        INT UNSIGNED NOT NULL,
  category          ENUM('orphan','special_needs','bullying','lack_of_basic_needs','abuse','neglect','mental_health','substance','other') NOT NULL,
  description       TEXT NOT NULL,
  date_identified   DATE NOT NULL,
  identified_by     INT UNSIGNED,
  support_provided  TEXT,
  support_type      ENUM('counselling','uniform','feeding','scholarship','medical','family_visit','referral','other'),
  referred_to       VARCHAR(160),
  external_agency   VARCHAR(160),
  follow_up_date    DATE,
  status            ENUM('open','in_progress','supported','closed','referred') NOT NULL DEFAULT 'open',
  responsible_staff INT UNSIGNED,
  is_confidential   TINYINT(1) NOT NULL DEFAULT 1,
  closed_at         DATETIME,
  notes             TEXT,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_welfare_student (student_id,status),
  KEY idx_welfare_category (category,status),
  CONSTRAINT fk_welfare_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_welfare_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_welfare_staff FOREIGN KEY (responsible_staff) REFERENCES staff (id) ON DELETE SET NULL
);

CREATE TABLE clubs (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  name             VARCHAR(140) NOT NULL,
  code             VARCHAR(20),
  club_category    ENUM('academic','religious','cultural','service','sports','interest','governance') NOT NULL DEFAULT 'interest',
  description      VARCHAR(500),
  patron_staff_id  INT UNSIGNED,
  co_patron_staff_id INT UNSIGNED,
  meeting_day      ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
  meeting_time     TIME,
  meeting_venue    VARCHAR(120),
  membership_limit SMALLINT,
  registration_fee DECIMAL(12,2),
  academic_year_id INT UNSIGNED,
  status           ENUM('active','inactive','dissolved') NOT NULL DEFAULT 'active',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_club (school_id,name),
  KEY idx_club_patron (patron_staff_id),
  CONSTRAINT fk_club_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_club_patron FOREIGN KEY (patron_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_club_copatron FOREIGN KEY (co_patron_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_club_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
);

CREATE TABLE club_memberships (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  club_id        INT UNSIGNED NOT NULL,
  student_id     INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  member_role    ENUM('member','executive','treasurer','secretary','president','captain') NOT NULL DEFAULT 'member',
  joined_date    DATE NOT NULL,
  left_date      DATE,
  status         ENUM('active','left','suspended','expelled') NOT NULL DEFAULT 'active',
  attendance_rate DECIMAL(5,2),
  remarks        VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_club_member (club_id,student_id,academic_year_id),
  KEY idx_club_student (student_id,status),
  CONSTRAINT fk_cm_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE
);

CREATE TABLE sports_teams (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  team_name        VARCHAR(140) NOT NULL,
  sport_category   ENUM('football','netball','basketball','athletics','volleyball','cricket','handball','swimming','other') NOT NULL,
  team_type        ENUM('boys','girls','mixed') NOT NULL DEFAULT 'boys',
  age_group        ENUM('junior','senior','mixed'),
  coach_staff_id   INT UNSIGNED,
  assistant_coach_staff_id INT UNSIGNED,
  season           VARCHAR(30),
  academic_year_id INT UNSIGNED,
  training_day     ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
  training_time    TIME,
  training_venue   VARCHAR(120),
  status           ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_team (school_id,team_name,season),
  CONSTRAINT fk_team_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_team_coach FOREIGN KEY (coach_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_team_assistant FOREIGN KEY (assistant_coach_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_team_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
);

CREATE TABLE team_members (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id        INT UNSIGNED NOT NULL,
  student_id     INT UNSIGNED NOT NULL,
  member_role    ENUM('player','captain','vice_captain','substitute','manager') NOT NULL DEFAULT 'player',
  shirt_number   SMALLINT,
  joined_date    DATE NOT NULL,
  status         ENUM('active','injured','dropped','left') NOT NULL DEFAULT 'active',
  remarks        VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_team_member (team_id,student_id),
  KEY idx_team_student (student_id),
  CONSTRAINT fk_tm_team FOREIGN KEY (team_id) REFERENCES sports_teams (id) ON DELETE CASCADE,
  CONSTRAINT fk_tm_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

CREATE TABLE fixtures (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id          INT UNSIGNED NOT NULL,
  opponent         VARCHAR(160) NOT NULL,
  opponent_type    ENUM('inter_school','house','national','friendly','tournament') NOT NULL DEFAULT 'inter_school',
  venue            VARCHAR(160),
  home_away        ENUM('home','away','neutral') NOT NULL DEFAULT 'home',
  fixture_date     DATE NOT NULL,
  start_time       TIME,
  competition_id   INT UNSIGNED,
  category         ENUM('league','friendly','cup','knockout','house','training') NOT NULL DEFAULT 'friendly',
  transport_required TINYINT(1) NOT NULL DEFAULT 0,
  status           ENUM('scheduled','live','played','postponed','cancelled','abandoned') NOT NULL DEFAULT 'scheduled',
  notes            VARCHAR(255),
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fixture_date (fixture_date,status),
  KEY idx_fixture_team (team_id,fixture_date),
  CONSTRAINT fk_fixture_team FOREIGN KEY (team_id) REFERENCES sports_teams (id) ON DELETE CASCADE
);

CREATE TABLE fixture_results (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fixture_id        INT UNSIGNED NOT NULL,
  our_score         SMALLINT NOT NULL DEFAULT 0,
  opponent_score    SMALLINT NOT NULL DEFAULT 0,
  result_outcome    ENUM('win','draw','loss'),
  half_time_score   VARCHAR(20),
  mvp_student_id    INT UNSIGNED,
  scorers           TEXT,
  team_of_the_day   TEXT,
  referee           VARCHAR(140),
  attendance_count  SMALLINT,
  remarks           VARCHAR(500),
  recorded_by       INT UNSIGNED,
  recorded_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fixture_result (fixture_id),
  CONSTRAINT fk_fr_fixture FOREIGN KEY (fixture_id) REFERENCES fixtures (id) ON DELETE CASCADE,
  CONSTRAINT fk_fr_student FOREIGN KEY (mvp_student_id) REFERENCES students (id) ON DELETE SET NULL
);

CREATE TABLE house_competition_points (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  house_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  category         ENUM('sports','academics','discipline','cultural','cleanliness','cooking','other') NOT NULL,
  event_name       VARCHAR(160) NOT NULL,
  points           DECIMAL(6,2) NOT NULL DEFAULT 0,
  points_type      ENUM('award','penalty') NOT NULL DEFAULT 'award',
  event_date       DATE NOT NULL,
  description      VARCHAR(255),
  awarded_by       INT UNSIGNED,
  source_fixture_id INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_house_points (house_id,academic_year_id,term_id),
  KEY idx_house_event_date (event_date),
  CONSTRAINT fk_hcp_house FOREIGN KEY (house_id) REFERENCES houses (id) ON DELETE CASCADE,
  CONSTRAINT fk_hcp_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_hcp_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE,
  CONSTRAINT fk_hcp_fixture FOREIGN KEY (source_fixture_id) REFERENCES fixtures (id) ON DELETE SET NULL
);

CREATE TABLE competitions (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  name             VARCHAR(180) NOT NULL,
  competition_type ENUM('music','drama','science_fair','sports','debate','quiz','agriculture','ict','other') NOT NULL,
  level            ENUM('school','district','regional','national','international') NOT NULL DEFAULT 'school',
  organiser        VARCHAR(160),
  venue            VARCHAR(160),
  start_date       DATE,
  end_date         DATE,
  budget           DECIMAL(14,2),
  transport_required TINYINT(1) NOT NULL DEFAULT 0,
  status           ENUM('planned','registration_open','participating','completed','cancelled') NOT NULL DEFAULT 'planned',
  results_summary  TEXT,
  created_by       INT UNSIGNED,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_competition_type (competition_type,level,start_date),
  CONSTRAINT fk_competition_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE competition_entries (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  competition_id   INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED,
  entry_name       VARCHAR(180),
  category         VARCHAR(120),
  participation_role ENUM('solo','team_member','team_leader','support','exhibitor') NOT NULL DEFAULT 'solo',
  club_id          INT UNSIGNED,
  team_id          INT UNSIGNED,
  registration_date DATE,
  fee_amount       DECIMAL(12,2),
  position         VARCHAR(40),
  score            DECIMAL(6,2),
  award            VARCHAR(120),
  certificate_path VARCHAR(255),
  remarks          VARCHAR(500),
  status           ENUM('registered','confirmed','participated','withdrew','disqualified') NOT NULL DEFAULT 'registered',
  PRIMARY KEY (id),
  UNIQUE KEY uq_entry (competition_id,student_id),
  KEY idx_entry_student (student_id),
  CONSTRAINT fk_entry_competition FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
  CONSTRAINT fk_entry_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_entry_club FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE SET NULL,
  CONSTRAINT fk_entry_team FOREIGN KEY (team_id) REFERENCES sports_teams (id) ON DELETE SET NULL
);

CREATE TABLE talent_records (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  talent_type      ENUM('music','sport','drama','art','leadership','academics','crafts','other') NOT NULL,
  talent_name      VARCHAR(160) NOT NULL,
  description      VARCHAR(500),
  level            ENUM('class','school','district','regional','national','international') NOT NULL DEFAULT 'school',
  achievement      VARCHAR(255),
  award_title      VARCHAR(160),
  award_date       DATE,
  certificate_path VARCHAR(255),
  leadership_role  VARCHAR(120),
  date_recorded    DATE NOT NULL,
  verified_by      INT UNSIGNED,
  is_testimonial_note TINYINT(1) NOT NULL DEFAULT 0,
  notes            VARCHAR(500),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_talent_student (student_id,talent_type),
  KEY idx_talent_level (level,award_date),
  CONSTRAINT fk_talent_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

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

CREATE TABLE products (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  product_code    VARCHAR(40)  NOT NULL,
  name            VARCHAR(160) NOT NULL,
  category        ENUM('uniform','scholastic','textbook','stationery','snack','beverage','supplies','other') NOT NULL DEFAULT 'snack',
  unit            VARCHAR(30)  NOT NULL DEFAULT 'piece',
  cost_price      DECIMAL(12,2) NOT NULL DEFAULT 0,
  selling_price   DECIMAL(12,2) NOT NULL DEFAULT 0,
  current_stock   DECIMAL(12,2) NOT NULL DEFAULT 0,
  reorder_level   DECIMAL(12,2) NOT NULL DEFAULT 0,
  account_id      INT UNSIGNED,
  track_stock     TINYINT(1)   NOT NULL DEFAULT 1,
  allow_student_purchase TINYINT(1) NOT NULL DEFAULT 1,
  vat_rate        DECIMAL(5,2) NOT NULL DEFAULT 0,
  barcode         VARCHAR(60),
  photo_path      VARCHAR(255),
  status          ENUM('active','inactive','out_of_stock','discontinued') NOT NULL DEFAULT 'active',
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_code (school_id,product_code),
  KEY idx_product_category (category,status),
  CONSTRAINT fk_product_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_product_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE product_stock_movements (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     INT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NOT NULL,
  movement_type ENUM('opening','purchase','sale','return','adjustment','loss','expiration') NOT NULL,
  quantity      DECIMAL(12,2) NOT NULL,
  unit_cost     DECIMAL(12,2),
  quantity_after DECIMAL(12,2),
  reference_type ENUM('sale','purchase_order','manual','count') NOT NULL DEFAULT 'manual',
  reference_id  INT UNSIGNED,
  movement_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason        VARCHAR(255),
  performed_by  INT UNSIGNED,
  PRIMARY KEY (id),
  KEY idx_psm_product (product_id,movement_date),
  CONSTRAINT fk_psm_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_psm_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
);

CREATE TABLE student_wallets (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id     INT UNSIGNED NOT NULL,
  wallet_number  VARCHAR(40)  NOT NULL,
  balance        DECIMAL(12,2) NOT NULL DEFAULT 0,
  daily_limit    DECIMAL(12,2),
  low_balance_threshold DECIMAL(12,2),
  account_id     INT UNSIGNED,
  status         ENUM('active','frozen','closed') NOT NULL DEFAULT 'active',
  is_pin_set     TINYINT(1) NOT NULL DEFAULT 0,
  pin_hash       VARCHAR(255),
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallet_student (student_id),
  UNIQUE KEY uq_wallet_number (wallet_number),
  CONSTRAINT fk_wallet_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_account FOREIGN KEY (account_id) REFERENCES gl_accounts (id) ON DELETE SET NULL
);

CREATE TABLE wallet_transactions (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  wallet_id        INT UNSIGNED NOT NULL,
  transaction_type ENUM('deposit','purchase','refund','adjustment','withdrawal','transfer') NOT NULL,
  amount           DECIMAL(12,2) NOT NULL,
  balance_before   DECIMAL(12,2),
  balance_after    DECIMAL(12,2),
  reference        VARCHAR(80),
  payment_id       INT UNSIGNED,
  sale_id          BIGINT UNSIGNED,
  guardian_id      INT UNSIGNED,
  performed_by     INT UNSIGNED,
  notes            VARCHAR(255),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wallet_txn (wallet_id,created_at),
  CONSTRAINT fk_wallet_txn_wallet FOREIGN KEY (wallet_id) REFERENCES student_wallets (id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_txn_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL,
  CONSTRAINT fk_wallet_txn_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE SET NULL
);

CREATE TABLE sales (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_number        VARCHAR(40)  NOT NULL,
  school_id          INT UNSIGNED NOT NULL,
  seller_staff_id    INT UNSIGNED,
  buyer_type         ENUM('student','staff','visitor','other') NOT NULL DEFAULT 'student',
  student_id         INT UNSIGNED,
  sale_date          DATE NOT NULL,
  sale_time          TIME NOT NULL,
  subtotal           DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount           DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount_paid        DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount_change      DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method     ENUM('cash','wallet','mobile_money','card','account','other') NOT NULL DEFAULT 'cash',
  wallet_transaction_id BIGINT UNSIGNED,
  payment_id         INT UNSIGNED,
  receipt_id         INT UNSIGNED,
  status             ENUM('completed','void','refunded','partial') NOT NULL DEFAULT 'completed',
  void_reason        VARCHAR(255),
  voided_by          INT UNSIGNED,
  notes              VARCHAR(255),
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sale_number (school_id,sale_number),
  KEY idx_sale_date (sale_date,status),
  KEY idx_sale_student (student_id,sale_date),
  CONSTRAINT fk_sale_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_sale_seller FOREIGN KEY (seller_staff_id) REFERENCES staff (id) ON DELETE SET NULL,
  CONSTRAINT fk_sale_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_sale_wallet_txn FOREIGN KEY (wallet_transaction_id) REFERENCES wallet_transactions (id) ON DELETE SET NULL,
  CONSTRAINT fk_sale_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL,
  CONSTRAINT fk_sale_receipt FOREIGN KEY (receipt_id) REFERENCES receipts (id) ON DELETE SET NULL
);

CREATE TABLE sale_items (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id       BIGINT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NOT NULL,
  quantity      DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit_price    DECIMAL(12,2) NOT NULL,
  unit_cost     DECIMAL(12,2),
  discount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_price   DECIMAL(12,2) NOT NULL,
  profit        DECIMAL(12,2),
  PRIMARY KEY (id),
  KEY idx_sale_item_sale (sale_id),
  KEY idx_sale_item_product (product_id),
  CONSTRAINT fk_sale_item_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE,
  CONSTRAINT fk_sale_item_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
);

CREATE TABLE shop_till_reconciliations (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL,
  recon_date     DATE NOT NULL,
  seller_staff_id INT UNSIGNED,
  expected_cash  DECIMAL(12,2) NOT NULL DEFAULT 0,
  counted_cash   DECIMAL(12,2) NOT NULL DEFAULT 0,
  difference     DECIMAL(12,2) NOT NULL DEFAULT 0,
  digital_total  DECIMAL(12,2) NOT NULL DEFAULT 0,
  sales_count    SMALLINT NOT NULL DEFAULT 0,
  reconciled_by  INT UNSIGNED,
  reconciled_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status         ENUM('open','matched','variance','approved') NOT NULL DEFAULT 'open',
  remarks        VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_till_recon (recon_date,seller_staff_id),
  CONSTRAINT fk_till_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_till_seller FOREIGN KEY (seller_staff_id) REFERENCES staff (id) ON DELETE SET NULL
);

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

CREATE TABLE IF NOT EXISTS uneb_levels (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(60) NOT NULL,
  code        VARCHAR(20) NOT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_level (school_id, code),
  CONSTRAINT fk_uneb_level_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_candidates (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  index_number    VARCHAR(30) NOT NULL,
  full_name       VARCHAR(160) NOT NULL,
  gender          ENUM('male','female') NOT NULL,
  date_of_birth   DATE,
  student_id      INT UNSIGNED,
  level_id        INT UNSIGNED NOT NULL,
  status          ENUM('registered','entered','withdrawn','completed') NOT NULL DEFAULT 'registered',
  notes           VARCHAR(255),
  registered_by   INT UNSIGNED,
  registered_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_index (school_id, index_number),
  KEY idx_uneb_level (level_id, status),
  CONSTRAINT fk_uneb_cand_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_cand_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_uneb_cand_level FOREIGN KEY (level_id) REFERENCES uneb_levels (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_candidate_subjects (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  candidate_id  INT UNSIGNED NOT NULL,
  subject_id    INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_cand_subject (candidate_id, subject_id),
  CONSTRAINT fk_uneb_subj_candidate FOREIGN KEY (candidate_id) REFERENCES uneb_candidates (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_subj_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_ca_submission (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  level_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  submission_type  ENUM('internal','uneb_portal') NOT NULL DEFAULT 'internal',
  status           ENUM('draft','submitted','confirmed') NOT NULL DEFAULT 'draft',
  notes            VARCHAR(255),
  submitted_by     INT UNSIGNED,
  submitted_at     DATETIME,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_uneb_ca_level (school_id, level_id, status),
  CONSTRAINT fk_uneb_ca_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_level_lvl FOREIGN KEY (level_id) REFERENCES uneb_levels (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS uneb_ca_subject_scores (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id  INT UNSIGNED NOT NULL,
  candidate_id   INT UNSIGNED NOT NULL,
  subject_id     INT UNSIGNED NOT NULL,
  ca_score       DECIMAL(5,2),
  project_score  DECIMAL(5,2),
  overall_score  DECIMAL(5,2),
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_ca_score (submission_id, candidate_id, subject_id),
  KEY idx_uneb_ca_score_cand (candidate_id),
  CONSTRAINT fk_uneb_ca_score_sub FOREIGN KEY (submission_id) REFERENCES uneb_ca_submission (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_score_cand FOREIGN KEY (candidate_id) REFERENCES uneb_candidates (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_score_subj FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_results (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  level_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED,
  exam_year        YEAR NOT NULL,
  index_number     VARCHAR(30) NOT NULL,
  full_name        VARCHAR(160) NOT NULL,
  gender           ENUM('male','female') NOT NULL,
  subject_results  TEXT,
  overall_level    VARCHAR(10),
  result_code      ENUM('Result 1','Result 2','Result 3'),
  ca_score_submitted TINYINT(1) NOT NULL DEFAULT 0,
  notes            VARCHAR(255),
  recorded_by      INT UNSIGNED,
  recorded_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_result (school_id, level_id, exam_year, index_number),
  KEY idx_uneb_result_year (exam_year, index_number),
  CONSTRAINT fk_uneb_res_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_res_level FOREIGN KEY (level_id) REFERENCES uneb_levels (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_res_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
);

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

ALTER TABLE admission_applications
  ADD CONSTRAINT fk_admission_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL;

ALTER TABLE users
  ADD CONSTRAINT fk_users_guardian FOREIGN KEY (guardian_id) REFERENCES guardians (id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_users_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL;

ALTER TABLE vehicles
  ADD CONSTRAINT fk_vehicle_driver FOREIGN KEY (assigned_driver_id) REFERENCES drivers (id) ON DELETE SET NULL;

ALTER TABLE purchase_requests
  ADD CONSTRAINT fk_pr_purchase_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE SET NULL;

ALTER TABLE attendance_notifications
  ADD CONSTRAINT fk_att_notif_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE SET NULL;

ALTER TABLE fixtures
  ADD CONSTRAINT fk_fixture_competition FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE SET NULL;

ALTER TABLE wallet_transactions
  ADD CONSTRAINT fk_wallet_txn_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE SET NULL;

INSERT INTO schools (name,motto,ownership_type,currency,currency_symbol,date_format,timezone)
VALUES ('Demo Secondary School','Excellence Through Hard Work','private','UGX','Ugx','d/m/Y','Africa/Kampala');

SET @school_id = (SELECT id FROM schools ORDER BY id LIMIT 1);

INSERT INTO roles (school_id,name,slug,description,is_system) VALUES
(@school_id,'Administrator','administrator','Full access to every module and setting',1),
(@school_id,'Head Teacher','head_teacher','Head of school: approves key decisions across modules',1),
(@school_id,'Deputy Head Teacher','deputy_head_teacher','Assists the head teacher in administration and discipline',1),
(@school_id,'Director of Studies','director_of_studies','Academic planning, timetabling, examinations and performance',1),
(@school_id,'Teacher','teacher','Records attendance, marks and continuous assessment',1),
(@school_id,'Bursar','bursar','Fees, payments, expenditure approvals and financial reports',1),
(@school_id,'Accountant','accountant','Records transactions, reconciliation and reporting',1),
(@school_id,'Registrar','registrar','Admission, student records, promotion and transfers',1),
(@school_id,'Librarian','librarian','Library circulation, stock and fines',1),
(@school_id,'Nurse','nurse','Health records, sick bay and welfare (restricted data)',1),
(@school_id,'Boarding Warden','warden','Dormitories, bed allocation, roll calls and exeats',1),
(@school_id,'Gate Officer','gate_officer','Visitors, gate passes, exeat verification and vehicle log',1),
(@school_id,'HR Officer','hr_officer','Staff records, contracts, leave and payroll preparation',1),
(@school_id,'Storekeeper','storekeeper','Stock, requisitions, purchase orders and assets',1),
(@school_id,'Parent','parent','Portal access to fees, results, attendance and messages',1),
(@school_id,'Student','student','Portal access to timetable, homework, results and notices',1);

INSERT INTO permissions (module_code,code,name,description,sort_order) VALUES
('administration','administration','Administration and System Configuration','School profile, users, roles, academic year, terms, classes, streams, departments, houses, settings, audit logs',1),
('students','students','Student Management','Admission, registration, profiles, allocation, promotion, transfers, discipline, documents, alumni',2),
('academics','academics','Academic Management','Curriculum, subjects, combinations, timetables, schemes, syllabus, homework, calendar',3),
('examinations','examinations','Examination and Assessment Management','Exams, marks, grading, results, report cards, transcripts, UNEB registration and submissions',4),
('uneb','uneb','UNEB Management','UNEB candidate registration, continuous assessment submission, national results records',4.1),
('finance','finance','Finance and Fees Management','Fee structures, invoicing, payments, receipts, clearance, budgets, expenses, statements',5),
('online_payments','online_payments','Online Payment Integration','Mobile money and bank gateways, payment references, auto posting, transaction logs',6),
('hr','hr','Human Resource and Payroll Management','Staff records, contracts, leave, payroll, statutory deductions, appraisals',7),
('attendance','attendance','Attendance Management','Daily and lesson attendance, staff attendance, lateness, absentee follow-up, notifications',8),
('library','library','Library Management','Books, copies, borrowing, returns, overdue notices and fines',9),
('boarding','boarding','Boarding Management','Dormitories, rooms, beds, boarding registration, roll calls, exeats, maintenance',10),
('transport','transport','Transport Management','Vehicles, drivers, routes, student route allocation, transport fees, maintenance, fuel',11),
('inventory','inventory','Inventory and Procurement Management','Items, stock, suppliers, requisitions, approvals, purchase orders, assets',12),
('communication','communication','Communication Management','Announcements, SMS, email, portal messages, events, PTA, message history',13),
('health','health','Health and Student Welfare Management','Medical records, sick bay, medication, incidents, counselling, welfare (restricted)',14),
('portal','portal','Parent and Student Portal','Parent and student self-service access and portal activity',15),
('cocurricular','cocurricular','Co-curricular Activities Management','Clubs, sports, house competitions, events, talents',16),
('gate','gate','Visitor and Gate Management','Visitors, gate passes, exeat verification, vehicle log, security incidents',17),
('canteen','canteen','Canteen and School Shop Management','Product catalogue, sales, student wallet, stock control, sales reports',18),
('compliance','compliance','Government Compliance and Capitation','EMIS returns, enrolment returns, UPE/USE grants, inspections, licences',19),
('reports','reports','Reports and Analytics','Consolidated reports, analytics, export and role-based dashboards',20),
('security','security','System Security, Data Management and Support','Password policy, sessions, backup, import/export, data protection, support tickets',21);

INSERT INTO role_permissions
  (role_id,permission_id,can_view,can_add,can_edit,can_approve,can_print,can_delete)
SELECT r.id, p.id,
  IF(FIND_IN_SET(p.module_code, m.full_list) OR FIND_IN_SET(p.module_code, m.manage_list) OR FIND_IN_SET(p.module_code, m.view_list), 1, 0),
  IF(FIND_IN_SET(p.module_code, m.full_list) OR FIND_IN_SET(p.module_code, m.manage_list), 1, 0),
  IF(FIND_IN_SET(p.module_code, m.full_list) OR FIND_IN_SET(p.module_code, m.manage_list), 1, 0),
  IF(FIND_IN_SET(p.module_code, m.full_list) OR FIND_IN_SET(p.module_code, m.manage_list), 1, 0),
  IF(FIND_IN_SET(p.module_code, m.full_list) OR FIND_IN_SET(p.module_code, m.manage_list) OR FIND_IN_SET(p.module_code, m.view_list), 1, 0),
  IF(FIND_IN_SET(p.module_code, m.full_list), 1, 0)
FROM roles r
CROSS JOIN permissions p
JOIN (
  SELECT 'administrator' AS slug,
    'administration,students,academics,examinations,uneb,finance,online_payments,hr,attendance,library,boarding,transport,inventory,communication,health,portal,cocurricular,gate,canteen,compliance,reports,security' AS full_list,
    '' AS manage_list, '' AS view_list
  UNION ALL SELECT 'head_teacher',
    'students,academics,examinations,attendance,communication,reports,cocurricular,library,boarding,health,portal,transport,gate',
    'administration,finance,online_payments,hr,inventory,canteen,compliance,security',''
  UNION ALL SELECT 'deputy_head_teacher',
    'students,academics,examinations,attendance,communication,cocurricular,library,boarding,health,portal,gate,transport',
    'reports,compliance,inventory',
    'administration,finance,online_payments,hr,canteen,security'
  UNION ALL SELECT 'director_of_studies',
    'academics,examinations,attendance,library,cocurricular,communication,portal',
    'students,reports',
    'administration,finance,online_payments,hr,inventory,boarding,transport,health,gate,canteen,compliance,security'
  UNION ALL SELECT 'teacher',
    '',
    'attendance,academics,examinations,cocurricular,communication',
    'administration,students,library,boarding,health,reports,portal,transport'
  UNION ALL SELECT 'bursar',
    'finance,online_payments,canteen',
    'reports,compliance,inventory,communication,administration',
    'students,academics,examinations,attendance,portal,transport,boarding,health,gate,hr,security,library,cocurricular'
  UNION ALL SELECT 'accountant',
    'finance,online_payments',
    'reports,inventory,canteen,communication',
    'students,administration,portal,compliance,hr,attendance,academics,examinations'
  UNION ALL SELECT 'registrar',
    'students',
    'academics,compliance,reports,communication,administration',
    'examinations,attendance,portal,library,health,boarding,transport,gate,cocurricular,finance,hr,inventory,security,online_payments,canteen'
  UNION ALL SELECT 'librarian',
    'library','reports,communication',
    'students,academics,portal,attendance,administration'
  UNION ALL SELECT 'nurse',
    'health','reports,communication',
    'students,attendance,boarding,portal,administration,examinations'
  UNION ALL SELECT 'warden',
    'boarding','attendance,communication,gate,transport',
    'students,health,portal,reports,examinations,administration'
  UNION ALL SELECT 'gate_officer',
    'gate','communication,transport',
    'students,boarding,portal,administration,attendance'
  UNION ALL SELECT 'hr_officer',
    'hr','reports,communication,administration',
    'students,academics,attendance,finance,security,portal'
  UNION ALL SELECT 'storekeeper',
    'inventory','reports,communication',
    'students,administration,transport,canteen,finance'
  UNION ALL SELECT 'parent','','','portal'
  UNION ALL SELECT 'student','','','portal'
) m ON m.slug = r.slug
WHERE r.school_id = @school_id;

INSERT INTO users (school_id,username,full_name,email,user_type,role_id,must_change_password,is_active)
SELECT @school_id,'admin','System Administrator','admin@demo.sc.ug','staff',
       (SELECT id FROM roles WHERE slug='administrator' AND school_id=@school_id), 1, 1;

INSERT INTO password_history (user_id,password_hash,change_reason)
SELECT u.id, 'FORCE_CHANGE_ON_FIRST_LOGIN', 'admin_reset'
FROM users u WHERE u.username='admin' AND u.school_id=@school_id;

INSERT INTO academic_years (school_id,name,start_date,end_date,is_active,status) VALUES
(@school_id,'2026','2026-01-26','2026-12-11',1,'active');

SET @year_id = (SELECT id FROM academic_years WHERE school_id=@school_id ORDER BY id LIMIT 1);

INSERT INTO terms (academic_year_id,name,term_number,start_date,end_date,report_date,is_active,status) VALUES
(@year_id,'Term 1',1,'2026-01-26','2026-04-10','2026-04-17',0,'closed'),
(@year_id,'Term 2',2,'2026-04-27','2026-07-31','2026-08-07',0,'closed'),
(@year_id,'Term 3',3,'2026-08-24','2026-12-11','2026-12-18',1,'active');

INSERT INTO classes (school_id,name,level,order_index) VALUES
(@school_id,'S1','O-Level',1),
(@school_id,'S2','O-Level',2),
(@school_id,'S3','O-Level',3),
(@school_id,'S4','O-Level',4),
(@school_id,'S5','A-Level',5),
(@school_id,'S6','A-Level',6);

INSERT INTO streams (class_id,name,capacity)
SELECT c.id, s.stream_name, 50
FROM classes c
JOIN (SELECT 'East' AS stream_name UNION ALL SELECT 'West') s
WHERE c.school_id = @school_id;

INSERT INTO departments (school_id,name,type,description) VALUES
(@school_id,'Sciences','academic','Physics, Chemistry, Biology and practical sciences'),
(@school_id,'Mathematics','academic','Mathematics and ICT'),
(@school_id,'Languages','academic','English, Literature, Luganda and French'),
(@school_id,'Humanities','academic','History, Geography, Religious Education and Social Studies'),
(@school_id,'Finance','non-academic','Fees, accounts and procurement'),
(@school_id,'Boarding','non-academic','Dormitories, welfare and discipline'),
(@school_id,'Sports and Co-curricular','non-academic','Games, clubs and house activities');

INSERT INTO houses (school_id,name,colour,motto) VALUES
(@school_id,'Nile','Blue','Steady Flow'),
(@school_id,'Victoria','Green','Greatness'),
(@school_id,'Albert','Yellow','Strength'),
(@school_id,'Kyoga','Red','Unity');

INSERT INTO curricula (school_id,name,code,level_scope,description,status) VALUES
(@school_id,'New Lower Secondary Curriculum (Competency-Based)','CBC_LOWER','O-Level','S1-S4 competency-based curriculum with 20% school continuous assessment and 80% UNEB end-of-cycle examination','active'),
(@school_id,'Uganda Advanced Certificate of Education (A-Level)','UACE','A-Level','Current S5-S6 A-Level programme pending competency-based reform (reported around 2028)','active');

INSERT INTO subjects (school_id,code,name,short_name,level,category,department_id,order_index) VALUES
(@school_id,'MAT','Mathematics','Maths','O-Level','compulsory',(SELECT id FROM departments WHERE school_id=@school_id AND name='Mathematics'),1),
(@school_id,'ENG','English Language','English','O-Level','compulsory',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),2),
(@school_id,'SST','Social Studies','SST','O-Level','compulsory',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),3),
(@school_id,'CRE','Religious Education (CRE)','CRE','O-Level','compulsory',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),4),
(@school_id,'PHY','Physics','Physics','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),5),
(@school_id,'CHE','Chemistry','Chemistry','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),6),
(@school_id,'BIO','Biology','Biology','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),7),
(@school_id,'GEO','Geography','Geography','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),8),
(@school_id,'HIS','History','History','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),9),
(@school_id,'ICT','Information and Communication Technology','ICT','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Mathematics'),10),
(@school_id,'AGR','Agriculture','Agriculture','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),11),
(@school_id,'LIT','Literature in English','Literature','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),12),
(@school_id,'LUG','Luganda','Luganda','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),13),
(@school_id,'FRE','French','French','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),14),
(@school_id,'MUS','Music','Music','O-Level','elective',NULL,15),
(@school_id,'ART','Art and Design','Art','O-Level','elective',NULL,16),
(@school_id,'PE','Physical Education','PE','O-Level','elective',NULL,17),
(@school_id,'APHY','Physics (A-Level)','Physics A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),21),
(@school_id,'ACHE','Chemistry (A-Level)','Chemistry A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),22),
(@school_id,'AMAT','Mathematics (A-Level)','Maths A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Mathematics'),23),
(@school_id,'ABIO','Biology (A-Level)','Biology A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),24),
(@school_id,'AHIS','History (A-Level)','History A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),25),
(@school_id,'AGEO','Geography (A-Level)','Geography A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),26),
(@school_id,'AECON','Economics (A-Level)','Economics A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),27),
(@school_id,'ALIT','Literature in English (A-Level)','Literature A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),28),
(@school_id,'ADIV','Divinity (A-Level)','Divinity','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),29),
(@school_id,'AAGR','Agriculture (A-Level)','Agriculture A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),30),
(@school_id,'AFRE','French (A-Level)','French A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),31),
(@school_id,'ASUBICT','Subsidiary ICT','Sub ICT','A-Level','subsidiary',(SELECT id FROM departments WHERE school_id=@school_id AND name='Mathematics'),41),
(@school_id,'ASUBMATH','Subsidiary Mathematics','Sub Maths','A-Level','subsidiary',(SELECT id FROM departments WHERE school_id=@school_id AND name='Mathematics'),42);

INSERT INTO alevel_combinations (school_id,code,name,description) VALUES
(@school_id,'PCM','Physics, Chemistry, Mathematics','Science combination for engineering and physical sciences'),
(@school_id,'BCG','Biology, Chemistry, Geography','Science combination for health and environmental sciences'),
(@school_id,'BCM','Biology, Chemistry, Mathematics','Science combination for medical and biological sciences'),
(@school_id,'HEG','History, Economics, Geography','Arts/social sciences combination');

INSERT INTO combination_subjects (combination_id,subject_id,subject_role,is_compulsory,order_index)
SELECT cb.id, s.id, 'principal', 1, s.order_index
FROM alevel_combinations cb
JOIN subjects s ON s.school_id = cb.school_id AND s.code IN ('APHY','ACHE','AMAT')
WHERE cb.code = 'PCM' AND cb.school_id = @school_id;

INSERT INTO combination_subjects (combination_id,subject_id,subject_role,is_compulsory,order_index)
SELECT cb.id, s.id, 'principal', 1, s.order_index
FROM alevel_combinations cb
JOIN subjects s ON s.school_id = cb.school_id AND s.code IN ('ABIO','ACHE','AGEO')
WHERE cb.code = 'BCG' AND cb.school_id = @school_id;

INSERT INTO combination_subjects (combination_id,subject_id,subject_role,is_compulsory,order_index)
SELECT cb.id, s.id, 'principal', 1, s.order_index
FROM alevel_combinations cb
JOIN subjects s ON s.school_id = cb.school_id AND s.code IN ('ABIO','ACHE','AMAT')
WHERE cb.code = 'BCM' AND cb.school_id = @school_id;

INSERT INTO combination_subjects (combination_id,subject_id,subject_role,is_compulsory,order_index)
SELECT cb.id, s.id, 'principal', 1, s.order_index
FROM alevel_combinations cb
JOIN subjects s ON s.school_id = cb.school_id AND s.code IN ('AHIS','AECON','AGEO')
WHERE cb.code = 'HEG' AND cb.school_id = @school_id;

INSERT INTO combination_subjects (combination_id,subject_id,subject_role,is_compulsory,order_index)
SELECT cb.id, s.id, 'subsidiary', 0, 40
FROM alevel_combinations cb
JOIN subjects s ON s.school_id = cb.school_id AND s.code = 'ASUBICT'
WHERE cb.school_id = @school_id;

INSERT INTO generic_skills (school_id,code,name,description,level_scope) VALUES
(@school_id,'COMM','Communication','Listening, speaking, reading and writing effectively','O-Level'),
(@school_id,'CRIT','Critical Thinking and Problem Solving','Analysing situations and proposing solutions','O-Level'),
(@school_id,'COLL','Collaboration and Teamwork','Working with others towards a shared goal','O-Level'),
(@school_id,'SELF','Self-Management and Independence','Planning, organising and taking responsibility for learning','O-Level'),
(@school_id,'DIGI','Digital Literacy','Responsible and effective use of technology','O-Level'),
(@school_id,'CITZ','Citizenship and Values','Participation and responsibility in the community','O-Level'),
(@school_id,'CREA','Creativity and Innovation','Generating and developing original ideas','O-Level'),
(@school_id,'LEAD','Leadership','Guiding and influencing others positively','Both');

INSERT INTO grading_scales (school_id,code,name,level_scope,uses_division,uses_points,description,is_default) VALUES
(@school_id,'CBC_AE','CBC Achievement Levels A-E (S1-S4)','O-Level',0,0,'Five achievement levels reported with descriptors. Divisions and aggregates are not used for new-curriculum learners.',1),
(@school_id,'UACE','UACE A-Level Grading and Points (S5-S6)','A-Level',0,1,'Current UACE principal/subsidiary grading and points. Rules are configurable pending the A-Level curriculum reform.',1),
(@school_id,'LEGACY_D1_F9','Legacy D1-F9 Scale (historical records only)','Legacy',1,1,'Former UCE scale kept only for old report cards, transcripts and alumni records.',0);

INSERT INTO grade_bands (grading_scale_id,band_order,min_percent,max_percent,grade_code,grade_name,descriptor,points,division,is_pass)
SELECT s.id, b.band_order, b.min_percent, b.max_percent, b.grade_code, b.grade_name, b.descriptor, NULL, NULL, b.is_pass
FROM grading_scales s
JOIN (
  SELECT 1 AS band_order, 80.00 AS min_percent, 100.00 AS max_percent, 'A' AS grade_code, 'Exceptional' AS grade_name,
         'Exceptional: the learner consistently demonstrates mastery of the competencies with independence and insight.' AS descriptor, 1 AS is_pass
  UNION ALL SELECT 2, 70.00, 79.99, 'B', 'Outstanding',
         'Outstanding: the learner demonstrates strong competence and only needs occasional support.' , 1
  UNION ALL SELECT 3, 60.00, 69.99, 'C', 'Satisfactory',
         'Satisfactory: the learner demonstrates the expected competencies with some support.', 1
  UNION ALL SELECT 4, 50.00, 59.99, 'D', 'Basic',
         'Basic: the learner demonstrates minimum competence and needs regular support.', 1
  UNION ALL SELECT 5, 0.00, 49.99, 'E', 'Elementary',
         'Elementary: the learner has not yet demonstrated the required competencies and needs remedial support.', 0
) b
WHERE s.school_id = @school_id AND s.code = 'CBC_AE';

INSERT INTO grade_bands (grading_scale_id,band_order,min_percent,max_percent,grade_code,grade_name,descriptor,points,division,is_pass)
SELECT s.id, b.band_order, b.min_percent, b.max_percent, b.grade_code, b.grade_name, b.descriptor, b.points, NULL, b.is_pass
FROM grading_scales s
JOIN (
  SELECT 1 AS band_order, 80.00 AS min_percent, 100.00 AS max_percent, 'A' AS grade_code, 'Grade A' AS grade_name, 'Distinction' AS descriptor, 5.00 AS points, 1 AS is_pass
  UNION ALL SELECT 2, 70.00, 79.99, 'B', 'Grade B', 'Credit', 4.00, 1
  UNION ALL SELECT 3, 60.00, 69.99, 'C', 'Grade C', 'Pass (ordinary)', 3.00, 1
  UNION ALL SELECT 4, 50.00, 59.99, 'D', 'Grade D', 'Marginal pass', 2.00, 1
  UNION ALL SELECT 5, 40.00, 49.99, 'E', 'Grade E', 'Subsidiary pass', 1.00, 1
  UNION ALL SELECT 6, 0.00, 39.99, 'F', 'Grade F', 'Fail', 0.00, 0
) b
WHERE s.school_id = @school_id AND s.code = 'UACE';

INSERT INTO grade_bands (grading_scale_id,band_order,min_percent,max_percent,grade_code,grade_name,descriptor,points,division,is_pass)
SELECT s.id, b.band_order, b.min_percent, b.max_percent, b.grade_code, b.grade_name, NULL, b.points, NULL, b.is_pass
FROM grading_scales s
JOIN (
  SELECT 1 AS band_order, 80.00 AS min_percent, 100.00 AS max_percent, 'D1' AS grade_code, 'Distinction 1' AS grade_name, 1.00 AS points, 1 AS is_pass
  UNION ALL SELECT 2, 75.00, 79.99, 'D2', 'Distinction 2', 2.00, 1
  UNION ALL SELECT 3, 70.00, 74.99, 'C3', 'Credit 3', 3.00, 1
  UNION ALL SELECT 4, 65.00, 69.99, 'C4', 'Credit 4', 4.00, 1
  UNION ALL SELECT 5, 60.00, 64.99, 'C5', 'Credit 5', 5.00, 1
  UNION ALL SELECT 6, 55.00, 59.99, 'C6', 'Credit 6', 6.00, 1
  UNION ALL SELECT 7, 50.00, 54.99, 'P7', 'Pass 7', 7.00, 1
  UNION ALL SELECT 8, 45.00, 49.99, 'P8', 'Pass 8', 8.00, 1
  UNION ALL SELECT 9, 0.00, 44.99, 'F9', 'Fail 9', 9.00, 0
) b
WHERE s.school_id = @school_id AND s.code = 'LEGACY_D1_F9';

INSERT INTO assessment_components (school_id,level_scope,component_code,name,weight_percent,scale_min,scale_max,applies_to,notes) VALUES
(@school_id,'O-Level','school_ca','School-based Continuous Assessment',20.00,0,100,'official_results','Accumulated 1-3 scale scores for activities, projects and integrated tasks, converted to a percentage'),
(@school_id,'O-Level','project_work','Project Work (part of school CA)',0.00,1,3,'official_results','Mandatory component recorded per subject; feeds the 20% school-based score'),
(@school_id,'O-Level','uneb_exam','UNEB End-of-Cycle Examination',80.00,0,100,'official_results','UCE examination contribution set by UNEB'),
(@school_id,'O-Level','midterm','Mid-Term Assessment',30.00,0,100,'internal_reports','Internal term reports only'),
(@school_id,'O-Level','end_of_term','End-of-Term Assessment',70.00,0,100,'internal_reports','Internal term reports only'),
(@school_id,'A-Level','midterm','Mid-Term Assessment',40.00,0,100,'internal_reports','Internal weights for S5-S6 reports; UNEB sets official A-Level grading'),
(@school_id,'A-Level','end_of_term','End-of-Term Examination',60.00,0,100,'internal_reports','Internal weights for S5-S6 reports; UNEB sets official A-Level grading');

INSERT INTO payment_methods (school_id,code,name,channel,requires_verification,sort_order) VALUES
(@school_id,'cash','Cash at School','cash',0,1),
(@school_id,'bank_deposit','Bank Deposit','bank',1,2),
(@school_id,'cheque','Cheque','cheque',1,3),
(@school_id,'mtn_momo','MTN Mobile Money','mobile_money',0,4),
(@school_id,'airtel_money','Airtel Money','mobile_money',0,5),
(@school_id,'online_gateway','Online Payment Gateway','online',0,6);

INSERT INTO settings (school_id,setting_group,setting_key,setting_value,value_type,description) VALUES
(@school_id,'general','date_format','d/m/Y','string','Display format for dates'),
(@school_id,'general','currency','UGX','string','Default currency code'),
(@school_id,'general','currency_symbol','Ugx','string','Currency shown on documents'),
(@school_id,'general','academic_week_starts','Monday','string','First day of the academic week'),
(@school_id,'general','time_format','12h','string','Time display format'),
(@school_id,'numbering','admission_prefix','ADM/','string','Prefix for admission numbers'),
(@school_id,'numbering','receipt_prefix','REC/','string','Prefix for receipt numbers'),
(@school_id,'numbering','invoice_prefix','INV/','string','Prefix for fee invoice numbers'),
(@school_id,'numbering','payment_prefix','PAY/','string','Prefix for payment references'),
(@school_id,'numbering','staff_prefix','STF/','string','Prefix for staff numbers'),
(@school_id,'fees','fee_clearance_required','1','bool','Block report cards and examinations for unpaid balances (1 = on)'),
(@school_id,'fees','fee_reminder_days_before','7','int','Days before due date to send reminders'),
(@school_id,'fees','allow_part_payment','1','bool','Allow part payments against invoices'),
(@school_id,'examinations','show_ranking_o_level','0','bool','Ranking is switched off by default for S1-S4 (0 = off)'),
(@school_id,'examinations','show_divisions','0','bool','Divisions/aggregates are not shown for new-curriculum learners'),
(@school_id,'examinations','min_subjects_for_uce','8','int','Minimum subjects for a UCE candidate'),
(@school_id,'security','password_min_length','8','int','Minimum password length'),
(@school_id,'security','password_expiry_days','90','int','Days before a password must be changed'),
(@school_id,'security','max_failed_logins','5','int','Failed attempts before an account is locked'),
(@school_id,'security','session_timeout_minutes','30','int','Automatic logout after inactivity'),
(@school_id,'security','require_two_factor_admin','0','bool','Require 2FA for administrator and bursar (optional)'),
(@school_id,'communication','sms_sender_name','SCHOOL','string','SMS sender ID (alpha-numeric)'),
(@school_id,'communication','email_from_address','no-reply@demo.sc.ug','string','From address for outgoing email'),
(@school_id,'communication','default_notification_channel','sms','string','Default channel for parent notifications'),
(@school_id,'reporting','report_card_footer','Report generated by SSMS','string','Footer text printed on report cards'),
(@school_id,'backup','backup_schedule','daily','string','Backup frequency: daily, weekly, manual'),
(@school_id,'data_protection','retain_alumni_data','1','bool','Keep alumni records after completion'),
(@school_id,'data_protection','consent_required_photo_use','1','bool','Require recorded consent before publishing student photos');

INSERT INTO settings (school_id,setting_group,setting_key,setting_value,value_type,description) VALUES
(@school_id,'smtp','smtp_host','','string','SMTP server hostname for outgoing email'),
(@school_id,'smtp','smtp_port','587','int','SMTP server port'),
(@school_id,'smtp','smtp_username','','string','SMTP authentication username'),
(@school_id,'smtp','smtp_password','','string','SMTP authentication password (stored as plain text - consider encryption in production)'),
(@school_id,'smtp','smtp_encryption','tls','string','Encryption method: none, ssl, or tls'),
(@school_id,'smtp','smtp_from_name','SSMS','string','Display name shown as email sender'),
(@school_id,'smtp','smtp_from_address','no-reply@demo.sc.ug','string','Email address shown as sender'),

(@school_id,'sms','sms_provider','','string','SMS provider name (e.g. Africa\'s Talking, Twilio, local gateway)'),
(@school_id,'sms','sms_api_key','','string','SMS API key or username'),
(@school_id,'sms','sms_api_secret','','string','SMS API secret or password'),
(@school_id,'sms','sms_sender_id','SSMS','string','SMS sender ID displayed on recipient phone'),
(@school_id,'sms','sms_gateway_url','','string','SMS gateway HTTP endpoint URL'),
(@school_id,'sms','sms_default_language','en','string','Default language for SMS templates'),

(@school_id,'grading','grading_pass_mark','50','int','Minimum percentage score to be considered a pass'),
(@school_id,'grading','default_grading_scale','CBC_AE','string','Default grading scale code used for new learners'),
(@school_id,'grading','show_class_ranking','0','bool','Display class ranking on report cards (0 = off)'),
(@school_id,'grading','round_marks','0','bool','Round computed marks to nearest whole number'),
(@school_id,'grading','include_project_in_ca','1','bool','Include project work in continuous assessment score'),

(@school_id,'portal','portal_parent_access','1','bool','Allow parents to access the portal (1 = on)'),
(@school_id,'portal','portal_student_access','1','bool','Allow students to access the portal (1 = on)'),
(@school_id,'portal','portal_publish_results','0','bool','Automatically publish results to portal when processed'),
(@school_id,'portal','portal_show_fee_balance','1','bool','Show fee balances to parents in portal'),
(@school_id,'portal','portal_show_attendance','1','bool','Show attendance records to parents in portal'),
(@school_id,'portal','portal_session_timeout','15','int','Portal session timeout in minutes'),

(@school_id,'uneb','uneb_center_number','','string','UNEB center/examination number assigned to the school'),
(@school_id,'uneb','uneb_school_code','','string','UNEB school code for candidate registration'),
(@school_id,'uneb','uneb_district','','string','District where the school is registered with UNEB'),
(@school_id,'uneb','ca_submission_deadline','','date','Deadline for submitting continuous assessment scores to UNEB'),
(@school_id,'uneb','project_work_mandatory','1','bool','Project work is mandatory for UCE candidates (1 = yes)'),
(@school_id,'uneb','min_subjects_uce','8','int','Minimum number of subjects for UCE certification'),

(@school_id,'academic','lessons_per_day','8','int','Default number of teaching periods per day'),
(@school_id,'academic','lesson_duration_minutes','40','int','Duration of each teaching period in minutes'),
(@school_id,'academic','default_class_teacher','','string','Default class teacher assignment policy'),
(@school_id,'academic','terms_per_year','3','int','Number of academic terms per year'),
(@school_id,'academic','promotion_min_average','50','int','Minimum average score required for promotion'),
(@school_id,'academic','allow_repeaters','1','bool','Allow students to repeat a class (1 = yes)'),

(@school_id,'finance','default_tax_rate','0','int','Default tax rate percentage applied to invoices'),
(@school_id,'finance','receipt_footer','Thank you for your payment','string','Footer text printed on receipts'),
(@school_id,'finance','invoice_payment_terms','Payment due within 14 days','string','Standard payment terms shown on invoices'),
(@school_id,'finance','auto_generate_invoices','0','bool','Automatically generate fee invoices at term start'),
(@school_id,'finance','late_payment_fee','0','decimal','Flat late payment fee amount'),

(@school_id,'security','ip_whitelist','','string','Comma-separated list of allowed IP addresses (blank = all allowed)'),
(@school_id,'security','login_notification_email','','string','Email address to receive login notifications'),
(@school_id,'security','max_concurrent_sessions','1','int','Maximum number of simultaneous sessions per user'),
(@school_id,'security','force_https','0','bool','Force HTTPS connections (1 = yes)'),
(@school_id,'security','audit_log_retention_days','365','int','Number of days to retain audit log entries'),

(@school_id,'curriculum','cbc_enabled','1','bool','Competency-Based Curriculum is active for O-Level'),
(@school_id,'curriculum','ca_weight_percent','20','int','Continuous assessment weight percentage (S1-S4)'),
(@school_id,'curriculum','exam_weight_percent','80','int','End-of-cycle examination weight percentage (S1-S4)'),
(@school_id,'curriculum','generic_skills_required','1','bool','Generic skills assessment is required'),
(@school_id,'curriculum','project_work_scale_min','1','int','Minimum project work score (1-3 scale)'),
(@school_id,'curriculum','project_work_scale_max','3','int','Maximum project work score (1-3 scale)');

INSERT INTO sequences (school_id,seq_key,prefix,next_value,padding,description) VALUES
(@school_id,'admission_number','ADM/',1,5,'Student admission numbers'),
(@school_id,'staff_number','STF/',1,4,'Staff numbers'),
(@school_id,'invoice_number','INV/',1,6,'Fee invoice numbers'),
(@school_id,'receipt_number','REC/',1,6,'Receipt numbers'),
(@school_id,'payment_reference','PAY/',1,6,'Payment references'),
(@school_id,'application_number','APP/',1,5,'Admission application numbers'),
(@school_id,'gate_pass','GP/',1,5,'Gate pass numbers'),
(@school_id,'exeat_number','EX/',1,5,'Exeat numbers'),
(@school_id,'purchase_request','PR/',1,5,'Purchase request numbers'),
(@school_id,'purchase_order','PO/',1,5,'Purchase order numbers'),
(@school_id,'expense_voucher','PV/',1,5,'Expense voucher numbers'),
(@school_id,'petty_cash_voucher','PCV/',1,5,'Petty cash voucher numbers'),
(@school_id,'sponsor_billing','SB/',1,4,'Sponsor billing numbers'),
(@school_id,'sale_number','SL/',1,5,'Canteen/shop sale numbers'),
(@school_id,'wallet_number','WLT/',1,6,'Student wallet numbers'),
(@school_id,'support_ticket','TKT/',1,5,'Support ticket numbers'),
(@school_id,'security_incident','SEC/',1,4,'Security incident numbers'),
(@school_id,'asset_tag','AST/',1,5,'Fixed asset tags');

INSERT INTO gl_accounts (school_id,account_code,account_name,account_type) VALUES
(@school_id,'1000','Assets','asset'),
(@school_id,'2000','Liabilities','liability'),
(@school_id,'3000','Equity','equity'),
(@school_id,'4000','Income','income'),
(@school_id,'5000','Expenditure','expense');

INSERT INTO gl_accounts (school_id,account_code,account_name,account_type,parent_id) VALUES
(@school_id,'1010','Cash on hand','asset',NULL),
(@school_id,'1020','Bank account - operating','asset',NULL),
(@school_id,'1030','Mobile money collections','asset',NULL),
(@school_id,'1040','Fees receivable','asset',NULL),
(@school_id,'1050','Fixed assets','asset',NULL),
(@school_id,'1060','Inventory and stores','asset',NULL),
(@school_id,'1070','Petty cash fund','asset',NULL),
(@school_id,'2010','Accounts payable','liability',NULL),
(@school_id,'2020','NSSF payable','liability',NULL),
(@school_id,'2030','PAYE payable (URA)','liability',NULL),
(@school_id,'2040','Accrued salaries and wages','liability',NULL),
(@school_id,'2050','Advance fees received','liability',NULL),
(@school_id,'3010','School development fund','equity',NULL),
(@school_id,'3020','Retained earnings','equity',NULL),
(@school_id,'4010','Tuition fees - O-Level','income',NULL),
(@school_id,'4020','Tuition fees - A-Level','income',NULL),
(@school_id,'4030','Boarding fees','income',NULL),
(@school_id,'4040','Transport fees','income',NULL),
(@school_id,'4050','Examination and registration fees','income',NULL),
(@school_id,'4060','Uniform and shop sales','income',NULL),
(@school_id,'4070','Canteen sales','income',NULL),
(@school_id,'4080','Government grants (UPE/USE)','income',NULL),
(@school_id,'4090','Donations and PTA contributions','income',NULL),
(@school_id,'4100','Library and other charges','income',NULL),
(@school_id,'4110','Interest income','income',NULL),
(@school_id,'5010','Salaries and wages','expense',NULL),
(@school_id,'5020','NSSF and statutory contributions','expense',NULL),
(@school_id,'5030','Utilities (water and electricity)','expense',NULL),
(@school_id,'5040','Learning materials and textbooks','expense',NULL),
(@school_id,'5050','Food and catering supplies','expense',NULL),
(@school_id,'5060','Repairs and maintenance','expense',NULL),
(@school_id,'5070','Transport and fuel','expense',NULL),
(@school_id,'5080','Administration expenses','expense',NULL),
(@school_id,'5090','Security services','expense',NULL),
(@school_id,'5100','Medical and health','expense',NULL),
(@school_id,'5110','Communication (SMS and internet)','expense',NULL),
(@school_id,'5120','Depreciation','expense',NULL);

UPDATE gl_accounts c
JOIN gl_accounts p
  ON p.school_id = c.school_id
 AND p.account_code = CONCAT(LEFT(c.account_code, 1), '000')
SET c.parent_id = p.id
WHERE c.parent_id IS NULL
  AND c.account_code <> CONCAT(LEFT(c.account_code, 1), '000');

INSERT INTO item_categories (school_id,name,code) VALUES
(@school_id,'Food and Catering','FOOD'),
(@school_id,'Cleaning','CLEAN'),
(@school_id,'Laboratory','LAB'),
(@school_id,'Sports','SPORT'),
(@school_id,'Stationery','STAT'),
(@school_id,'ICT','ICT'),
(@school_id,'Medical','MED'),
(@school_id,'Uniform','UNIF'),
(@school_id,'Building and Furniture','BUIL');

INSERT INTO leave_types (school_id,name,code,days_per_year,is_paid,requires_document) VALUES
(@school_id,'Annual Leave','AL',21,1,0),
(@school_id,'Sick Leave','SL',30,1,1),
(@school_id,'Maternity Leave','ML',90,1,1),
(@school_id,'Paternity Leave','PL',7,1,1),
(@school_id,'Compassionate Leave','CL',7,1,1),
(@school_id,'Study Leave','STL',0,0,1),
(@school_id,'Unpaid Leave','UL',0,0,0);

INSERT INTO notification_templates (school_id,template_code,name,channel,subject,body,placeholders,related_module) VALUES
(@school_id,'fee_reminder','Fee balance reminder','sms',NULL,'Dear {guardian_name}, {student_name} ( {admission_number} ) has an outstanding balance of UGX {balance} due on {due_date}. Please pay at the school office or by mobile money using reference {payment_reference}. - {school_name}','{guardian_name},{student_name},{admission_number},{balance},{due_date},{payment_reference},{school_name}','finance'),
(@school_id,'absence_alert','Student absence alert','sms',NULL,'Dear {guardian_name}, {student_name} was reported {status} on {date} for {class_name}. Please contact the school on {school_phone}. - {school_name}','{guardian_name},{student_name},{status},{date},{class_name},{school_phone}','attendance'),
(@school_id,'late_alert','Late arrival alert','sms',NULL,'Dear {guardian_name}, {student_name} arrived at {time} on {date} ({minutes} minutes late). - {school_name}','{guardian_name},{student_name},{time},{date},{minutes}','attendance'),
(@school_id,'result_ready','Results released','sms',NULL,'Dear {guardian_name}, results for {student_name} for {term} are ready. View or download them from the parent portal. - {school_name}','{guardian_name},{student_name},{term}','examinations'),
(@school_id,'payment_receipt','Payment received','sms',NULL,'We confirm receipt of UGX {amount} from {payer_name} for {student_name}. Receipt number {receipt_number}. Balance: UGX {balance}. - {school_name}','{amount},{payer_name},{student_name},{receipt_number},{balance}','finance'),
(@school_id,'meeting_invite','Meeting invitation','email','School meeting invitation','Dear {guardian_name}, you are invited to {event_name} on {event_date} at {venue}. Please confirm attendance. - {school_name}','{guardian_name},{event_name},{event_date},{venue}','communication'),
(@school_id,'exeat_notification','Exeat approval','sms',NULL,'Dear {guardian_name}, exeat {exeat_number} for {student_name} to leave on {out_date} and return by {return_time} has been {status}. - {school_name}','{guardian_name},{exeat_number},{student_name},{out_date},{return_time},{status}','boarding'),
(@school_id,'report_card_notice','Report card available','portal','New report card available','Dear {guardian_name}, the report card for {student_name} ({term}) has been released and can now be downloaded from the portal.','{guardian_name},{student_name},{term}','examinations');

INSERT INTO gateway_configs (school_id,provider_code,provider_name,provider_type,currency,environment,is_active) VALUES
(@school_id,'mtn_momo','MTN Mobile Money','mobile_money','UGX','sandbox',1),
(@school_id,'airtel_money','Airtel Money','mobile_money','UGX','sandbox',1);

INSERT INTO statutory_rates (school_id,code,name,rate_type,rate_value,band_min,band_max,is_employer,effective_from,notes) VALUES
(@school_id,'NSSF_EMPLOYEE','NSSF employee contribution','percentage',5.000,NULL,NULL,0,'2026-01-01','Confirm the current NSSF rate with NSSF Uganda'),
(@school_id,'NSSF_EMPLOYER','NSSF employer contribution','percentage',10.000,NULL,NULL,1,'2026-01-01','Confirm the current NSSF rate with NSSF Uganda'),
(@school_id,'PAYE_BAND_1','PAYE band 0 - 235,000','band',0.000,0.00,235000.00,0,'2026-01-01','Verify current URA PAYE bands and personal relief'),
(@school_id,'PAYE_BAND_2','PAYE band 235,001 - 335,000','band',10.000,235000.01,335000.00,0,'2026-01-01','Verify current URA PAYE bands and personal relief'),
(@school_id,'PAYE_BAND_3','PAYE band 335,001 - 410,000','band',20.000,335000.01,410000.00,0,'2026-01-01','Verify current URA PAYE bands and personal relief'),
(@school_id,'PAYE_BAND_4','PAYE band above 410,000','band',30.000,410000.01,NULL,0,'2026-01-01','Verify current URA PAYE bands and personal relief');

INSERT INTO allowances (school_id,code,name,amount_type,default_amount,is_taxable) VALUES
(@school_id,'HOUSING','Housing Allowance','fixed',0.00,1),
(@school_id,'TRANSPORT','Transport Allowance','fixed',0.00,1),
(@school_id,'MEAL','Meal Allowance','fixed',0.00,1),
(@school_id,'RESPONSIBILITY','Responsibility Allowance','fixed',0.00,1),
(@school_id,'DUTY','Duty Allowance','fixed',0.00,1),
(@school_id,'MEDICAL','Medical Allowance','fixed',0.00,1);

INSERT IGNORE INTO uneb_levels (school_id, name, code)
SELECT id, 'UCE', 'UCE' FROM schools
UNION ALL
SELECT id, 'UACE', 'UACE' FROM schools;

CREATE OR REPLACE VIEW vw_current_term AS
SELECT
  ay.school_id,
  ay.id   AS academic_year_id,
  ay.name AS academic_year,
  t.id    AS term_id,
  t.name  AS term_name,
  t.term_number,
  t.start_date,
  t.end_date
FROM academic_years ay
JOIN terms t ON t.academic_year_id = ay.id AND t.is_active = 1
WHERE ay.is_active = 1;

CREATE OR REPLACE VIEW vw_student_placement AS
SELECT
  s.id                AS student_id,
  s.school_id,
  s.admission_number,
  s.first_name,
  s.middle_name,
  s.last_name,
  CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name) AS full_name,
  s.gender,
  s.date_of_birth,
  s.status,
  s.student_type,
  s.current_class_id AS class_id,
  c.name  AS class_name,
  c.level AS class_level,
  st.name AS stream_name,
  h.name  AS house_name,
  ay.name AS academic_year,
  t.term_name
FROM students s
LEFT JOIN classes c        ON c.id  = s.current_class_id
LEFT JOIN streams st       ON st.id = s.current_stream_id
LEFT JOIN houses h         ON h.id  = s.house_id
LEFT JOIN academic_years ay ON ay.id = s.academic_year_id
LEFT JOIN vw_current_term  t ON t.school_id = s.school_id;

CREATE OR REPLACE VIEW vw_student_guardians AS
SELECT
  sg.student_id,
  g.id   AS guardian_id,
  g.full_name,
  CONCAT(g.first_name, ' ', g.last_name) AS guardian_name,
  sg.relationship,
  g.phone,
  g.email,
  g.occupation,
  g.address,
  sg.is_primary,
  sg.is_fee_payer,
  g.receive_sms,
  g.receive_email,
  g.user_id AS portal_user_id
FROM student_guardians sg
JOIN guardians g ON g.id = sg.guardian_id;

CREATE OR REPLACE VIEW vw_fee_balances AS
SELECT
  i.school_id,
  i.student_id,
  i.academic_year_id,
  i.term_id,
  COUNT(i.id)                             AS invoice_count,
  SUM(i.subtotal)                         AS total_invoiced,
  SUM(i.discount_total)                   AS total_discounts,
  SUM(i.waived_total)                     AS total_waived,
  SUM(i.total_amount)                     AS total_charged,
  SUM(i.paid_amount)                      AS total_paid,
  SUM(i.balance)                          AS balance,
  SUM(CASE WHEN i.balance > 0 AND i.due_date < CURRENT_DATE
           THEN i.balance ELSE 0 END)     AS overdue_amount,
  MIN(CASE WHEN i.balance > 0 THEN i.due_date END) AS next_due_date
FROM fee_invoices i
WHERE i.status <> 'void'
GROUP BY i.school_id, i.student_id, i.academic_year_id, i.term_id;

CREATE OR REPLACE VIEW vw_debtor_list AS
SELECT
  fb.school_id,
  fb.academic_year_id,
  fb.term_id,
  fb.student_id,
  p.full_name AS student_name,
  p.admission_number,
  c.name AS class_name,
  fb.total_charged,
  fb.total_paid,
  fb.balance,
  fb.overdue_amount,
  DATEDIFF(CURRENT_DATE, fb.next_due_date) AS days_overdue,
  vg.full_name AS primary_guardian,
  vg.phone     AS guardian_phone
FROM vw_fee_balances fb
JOIN vw_student_placement p ON p.student_id = fb.student_id
LEFT JOIN classes c          ON c.id = p.class_id
LEFT JOIN vw_student_guardians vg ON vg.student_id = fb.student_id AND vg.is_primary = 1
WHERE fb.balance > 0;

CREATE OR REPLACE VIEW vw_fee_collection_summary AS
SELECT
  p.school_id,
  p.payment_date,
  p.channel,
  pm.name AS method_name,
  COUNT(p.id)        AS payment_count,
  SUM(p.amount_local) AS amount_collected
FROM payments p
LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
WHERE p.status IN ('verified','posted')
GROUP BY p.school_id, p.payment_date, p.channel, pm.name;

CREATE OR REPLACE VIEW vw_invoice_status AS
SELECT
  i.id AS invoice_id,
  i.invoice_number,
  i.school_id,
  i.student_id,
  i.academic_year_id,
  i.term_id,
  i.issue_date,
  i.due_date,
  i.total_amount,
  i.paid_amount,
  i.balance,
  i.status,
  CASE WHEN i.balance <= 0 THEN 'Settled'
       WHEN i.paid_amount > 0 THEN 'Part paid'
       WHEN i.due_date < CURRENT_DATE THEN 'Overdue'
       ELSE 'Unpaid' END AS ageing_status
FROM fee_invoices i;

CREATE OR REPLACE VIEW vw_attendance_daily_summary AS
SELECT
  a.school_id,
  a.attendance_date,
  a.class_id,
  c.name  AS class_name,
  a.stream_id,
  st.name AS stream_name,
  COUNT(*)                                   AS marked_count,
  SUM(a.status = 'present')                  AS present_count,
  SUM(a.status = 'absent')                   AS absent_count,
  SUM(a.status = 'late')                     AS late_count,
  SUM(a.status = 'excused')                  AS excused_count,
  ROUND(100.0 * SUM(a.status = 'present') / NULLIF(COUNT(*), 0), 2) AS attendance_percent
FROM student_attendance a
JOIN classes c  ON c.id = a.class_id
LEFT JOIN streams st ON st.id = a.stream_id
WHERE a.record_scope = 'daily'
GROUP BY a.school_id, a.attendance_date, a.class_id, c.name, a.stream_id, st.name;

CREATE OR REPLACE VIEW vw_student_attendance_rate AS
SELECT
  a.school_id,
  a.student_id,
  a.academic_year_id,
  a.term_id,
  COUNT(*)                                 AS days_marked,
  SUM(a.status = 'present')                AS days_present,
  SUM(a.status = 'absent')                 AS days_absent,
  SUM(a.status = 'late')                   AS days_late,
  ROUND(100.0 * SUM(a.status = 'present') / NULLIF(COUNT(*), 0), 2) AS attendance_percent
FROM student_attendance a
WHERE a.record_scope = 'daily'
GROUP BY a.school_id, a.student_id, a.academic_year_id, a.term_id;

CREATE OR REPLACE VIEW vw_staff_attendance_summary AS
SELECT
  sa.school_id,
  sa.staff_id,
  st.first_name,
  st.last_name,
  COUNT(*)                       AS days_marked,
  SUM(sa.status = 'present')     AS days_present,
  SUM(sa.status = 'absent')      AS days_absent,
  SUM(sa.status = 'late')        AS days_late,
  SUM(sa.status = 'on_leave')    AS days_on_leave,
  SUM(IFNULL(sa.minutes_late,0)) AS total_minutes_late
FROM staff_attendance sa
JOIN staff st ON st.id = sa.staff_id
GROUP BY sa.school_id, sa.staff_id, st.first_name, st.last_name;

CREATE OR REPLACE VIEW vw_subject_results_with_grades AS
SELECT
  r.exam_id,
  r.student_id,
  r.subject_id,
  r.class_id,
  r.stream_id,
  sub.name AS subject_name,
  sub.code AS subject_code,
  r.ca_percent,
  r.exam_percent,
  r.project_percent,
  r.weighted_percent,
  r.grade_code,
  r.grade_name,
  gb.descriptor,
  r.points,
  CASE WHEN gb.grade_code IS NOT NULL THEN 'Pass' ELSE 'Unclassified' END AS performance_status
FROM subject_results r
JOIN subjects sub ON sub.id = r.subject_id
LEFT JOIN grade_bands gb
       ON gb.grading_scale_id = r.grading_scale_id
      AND r.weighted_percent BETWEEN gb.min_percent AND gb.max_percent;

CREATE OR REPLACE VIEW vw_grade_distribution AS
SELECT
  r.exam_id,
  e.name AS exam_name,
  r.class_id,
  c.name AS class_name,
  r.subject_id,
  sub.name AS subject_name,
  IFNULL(r.grade_code, 'N/A') AS grade_code,
  IFNULL(gb.grade_name, 'Not graded') AS grade_name,
  gb.descriptor,
  COUNT(*) AS student_count
FROM subject_results r
JOIN exams e ON e.id = r.exam_id
JOIN classes c ON c.id = r.class_id
JOIN subjects sub ON sub.id = r.subject_id
LEFT JOIN grade_bands gb
       ON gb.grading_scale_id = r.grading_scale_id
      AND r.weighted_percent BETWEEN gb.min_percent AND gb.max_percent
GROUP BY r.exam_id, e.name, r.class_id, c.name, r.subject_id, sub.name,
         IFNULL(r.grade_code, 'N/A'), IFNULL(gb.grade_name, 'Not graded'), gb.descriptor;

CREATE OR REPLACE VIEW vw_exam_class_performance AS
SELECT
  r.exam_id,
  e.name AS exam_name,
  r.class_id,
  c.name AS class_name,
  r.subject_id,
  sub.name AS subject_name,
  COUNT(*) AS candidates,
  ROUND(AVG(r.weighted_percent), 2) AS average_percent,
  MAX(r.weighted_percent) AS highest_percent,
  MIN(r.weighted_percent) AS lowest_percent,
  SUM(r.weighted_percent >= 50) AS passed_count,
  ROUND(100.0 * SUM(r.weighted_percent >= 50) / NULLIF(COUNT(*), 0), 2) AS pass_rate
FROM subject_results r
JOIN exams e ON e.id = r.exam_id
JOIN classes c ON c.id = r.class_id
JOIN subjects sub ON sub.id = r.subject_id
GROUP BY r.exam_id, e.name, r.class_id, c.name, r.subject_id, sub.name;

CREATE OR REPLACE VIEW vw_ranked_results AS
SELECT
  rs.exam_id,
  rs.student_id,
  rs.class_id,
  rs.stream_id,
  rs.average_percent,
  rs.total_points,
  RANK() OVER (PARTITION BY rs.exam_id, rs.class_id ORDER BY rs.average_percent DESC) AS class_rank,
  RANK() OVER (PARTITION BY rs.exam_id, rs.stream_id ORDER BY rs.average_percent DESC) AS stream_rank
FROM result_summaries rs;

CREATE OR REPLACE VIEW vw_certificate_eligibility_status AS
SELECT
  ce.student_id,
  s.admission_number,
  CONCAT(s.first_name, ' ', s.last_name) AS student_name,
  ce.academic_year_id,
  ce.cycle,
  ce.subjects_taken,
  ce.compulsory_missing,
  ce.has_project_work,
  ce.best_level,
  ce.qualified,
  ce.result_code,
  ce.result_meaning,
  ce.issues,
  ce.checked_at
FROM certificate_eligibility ce
JOIN students s ON s.id = ce.student_id;

CREATE OR REPLACE VIEW vw_uneb_candidate_readiness AS
SELECT
  c.student_id,
  s.admission_number,
  CONCAT(s.first_name, ' ', s.last_name) AS student_name,
  c.index_number,
  l.name AS level_name,
  c.status AS registration_status,
  (SELECT COUNT(*) FROM uneb_candidate_subjects cs WHERE cs.candidate_id = c.id) AS subjects_count,
  CASE
    WHEN c.status = 'withdrawn' THEN 'Candidate withdrawn'
    WHEN c.status = 'completed' THEN 'Completed'
    WHEN (SELECT COUNT(*) FROM uneb_candidate_subjects cs WHERE cs.candidate_id = c.id) < 8 THEN 'Fewer than 8 subjects'
    ELSE 'Ready'
  END AS readiness_issue,
  c.notes AS issues
FROM uneb_candidates c
LEFT JOIN students s ON s.id = c.student_id
JOIN uneb_levels l ON l.id = c.level_id;

CREATE OR REPLACE VIEW vw_outstanding_project_work AS
SELECT
  p.student_id,
  s.admission_number,
  CONCAT(s.first_name, ' ', s.last_name) AS student_name,
  p.subject_id,
  sub.name AS subject_name,
  p.academic_year_id,
  p.status,
  p.assessed_date
FROM project_work_records p
JOIN students s ON s.id = p.student_id
JOIN subjects sub ON sub.id = p.subject_id
WHERE p.status IN ('pending','missing')
   OR p.score IS NULL;

CREATE OR REPLACE VIEW vw_teaching_workload AS
SELECT
  st.school_id,
  ta.staff_id,
  st.staff_number,
  CONCAT(st.first_name, ' ', st.last_name) AS teacher_name,
  d.name AS department_name,
  ta.academic_year_id,
  ta.term_id,
  COUNT(*)                         AS allocation_count,
  SUM(ta.lessons_per_week)         AS lessons_per_week,
  COUNT(DISTINCT ta.subject_id)    AS subjects_taught,
  COUNT(DISTINCT ta.class_id)      AS classes_taught
FROM teacher_allocations ta
JOIN staff st ON st.id = ta.staff_id
LEFT JOIN departments d ON d.id = st.department_id
GROUP BY st.school_id, ta.staff_id, st.staff_number, st.first_name, st.last_name,
         d.name, ta.academic_year_id, ta.term_id;

CREATE OR REPLACE VIEW vw_syllabus_coverage_percent AS
SELECT
  sc.class_id,
  c.name AS class_name,
  sc.stream_id,
  sy.subject_id,
  sub.name AS subject_name,
  MAX(sc.academic_year_id) AS academic_year_id,
  MAX(sc.term_id) AS term_id,
  COUNT(DISTINCT st.id) AS total_topics,
  COUNT(DISTINCT sc.topic_id) AS topics_taught,
  ROUND(100.0 * COUNT(DISTINCT sc.topic_id) / NULLIF(COUNT(DISTINCT st.id), 0), 2) AS coverage_percent
FROM syllabus_topics st
JOIN syllabuses sy ON sy.id = st.syllabus_id
JOIN subjects sub ON sub.id = sy.subject_id
LEFT JOIN syllabus_coverage sc ON sc.topic_id = st.id
LEFT JOIN classes c ON c.id = sc.class_id
GROUP BY sc.class_id, c.name, sc.stream_id, sy.subject_id, sub.name,
         sc.academic_year_id, sc.term_id;

CREATE OR REPLACE VIEW vw_homework_status AS
SELECT
  h.id AS homework_id,
  h.title,
  h.subject_id,
  sub.name AS subject_name,
  h.class_id,
  c.name AS class_name,
  h.staff_id,
  CONCAT(st.first_name, ' ', st.last_name) AS teacher_name,
  h.assigned_date,
  h.due_date,
  h.status,
  COUNT(hs.id) AS expected_submissions,
  SUM(hs.status IN ('submitted','marked')) AS submissions_received,
  ROUND(100.0 * SUM(hs.status IN ('submitted','marked')) / NULLIF(COUNT(hs.id), 0), 2) AS submission_percent
FROM homework h
JOIN subjects sub ON sub.id = h.subject_id
JOIN classes c ON c.id = h.class_id
JOIN staff st ON st.id = h.staff_id
LEFT JOIN homework_submissions hs ON hs.homework_id = h.id
GROUP BY h.id, h.title, h.subject_id, sub.name, h.class_id, c.name,
         h.staff_id, st.first_name, st.last_name, h.assigned_date, h.due_date, h.status;

CREATE OR REPLACE VIEW vw_overdue_books AS
SELECT
  b.id AS borrowing_id,
  b.school_id,
  bk.title,
  bc.accession_number,
  b.borrower_type,
  b.student_id,
  CONCAT(s.first_name, ' ', s.last_name) AS borrower_name,
  s.admission_number,
  b.staff_id,
  b.issue_date,
  b.due_date,
  DATEDIFF(CURRENT_DATE, b.due_date) AS days_overdue,
  b.status
FROM borrowings b
JOIN book_copies bc ON bc.id = b.copy_id
JOIN books bk ON bk.id = b.book_id
LEFT JOIN students s ON s.id = b.student_id
WHERE b.return_date IS NULL
  AND b.status IN ('borrowed','overdue')
  AND b.due_date < CURRENT_DATE;

CREATE OR REPLACE VIEW vw_library_stock_status AS
SELECT
  bk.school_id,
  bk.id AS book_id,
  bk.title,
  bk.author,
  cat.name AS category_name,
  bk.total_copies,
  bk.available_copies,
  bk.lost_copies,
  (bk.total_copies - bk.available_copies - bk.lost_copies) AS copies_out,
  bk.status
FROM books bk
LEFT JOIN book_categories cat ON cat.id = bk.category_id;

CREATE OR REPLACE VIEW vw_dormitory_occupancy AS
SELECT
  d.school_id,
  d.id AS dormitory_id,
  d.name AS dormitory_name,
  d.dormitory_type,
  d.capacity,
  d.occupied_beds,
  (d.capacity - d.occupied_beds) AS vacancies,
  ROUND(100.0 * d.occupied_beds / NULLIF(d.capacity, 0), 2) AS occupancy_percent,
  CONCAT(w.first_name, ' ', w.last_name) AS warden_name
FROM dormitories d
LEFT JOIN staff w ON w.id = d.warden_staff_id;

CREATE OR REPLACE VIEW vw_route_load AS
SELECT
  r.school_id,
  r.id AS route_id,
  r.route_code,
  r.route_name,
  v.plate_number,
  v.seating_capacity,
  COUNT(sra.student_id) AS students_allocated,
  ROUND(100.0 * COUNT(sra.student_id) / NULLIF(v.seating_capacity, 0), 2) AS capacity_percent,
  r.status
FROM routes r
LEFT JOIN vehicles v ON v.id = r.vehicle_id
LEFT JOIN student_route_allocations sra ON sra.route_id = r.id AND sra.status = 'active'
GROUP BY r.school_id, r.id, r.route_code, r.route_name, v.plate_number,
         v.seating_capacity, r.status;

CREATE OR REPLACE VIEW vw_low_stock_items AS
SELECT
  i.school_id,
  i.id AS item_id,
  i.item_code,
  i.name AS item_name,
  cat.name AS category_name,
  i.unit,
  i.current_stock,
  i.reorder_level,
  (i.reorder_level - i.current_stock) AS shortfall,
  i.status
FROM items i
LEFT JOIN item_categories cat ON cat.id = i.category_id
WHERE i.current_stock <= i.reorder_level
  AND i.is_stocked = 1
  AND i.status = 'active';

CREATE OR REPLACE VIEW vw_budget_vs_actual AS
SELECT
  b.school_id,
  b.id AS budget_id,
  b.name AS budget_name,
  b.budget_type,
  b.academic_year_id,
  b.status,
  a.account_code,
  a.account_name,
  a.account_type,
  SUM(bl.planned_amount) AS planned_amount,
  SUM(bl.actual_amount)  AS actual_amount,
  SUM(bl.planned_amount - bl.actual_amount) AS variance
FROM budgets b
JOIN budget_lines bl ON bl.budget_id = b.id
JOIN gl_accounts a ON a.id = bl.account_id
GROUP BY b.school_id, b.id, b.name, b.budget_type, b.academic_year_id, b.status,
         a.account_code, a.account_name, a.account_type;

CREATE OR REPLACE VIEW vw_income_expenditure AS
SELECT
  le.school_id,
  le.entry_date,
  a.account_code,
  a.account_name,
  a.account_type,
  SUM(le.debit)  AS total_debit,
  SUM(le.credit) AS total_credit,
  CASE WHEN a.account_type = 'income' THEN SUM(le.credit) - SUM(le.debit)
       WHEN a.account_type = 'expense' THEN SUM(le.debit) - SUM(le.credit)
       ELSE 0 END AS net_amount
FROM ledger_entries le
JOIN gl_accounts a ON a.id = le.account_id
WHERE le.is_posted = 1
GROUP BY le.school_id, le.entry_date, a.account_code, a.account_name, a.account_type;

CREATE OR REPLACE VIEW vw_leave_balance AS
SELECT
  st.school_id,
  lr.staff_id,
  st.staff_number,
  CONCAT(st.first_name, ' ', st.last_name) AS staff_name,
  lt.name AS leave_type,
  lt.days_per_year,
  SUM(CASE WHEN YEAR(lr.start_date) = YEAR(CURRENT_DATE)
            AND lr.status = 'approved' THEN lr.days_requested ELSE 0 END) AS days_taken,
  (lt.days_per_year - SUM(CASE WHEN YEAR(lr.start_date) = YEAR(CURRENT_DATE)
            AND lr.status = 'approved' THEN lr.days_requested ELSE 0 END)) AS days_remaining,
  SUM(CASE WHEN lr.status = 'pending' THEN lr.days_requested ELSE 0 END) AS days_pending
FROM leave_requests lr
JOIN leave_types lt ON lt.id = lr.leave_type_id
JOIN staff st ON st.id = lr.staff_id
GROUP BY st.school_id, lr.staff_id, st.staff_number, st.first_name, st.last_name,
         lt.name, lt.days_per_year;

CREATE OR REPLACE VIEW vw_payroll_summary AS
SELECT
  pp.school_id,
  pp.id AS payroll_period_id,
  pp.period_label,
  pp.status,
  COUNT(pi.id) AS employees_paid,
  SUM(pi.basic_pay) AS total_basic,
  SUM(pi.allowances_total) AS total_allowances,
  SUM(pi.gross_pay) AS total_gross,
  SUM(pi.nssf_employee) AS total_nssf_employee,
  SUM(pi.nssf_employer) AS total_nssf_employer,
  SUM(pi.paye) AS total_paye,
  SUM(pi.other_deductions) AS total_other_deductions,
  SUM(pi.net_pay) AS total_net_pay
FROM payroll_periods pp
LEFT JOIN payroll_items pi ON pi.payroll_period_id = pp.id
GROUP BY pp.school_id, pp.id, pp.period_label, pp.status;

CREATE OR REPLACE VIEW vw_contract_expiry_alerts AS
SELECT
  st.school_id,
  ct.id AS contract_id,
  ct.staff_id,
  CONCAT(st.first_name, ' ', st.last_name) AS staff_name,
  st.position,
  ct.contract_type,
  ct.start_date,
  ct.end_date,
  DATEDIFF(ct.end_date, CURRENT_DATE) AS days_to_expiry,
  ct.status
FROM contracts ct
JOIN staff st ON st.id = ct.staff_id
WHERE ct.status = 'active'
  AND ct.end_date IS NOT NULL
  AND DATEDIFF(ct.end_date, CURRENT_DATE) <= 90;

CREATE OR REPLACE VIEW vw_licence_expiry_alerts AS
SELECT
  l.school_id,
  l.id AS licence_id,
  l.licence_type,
  l.licence_number,
  l.issuing_body,
  l.issue_date,
  l.expiry_date,
  DATEDIFF(l.expiry_date, CURRENT_DATE) AS days_to_expiry,
  l.status
FROM licences l
WHERE l.expiry_date IS NOT NULL
  AND DATEDIFF(l.expiry_date, CURRENT_DATE) <= 90;

CREATE OR REPLACE VIEW vw_audit_summary AS
SELECT
  al.school_id,
  al.user_id,
  al.user_name,
  al.module,
  al.action,
  DATE(al.created_at) AS activity_date,
  COUNT(*) AS action_count
FROM audit_logs al
GROUP BY al.school_id, al.user_id, al.user_name, al.module, al.action,
         DATE(al.created_at);

CREATE OR REPLACE VIEW vw_support_ticket_summary AS
SELECT
  st.school_id,
  st.status,
  st.priority,
  COUNT(*) AS ticket_count,
  ROUND(AVG(TIMESTAMPDIFF(HOUR, st.created_at, IFNULL(st.first_response_at, CURRENT_TIMESTAMP))), 2) AS avg_first_response_hours,
  ROUND(AVG(TIMESTAMPDIFF(HOUR, st.created_at, IFNULL(st.resolved_at, CURRENT_TIMESTAMP))), 2) AS avg_resolution_hours,
  AVG(st.satisfaction_rating) AS avg_satisfaction
FROM support_tickets st
GROUP BY st.school_id, st.status, st.priority;

CREATE OR REPLACE VIEW vw_school_dashboard AS
SELECT
  sc.id AS school_id,
  sc.name AS school_name,
  (SELECT COUNT(*) FROM students s WHERE s.school_id = sc.id AND s.status = 'active') AS active_students,
  (SELECT COUNT(*) FROM students s WHERE s.school_id = sc.id AND s.status = 'active' AND s.student_type = 'boarding') AS boarders,
  (SELECT COUNT(*) FROM staff st WHERE st.school_id = sc.id AND st.employment_status = 'active') AS active_staff,
  (SELECT COUNT(*) FROM classes c WHERE c.school_id = sc.id AND c.is_active = 1) AS active_classes,
  (SELECT IFNULL(SUM(i.balance), 0) FROM fee_invoices i WHERE i.school_id = sc.id AND i.status <> 'void') AS outstanding_fees,
  (SELECT IFNULL(SUM(p.amount_local), 0) FROM payments p WHERE p.school_id = sc.id AND p.status IN ('verified','posted') AND p.payment_date >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')) AS fees_collected_this_month,
  (SELECT COUNT(*) FROM student_attendance a WHERE a.school_id = sc.id AND a.attendance_date = CURRENT_DATE AND a.status = 'absent') AS absent_today,
  (SELECT COUNT(*) FROM support_tickets t WHERE t.school_id = sc.id AND t.status IN ('open','in_progress')) AS open_tickets
FROM schools sc;

DROP PROCEDURE IF EXISTS ssms_uneb_align;

DELIMITER //
CREATE PROCEDURE ssms_uneb_align()
BEGIN
  IF EXISTS (
      SELECT 1 FROM information_schema.columns
       WHERE table_schema = DATABASE()
         AND table_name   = 'uneb_candidates'
         AND column_name  = 'cycle'
  ) THEN
    DROP TABLE IF EXISTS uneb_ca_subject_scores;
    DROP TABLE IF EXISTS uneb_ca_submission;
    DROP TABLE IF EXISTS uneb_candidate_subjects;
    DROP TABLE IF EXISTS uneb_candidates;
    DROP TABLE IF EXISTS uneb_results;
  END IF;
END//
DELIMITER ;

CALL ssms_uneb_align();
DROP PROCEDURE ssms_uneb_align;

CREATE TABLE IF NOT EXISTS uneb_levels (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(60) NOT NULL,
  code        VARCHAR(20) NOT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_level (school_id, code),
  CONSTRAINT fk_uneb_level_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_candidates (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id       INT UNSIGNED NOT NULL,
  index_number    VARCHAR(30) NOT NULL,
  full_name       VARCHAR(160) NOT NULL,
  gender          ENUM('male','female') NOT NULL,
  date_of_birth   DATE,
  student_id      INT UNSIGNED,
  level_id        INT UNSIGNED NOT NULL,
  status          ENUM('registered','entered','withdrawn','completed') NOT NULL DEFAULT 'registered',
  notes           VARCHAR(255),
  registered_by   INT UNSIGNED,
  registered_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_index (school_id, index_number),
  KEY idx_uneb_level (level_id, status),
  CONSTRAINT fk_uneb_cand_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_cand_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_uneb_cand_level FOREIGN KEY (level_id) REFERENCES uneb_levels (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_candidate_subjects (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  candidate_id  INT UNSIGNED NOT NULL,
  subject_id    INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_cand_subject (candidate_id, subject_id),
  CONSTRAINT fk_uneb_subj_candidate FOREIGN KEY (candidate_id) REFERENCES uneb_candidates (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_subj_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_ca_submission (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  level_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  term_id          INT UNSIGNED,
  submission_type  ENUM('internal','uneb_portal') NOT NULL DEFAULT 'internal',
  status           ENUM('draft','submitted','confirmed') NOT NULL DEFAULT 'draft',
  notes            VARCHAR(255),
  submitted_by     INT UNSIGNED,
  submitted_at     DATETIME,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_uneb_ca_level (school_id, level_id, status),
  CONSTRAINT fk_uneb_ca_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_level_lvl FOREIGN KEY (level_id) REFERENCES uneb_levels (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_term FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS uneb_ca_subject_scores (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id  INT UNSIGNED NOT NULL,
  candidate_id   INT UNSIGNED NOT NULL,
  subject_id     INT UNSIGNED NOT NULL,
  ca_score       DECIMAL(5,2),
  project_score  DECIMAL(5,2),
  overall_score  DECIMAL(5,2),
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_ca_score (submission_id, candidate_id, subject_id),
  KEY idx_uneb_ca_score_cand (candidate_id),
  CONSTRAINT fk_uneb_ca_score_sub FOREIGN KEY (submission_id) REFERENCES uneb_ca_submission (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_score_cand FOREIGN KEY (candidate_id) REFERENCES uneb_candidates (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_ca_score_subj FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uneb_results (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        INT UNSIGNED NOT NULL,
  level_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED,
  exam_year        YEAR NOT NULL,
  index_number     VARCHAR(30) NOT NULL,
  full_name        VARCHAR(160) NOT NULL,
  gender           ENUM('male','female') NOT NULL,
  subject_results  TEXT,
  overall_level    VARCHAR(10),
  result_code      ENUM('Result 1','Result 2','Result 3'),
  ca_score_submitted TINYINT(1) NOT NULL DEFAULT 0,
  notes            VARCHAR(255),
  recorded_by      INT UNSIGNED,
  recorded_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_result (school_id, level_id, exam_year, index_number),
  KEY idx_uneb_result_year (exam_year, index_number),
  CONSTRAINT fk_uneb_res_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_res_level FOREIGN KEY (level_id) REFERENCES uneb_levels (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_res_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
);


USE ssms;
SET NAMES utf8mb4;

DELIMITER $$

DROP TRIGGER IF EXISTS trg_payments_ai_sync_invoice$$
CREATE TRIGGER trg_payments_ai_sync_invoice AFTER INSERT ON payments
FOR EACH ROW
BEGIN
  DECLARE v_paid DECIMAL(12,2);
  IF NEW.invoice_id IS NOT NULL THEN
    SELECT COALESCE(SUM(amount_local),0) INTO v_paid
      FROM payments
     WHERE invoice_id = NEW.invoice_id AND status IN ('verified','posted');
    UPDATE fee_invoices
       SET paid_amount = v_paid,
           balance = GREATEST(total_amount - v_paid, 0),
           status = CASE WHEN v_paid >= total_amount AND total_amount > 0 THEN 'paid'
                         WHEN v_paid > 0 THEN 'part_paid'
                         ELSE 'unpaid' END
     WHERE id = NEW.invoice_id AND status <> 'void';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_payments_au_sync_invoice$$
CREATE TRIGGER trg_payments_au_sync_invoice AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
  DECLARE v_paid DECIMAL(12,2);
  IF OLD.invoice_id IS NOT NULL AND (NEW.invoice_id IS NULL OR NEW.invoice_id <> OLD.invoice_id) THEN
    SELECT COALESCE(SUM(amount_local),0) INTO v_paid
      FROM payments
     WHERE invoice_id = OLD.invoice_id AND status IN ('verified','posted');
    UPDATE fee_invoices
       SET paid_amount = v_paid,
           balance = GREATEST(total_amount - v_paid, 0),
           status = CASE WHEN v_paid >= total_amount AND total_amount > 0 THEN 'paid'
                         WHEN v_paid > 0 THEN 'part_paid'
                         ELSE 'unpaid' END
     WHERE id = OLD.invoice_id AND status <> 'void';
  END IF;
  IF NEW.invoice_id IS NOT NULL THEN
    SELECT COALESCE(SUM(amount_local),0) INTO v_paid
      FROM payments
     WHERE invoice_id = NEW.invoice_id AND status IN ('verified','posted');
    UPDATE fee_invoices
       SET paid_amount = v_paid,
           balance = GREATEST(total_amount - v_paid, 0),
           status = CASE WHEN v_paid >= total_amount AND total_amount > 0 THEN 'paid'
                         WHEN v_paid > 0 THEN 'part_paid'
                         ELSE 'unpaid' END
     WHERE id = NEW.invoice_id AND status <> 'void';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_payments_ad_sync_invoice$$
CREATE TRIGGER trg_payments_ad_sync_invoice AFTER DELETE ON payments
FOR EACH ROW
BEGIN
  DECLARE v_paid DECIMAL(12,2);
  IF OLD.invoice_id IS NOT NULL THEN
    SELECT COALESCE(SUM(amount_local),0) INTO v_paid
      FROM payments
     WHERE invoice_id = OLD.invoice_id AND status IN ('verified','posted');
    UPDATE fee_invoices
       SET paid_amount = v_paid,
           balance = GREATEST(total_amount - v_paid, 0),
           status = CASE WHEN v_paid >= total_amount AND total_amount > 0 THEN 'paid'
                         WHEN v_paid > 0 THEN 'part_paid'
                         ELSE 'unpaid' END
     WHERE id = OLD.invoice_id AND status <> 'void';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_payments_bi_guard$$
CREATE TRIGGER trg_payments_bi_guard BEFORE INSERT ON payments
FOR EACH ROW
BEGIN
  DECLARE v_student INT;
  IF NEW.amount <= 0 OR NEW.amount_local <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amounts must be greater than zero.';
  END IF;
  IF NEW.invoice_id IS NOT NULL THEN
    SELECT student_id INTO v_student FROM fee_invoices WHERE id = NEW.invoice_id;
    IF v_student IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The selected invoice does not exist.';
    END IF;
    IF NEW.student_id IS NOT NULL AND v_student <> NEW.student_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The payment student does not match the invoice student.';
    END IF;
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_fee_invoices_bu_guard$$
CREATE TRIGGER trg_fee_invoices_bu_guard BEFORE UPDATE ON fee_invoices
FOR EACH ROW
BEGIN
  IF NEW.total_amount < 0 OR NEW.paid_amount < 0 OR NEW.balance < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice amounts cannot be negative.';
  END IF;
  IF ABS(NEW.balance - GREATEST(NEW.total_amount - NEW.paid_amount, 0)) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice balance must equal the total less the amount paid.';
  END IF;
  IF OLD.status = 'void' AND (
       NEW.status <> 'void'
       OR NEW.total_amount <> OLD.total_amount
       OR NEW.paid_amount <> OLD.paid_amount
       OR NEW.balance <> OLD.balance
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Void invoices cannot be changed.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_fee_invoices_bd_guard$$
CREATE TRIGGER trg_fee_invoices_bd_guard BEFORE DELETE ON fee_invoices
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM payments WHERE invoice_id = OLD.id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoices with payments cannot be deleted.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_fee_invoice_items_bi_net$$
CREATE TRIGGER trg_fee_invoice_items_bi_net BEFORE INSERT ON fee_invoice_items
FOR EACH ROW
BEGIN
  IF NEW.amount < 0 OR COALESCE(NEW.discount_amount,0) < 0 OR COALESCE(NEW.waived_amount,0) < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice item amounts cannot be negative.';
  END IF;
  IF EXISTS (SELECT 1 FROM fee_invoices WHERE id = NEW.invoice_id AND status = 'void') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice items cannot be added to a void invoice.';
  END IF;
  SET NEW.net_amount = ROUND(GREATEST(NEW.amount - COALESCE(NEW.discount_amount,0) - COALESCE(NEW.waived_amount,0), 0), 2);
END$$

DROP TRIGGER IF EXISTS trg_fee_invoice_items_bu_net$$
CREATE TRIGGER trg_fee_invoice_items_bu_net BEFORE UPDATE ON fee_invoice_items
FOR EACH ROW
BEGIN
  IF NEW.amount < 0 OR COALESCE(NEW.discount_amount,0) < 0 OR COALESCE(NEW.waived_amount,0) < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice item amounts cannot be negative.';
  END IF;
  IF EXISTS (SELECT 1 FROM fee_invoices WHERE id = NEW.invoice_id AND status = 'void') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice items cannot be changed on a void invoice.';
  END IF;
  SET NEW.net_amount = ROUND(GREATEST(NEW.amount - COALESCE(NEW.discount_amount,0) - COALESCE(NEW.waived_amount,0), 0), 2);
END$$

DROP TRIGGER IF EXISTS trg_receipts_bi_guard$$
CREATE TRIGGER trg_receipts_bi_guard BEFORE INSERT ON receipts
FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(20);
  DECLARE v_amount DECIMAL(12,2);
  SELECT status, amount_local INTO v_status, v_amount FROM payments WHERE id = NEW.payment_id;
  IF v_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The receipt payment does not exist.';
  END IF;
  IF v_status NOT IN ('verified','posted') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Receipts can only be issued for verified or posted payments.';
  END IF;
  IF ABS(NEW.amount - v_amount) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The receipt amount must match the payment amount.';
  END IF;
  IF EXISTS (SELECT 1 FROM receipts WHERE payment_id = NEW.payment_id AND is_void = 0) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'An active receipt already exists for this payment.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_receipts_bu_guard$$
CREATE TRIGGER trg_receipts_bu_guard BEFORE UPDATE ON receipts
FOR EACH ROW
BEGIN
  IF NEW.payment_id <> OLD.payment_id OR NEW.amount <> OLD.amount THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A receipt payment and amount cannot be changed.';
  END IF;
  IF OLD.is_void = 1 AND NEW.is_void = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A voided receipt cannot be restored.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_ledger_entries_bi_guard$$
CREATE TRIGGER trg_ledger_entries_bi_guard BEFORE INSERT ON ledger_entries
FOR EACH ROW
BEGIN
  IF NEW.debit < 0 OR NEW.credit < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger amounts cannot be negative.';
  END IF;
  IF NOT ((NEW.debit > 0 AND NEW.credit = 0) OR (NEW.credit > 0 AND NEW.debit = 0)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A ledger entry needs exactly one debit or credit amount.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_wallet_transactions_bi_guard$$
CREATE TRIGGER trg_wallet_transactions_bi_guard BEFORE INSERT ON wallet_transactions
FOR EACH ROW
BEGIN
  IF NEW.amount <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Wallet transaction amounts must be greater than zero.';
  END IF;
  IF NEW.transaction_type IN ('deposit','refund') THEN
    IF ABS(NEW.balance_after - (NEW.balance_before + NEW.amount)) > 0.01 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Wallet balance after does not match the amount added.';
    END IF;
  ELSEIF NEW.transaction_type IN ('purchase','withdrawal') THEN
    IF ABS(NEW.balance_after - (NEW.balance_before - NEW.amount)) > 0.01 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Wallet balance after does not match the amount deducted.';
    END IF;
  END IF;
  IF NEW.balance_after < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A wallet balance cannot go below zero.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_wallet_transactions_ai_sync$$
CREATE TRIGGER trg_wallet_transactions_ai_sync AFTER INSERT ON wallet_transactions
FOR EACH ROW
BEGIN
  UPDATE student_wallets SET balance = NEW.balance_after WHERE id = NEW.wallet_id;
END$$

DROP TRIGGER IF EXISTS trg_petty_cash_vouchers_bi_guard$$
CREATE TRIGGER trg_petty_cash_vouchers_bi_guard BEFORE INSERT ON petty_cash_vouchers
FOR EACH ROW
BEGIN
  DECLARE v_balance DECIMAL(12,2);
  IF NEW.amount <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Voucher amounts must be greater than zero.';
  END IF;
  SELECT current_balance INTO v_balance FROM petty_cash_funds WHERE id = NEW.fund_id;
  IF v_balance IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The petty cash fund does not exist.';
  END IF;
  IF NEW.amount > v_balance THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The voucher exceeds the petty cash fund balance.';
  END IF;
  SET NEW.balance_after = ROUND(v_balance - NEW.amount, 2);
END$$

DROP TRIGGER IF EXISTS trg_petty_cash_vouchers_au_balance$$
CREATE TRIGGER trg_petty_cash_vouchers_au_balance AFTER UPDATE ON petty_cash_vouchers
FOR EACH ROW
BEGIN
  DECLARE v_balance DECIMAL(12,2);
  IF OLD.status = 'paid' AND NEW.status = 'paid' AND (
       NEW.amount <> OLD.amount OR NEW.fund_id <> OLD.fund_id
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A paid voucher cannot change its amount or fund.';
  END IF;
  IF NEW.status = 'paid' AND OLD.status <> 'paid' THEN
    SELECT current_balance INTO v_balance FROM petty_cash_funds WHERE id = NEW.fund_id;
    IF v_balance IS NULL OR v_balance < NEW.amount THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The petty cash fund has insufficient balance.';
    END IF;
    UPDATE petty_cash_funds SET current_balance = current_balance - NEW.amount WHERE id = NEW.fund_id;
  END IF;
  IF OLD.status = 'paid' AND NEW.status <> 'paid' THEN
    UPDATE petty_cash_funds SET current_balance = current_balance + OLD.amount WHERE id = OLD.fund_id;
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_marks_bi_compute$$
CREATE TRIGGER trg_marks_bi_compute BEFORE INSERT ON marks
FOR EACH ROW
BEGIN
  DECLARE v_max DECIMAL(10,2);
  IF COALESCE(NEW.ca_mark,0) < 0 OR COALESCE(NEW.exam_mark,0) < 0 OR COALESCE(NEW.project_mark,0) < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mark scores cannot be negative.';
  END IF;
  IF NEW.max_ca_mark IS NOT NULL AND NEW.ca_mark IS NOT NULL AND NEW.ca_mark > NEW.max_ca_mark + 0.001 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The CA mark cannot exceed its maximum.';
  END IF;
  IF NEW.max_exam_mark IS NOT NULL AND NEW.exam_mark IS NOT NULL AND NEW.exam_mark > NEW.max_exam_mark + 0.001 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam mark cannot exceed its maximum.';
  END IF;
  SET NEW.total_mark = ROUND(COALESCE(NEW.ca_mark,0) + COALESCE(NEW.exam_mark,0), 2);
  SET v_max = COALESCE(NEW.max_ca_mark,0) + COALESCE(NEW.max_exam_mark,0);
  SET NEW.percentage = IF(v_max > 0, ROUND(NEW.total_mark / v_max * 100, 2), 0);
END$$

DROP TRIGGER IF EXISTS trg_marks_bu_compute$$
CREATE TRIGGER trg_marks_bu_compute BEFORE UPDATE ON marks
FOR EACH ROW
BEGIN
  DECLARE v_max DECIMAL(10,2);
  IF OLD.status = 'locked' AND (
       NOT (NEW.ca_mark <=> OLD.ca_mark)
       OR NOT (NEW.exam_mark <=> OLD.exam_mark)
       OR NOT (NEW.project_mark <=> OLD.project_mark)
       OR NOT (NEW.max_ca_mark <=> OLD.max_ca_mark)
       OR NOT (NEW.max_exam_mark <=> OLD.max_exam_mark)
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Locked marks cannot be edited. Unlock the mark first.';
  END IF;
  IF COALESCE(NEW.ca_mark,0) < 0 OR COALESCE(NEW.exam_mark,0) < 0 OR COALESCE(NEW.project_mark,0) < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mark scores cannot be negative.';
  END IF;
  IF NEW.max_ca_mark IS NOT NULL AND NEW.ca_mark IS NOT NULL AND NEW.ca_mark > NEW.max_ca_mark + 0.001 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The CA mark cannot exceed its maximum.';
  END IF;
  IF NEW.max_exam_mark IS NOT NULL AND NEW.exam_mark IS NOT NULL AND NEW.exam_mark > NEW.max_exam_mark + 0.001 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam mark cannot exceed its maximum.';
  END IF;
  IF NOT (NEW.ca_mark <=> OLD.ca_mark)
     OR NOT (NEW.exam_mark <=> OLD.exam_mark)
     OR NOT (NEW.max_ca_mark <=> OLD.max_ca_mark)
     OR NOT (NEW.max_exam_mark <=> OLD.max_exam_mark) THEN
    SET NEW.total_mark = ROUND(COALESCE(NEW.ca_mark,0) + COALESCE(NEW.exam_mark,0), 2);
    SET v_max = COALESCE(NEW.max_ca_mark,0) + COALESCE(NEW.max_exam_mark,0);
    SET NEW.percentage = IF(v_max > 0, ROUND(NEW.total_mark / v_max * 100, 2), 0);
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_exams_bi_dates$$
CREATE TRIGGER trg_exams_bi_dates BEFORE INSERT ON exams
FOR EACH ROW
BEGIN
  IF NEW.start_date IS NOT NULL AND NEW.end_date IS NOT NULL AND NEW.end_date < NEW.start_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam end date cannot be before the start date.';
  END IF;
  IF NEW.max_mark IS NOT NULL AND NEW.max_mark <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam maximum mark must be greater than zero.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_exams_bu_dates$$
CREATE TRIGGER trg_exams_bu_dates BEFORE UPDATE ON exams
FOR EACH ROW
BEGIN
  IF NEW.start_date IS NOT NULL AND NEW.end_date IS NOT NULL AND NEW.end_date < NEW.start_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam end date cannot be before the start date.';
  END IF;
  IF NEW.max_mark IS NOT NULL AND NEW.max_mark <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam maximum mark must be greater than zero.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_exam_schedule_bi_dates$$
CREATE TRIGGER trg_exam_schedule_bi_dates BEFORE INSERT ON exam_schedule
FOR EACH ROW
BEGIN
  DECLARE v_start DATE;
  DECLARE v_end DATE;
  SELECT start_date, end_date INTO v_start, v_end FROM exams WHERE id = NEW.exam_id;
  IF NEW.paper_date IS NOT NULL AND v_start IS NOT NULL AND v_end IS NOT NULL
     AND (NEW.paper_date < v_start OR NEW.paper_date > v_end) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The paper date must fall inside the exam dates.';
  END IF;
  IF NEW.max_mark IS NOT NULL AND NEW.max_mark <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The paper maximum mark must be greater than zero.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_exam_schedule_bu_dates$$
CREATE TRIGGER trg_exam_schedule_bu_dates BEFORE UPDATE ON exam_schedule
FOR EACH ROW
BEGIN
  DECLARE v_start DATE;
  DECLARE v_end DATE;
  SELECT start_date, end_date INTO v_start, v_end FROM exams WHERE id = NEW.exam_id;
  IF NEW.paper_date IS NOT NULL AND v_start IS NOT NULL AND v_end IS NOT NULL
     AND (NEW.paper_date < v_start OR NEW.paper_date > v_end) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The paper date must fall inside the exam dates.';
  END IF;
  IF NEW.max_mark IS NOT NULL AND NEW.max_mark <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The paper maximum mark must be greater than zero.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_leave_requests_bi_dates$$
CREATE TRIGGER trg_leave_requests_bi_dates BEFORE INSERT ON leave_requests
FOR EACH ROW
BEGIN
  IF NEW.end_date < NEW.start_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The leave end date cannot be before the start date.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_leave_requests_bu_dates$$
CREATE TRIGGER trg_leave_requests_bu_dates BEFORE UPDATE ON leave_requests
FOR EACH ROW
BEGIN
  IF NEW.end_date < NEW.start_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The leave end date cannot be before the start date.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_payroll_items_bi_guard$$
CREATE TRIGGER trg_payroll_items_bi_guard BEFORE INSERT ON payroll_items
FOR EACH ROW
BEGIN
  IF ABS(NEW.gross_pay - (NEW.basic_pay + NEW.allowances_total)) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Gross pay must equal basic pay plus allowances.';
  END IF;
  IF NEW.total_deductions + 0.01 < (COALESCE(NEW.nssf_employee,0) + COALESCE(NEW.paye,0) + COALESCE(NEW.other_deductions,0)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Total deductions are less than their component parts.';
  END IF;
  IF ABS(NEW.net_pay - (NEW.gross_pay - NEW.total_deductions)) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net pay must equal gross pay less total deductions.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_payroll_items_bu_guard$$
CREATE TRIGGER trg_payroll_items_bu_guard BEFORE UPDATE ON payroll_items
FOR EACH ROW
BEGIN
  IF OLD.is_paid = 1 AND (
       NEW.basic_pay <> OLD.basic_pay
       OR NEW.allowances_total <> OLD.allowances_total
       OR NEW.gross_pay <> OLD.gross_pay
       OR NEW.total_deductions <> OLD.total_deductions
       OR NEW.net_pay <> OLD.net_pay
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A paid payroll item cannot change its amounts.';
  END IF;
  IF ABS(NEW.gross_pay - (NEW.basic_pay + NEW.allowances_total)) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Gross pay must equal basic pay plus allowances.';
  END IF;
  IF NEW.total_deductions + 0.01 < (COALESCE(NEW.nssf_employee,0) + COALESCE(NEW.paye,0) + COALESCE(NEW.other_deductions,0)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Total deductions are less than their component parts.';
  END IF;
  IF ABS(NEW.net_pay - (NEW.gross_pay - NEW.total_deductions)) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net pay must equal gross pay less total deductions.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_borrowings_bi_guard$$
CREATE TRIGGER trg_borrowings_bi_guard BEFORE INSERT ON borrowings
FOR EACH ROW
BEGIN
  DECLARE v_book INT;
  IF NEW.borrower_type = 'student' AND NEW.student_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Choose the student borrowing this copy.';
  END IF;
  IF NEW.borrower_type = 'staff' AND NEW.staff_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Choose the staff member borrowing this copy.';
  END IF;
  IF NEW.due_date < NEW.issue_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The due date cannot be before the issue date.';
  END IF;
  SELECT book_id INTO v_book FROM book_copies WHERE id = NEW.copy_id;
  IF v_book IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The selected book copy does not exist.';
  END IF;
  IF v_book <> NEW.book_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The book copy does not belong to the selected book.';
  END IF;
  IF NEW.status IN ('borrowed','overdue') AND EXISTS (
       SELECT 1 FROM borrowings
        WHERE copy_id = NEW.copy_id AND status IN ('borrowed','overdue')
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This copy is already on loan.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_visitors_bi_times$$
CREATE TRIGGER trg_visitors_bi_times BEFORE INSERT ON visitors
FOR EACH ROW
BEGIN
  IF NEW.time_in IS NULL THEN
    SET NEW.time_in = NOW();
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_visitors_bu_times$$
CREATE TRIGGER trg_visitors_bu_times BEFORE UPDATE ON visitors
FOR EACH ROW
BEGIN
  IF NEW.time_out IS NOT NULL AND NEW.time_in IS NOT NULL THEN
    IF NEW.time_out < NEW.time_in THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The visitor check-out time cannot be before check-in.';
    END IF;
    SET NEW.duration_minutes = TIMESTAMPDIFF(MINUTE, NEW.time_in, NEW.time_out);
  END IF;
END$$

DELIMITER ;
