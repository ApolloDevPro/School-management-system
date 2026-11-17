

USE ssms;
SET NAMES utf8mb4;

-- --------------------------------------------------------------------------
-- Curriculum Management (Lower Secondary CBC and A-Level)
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Subject Management
-- uneb_subject_code is left for the school to fill from the UNEB subject list.
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Subject Allocation (subject -> class / stream)
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- A-Level Combination Management
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Student Subject Registration (module 2.2)
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Student Combination Allocation (module 2.2)
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Teacher Allocation (teacher -> subject / class / stream)
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Rooms (used by timetabling and the examination timetable)
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Timetable Management
-- The unique keys prevent a teacher or a stream being booked twice in the
-- same period of the same timetable (clash detection at database level).
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Schemes of Work
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Lesson Planning
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Syllabus Management and Syllabus Coverage
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Generic Skills (competency-based curriculum) and Integrated Activities
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Homework Management
-- --------------------------------------------------------------------------
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

-- --------------------------------------------------------------------------
-- Academic Calendar
-- --------------------------------------------------------------------------
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

