

USE ssms;
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- Examination Setup
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Examination Timetable and Invigilation
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Assessment Setup (component weights)
-- S1-S4 default: school based CA 20% + UNEB end-of-cycle examination 80%.
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Continuous Assessment Recording (S1-S4, 1 to 3 scale)
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Project Work Recording (mandatory for UCE under the new curriculum)
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- UNEB Continuous Assessment Submission
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Marks Entry and Marks Verification
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Grading Systems
-- Three scales are expected: CBC achievement levels (A-E), UACE points and
-- the legacy D1-F9 scale kept for historical records.
-- Boundaries are provisional settings and must be confirmed with UNEB/NCDC.
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Results Processing
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Performance Ranking (off by default for S1-S4)
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- UCE Certificate Eligibility and Result Codes
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Report Card Generation
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Transcript Management
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Remedial and Retake Records
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- UNEB Candidate Registration
-- --------------------------------------------------------------------------
CREATE TABLE uneb_candidates (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id          INT UNSIGNED NOT NULL,
  academic_year_id    INT UNSIGNED NOT NULL,
  cycle               ENUM('UCE','UACE') NOT NULL,
  candidate_number    VARCHAR(60),
  index_number        VARCHAR(60),
  registration_status ENUM('draft','validated','ready','exported','submitted','accepted','rejected') NOT NULL DEFAULT 'draft',
  eligibility_status  ENUM('not_checked','eligible','ineligible','warning') NOT NULL DEFAULT 'not_checked',
  subjects_count      SMALLINT NOT NULL DEFAULT 0,
  missing_compulsory  SMALLINT NOT NULL DEFAULT 0,
  missing_project_work SMALLINT NOT NULL DEFAULT 0,
  export_file_path    VARCHAR(255),
  exported_by         INT UNSIGNED,
  exported_at         DATETIME,
  uneb_response       TEXT,
  issues              JSON,
  notes               VARCHAR(255),
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_candidate (student_id,academic_year_id,cycle),
  KEY idx_uneb_status (cycle,registration_status),
  CONSTRAINT fk_uneb_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE
);

CREATE TABLE uneb_candidate_subjects (
  candidate_id        INT UNSIGNED NOT NULL,
  subject_id          INT UNSIGNED NOT NULL,
  entry_type          ENUM('principal','subsidiary','compulsory','elective') NOT NULL DEFAULT 'elective',
  project_work_status ENUM('recorded','missing','not_applicable') NOT NULL DEFAULT 'not_applicable',
  ca_status           ENUM('ready','partial','missing','submitted') NOT NULL DEFAULT 'ready',
  uneb_subject_code   VARCHAR(20),
  PRIMARY KEY (candidate_id,subject_id),
  CONSTRAINT fk_ucs_candidate FOREIGN KEY (candidate_id) REFERENCES uneb_candidates (id) ON DELETE CASCADE,
  CONSTRAINT fk_ucs_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- UNEB Results Records (national results after release)
-- --------------------------------------------------------------------------
CREATE TABLE uneb_results (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id         INT UNSIGNED,
  academic_year_id   INT UNSIGNED NOT NULL,
  cycle              ENUM('UCE','UACE') NOT NULL,
  index_number       VARCHAR(60),
  subject_id         INT UNSIGNED NOT NULL,
  achievement_level  VARCHAR(10),
  grade_code         VARCHAR(10),
  points             DECIMAL(5,2),
  ca_score           DECIMAL(6,2),
  division           VARCHAR(10),
  aggregate          DECIMAL(8,2),
  result_code        ENUM('Result 1','Result 2','Result 3'),
  source             ENUM('manual_entry','excel_import','uneb_file') NOT NULL DEFAULT 'excel_import',
  imported_by        INT UNSIGNED,
  imported_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  remarks            VARCHAR(255),
  PRIMARY KEY (id),
  UNIQUE KEY uq_uneb_result (student_id,academic_year_id,cycle,subject_id),
  KEY idx_uneb_result_year (academic_year_id,cycle),
  CONSTRAINT fk_uneb_res_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_res_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
  CONSTRAINT fk_uneb_res_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE
);

-- --------------------------------------------------------------------------
-- Question Bank (Optional)
-- --------------------------------------------------------------------------
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

