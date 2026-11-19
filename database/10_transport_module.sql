USE ssms;
SET NAMES utf8mb4;

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

ALTER TABLE vehicles
  ADD CONSTRAINT fk_vehicle_driver FOREIGN KEY (assigned_driver_id) REFERENCES drivers (id) ON DELETE SET NULL;

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
