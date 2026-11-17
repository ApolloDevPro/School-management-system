-- ==========================================================================
-- SSMS - File : 16_canteen_module.sql
-- Module      : 2.18 Canteen and School Shop Management (Optional)
-- Depends on  : 00 - 05 (schools, students, staff, receipts, gl_accounts)
-- ==========================================================================

USE ssms;
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- Product Catalogue
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Shop / Canteen Stock Control
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Student Wallet / Account
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Sales Recording
-- --------------------------------------------------------------------------
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

ALTER TABLE wallet_transactions
  ADD CONSTRAINT fk_wallet_txn_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE SET NULL;

-- --------------------------------------------------------------------------
-- Shop cash reconciliation (end-of-day till)
-- --------------------------------------------------------------------------
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

