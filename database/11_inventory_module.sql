-- ==========================================================================
-- SSMS - File : 11_inventory_module.sql
-- Module      : 2.12 Inventory and Procurement Management
-- Depends on  : 00 - 02 (schools, departments, staff, gl_accounts)
-- ==========================================================================

USE ssms;
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- Item Categories
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Item Registration (stock items)
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Supplier Management
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Stock Management (receipts, issues, returns, adjustments)
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Purchase Requests and Requisition Approval
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Purchase Orders
-- --------------------------------------------------------------------------
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

ALTER TABLE purchase_requests
  ADD CONSTRAINT fk_pr_purchase_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE SET NULL;

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

-- --------------------------------------------------------------------------
-- Asset Management (fixed assets)
-- --------------------------------------------------------------------------
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

