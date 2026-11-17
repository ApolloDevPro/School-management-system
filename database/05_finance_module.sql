-- ==========================================================================
-- SSMS - File : 05_finance_module.sql
-- Module      : 2.5 Finance and Fees Management
-- Depends on  : 00 - 02 (schools, students, staff, departments)
-- ==========================================================================

USE ssms;
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- Chart of Accounts
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Fees Structure
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Payment Methods
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Student Invoicing
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Fee Payments and Verification
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Receipt Generation
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Fee Balances, Reminders and Clearance
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Discounts and Waivers
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Sponsors, Bursaries and Scholarships
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Sponsor Billing
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Budgeting
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Expense Management
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Petty Cash Management
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Bank Accounts, Transactions and Reconciliation
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Double-entry ledger (feeds the financial statements)
-- --------------------------------------------------------------------------
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

